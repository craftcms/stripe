<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\Invoices;
use Stripe\Invoice as StripeInvoice;

use function CraftCms\Cms\cp_url;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    Edition::set(Edition::Pro);

    foreach ([
        ['in_oldest', 'INV-001', 'alice@example.com', 1700000000],
        ['in_middle', 'INV-002', 'bob@example.com', 1700000100],
        ['in_newest', 'INV-003', 'carol@example.com', 1700000200],
    ] as [$id, $number, $email, $created]) {
        app(Invoices::class)->createOrUpdateInvoice(StripeInvoice::constructFrom(
            ['id' => $id, 'number' => $number, 'customer_email' => $email, 'created' => $created] + $this->stripe->state['invoices']['in_alice_1'],
        ));
    }
});

/**
 * Returns the invoices table the Invoices screen shows for some query params.
 *
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function invoicesTable(array $params = []): array
{
    actingAs(UserModel::factory()->admin()->createElement());

    return test()->getJson(cp_url('stripe/invoices', $params), ['X-Inertia' => 'true'])
        ->assertOk()
        ->json('props.ui.nodes.0.props');
}

it('requires access to Stripe', function () {
    actingAs(UserModel::factory()->createElement());

    $this->getJson(cp_url('stripe/invoices'), ['X-Inertia' => 'true'])->assertForbidden();
});

it('lists invoices newest first, unless a sortable column is picked', function (array $sort, array $expected) {
    expect(array_column(invoicesTable(['sort' => [$sort]])['rows'], 'id'))->toBe($expected);
})->with([
    'default' => [[], ['in_newest', 'in_middle', 'in_oldest']],
    'number, ascending' => [['field' => 'number', 'direction' => 'asc'], ['in_oldest', 'in_middle', 'in_newest']],
    'customer email, descending' => [['field' => 'customerEmail', 'direction' => 'desc'], ['in_newest', 'in_middle', 'in_oldest']],
    'unsortable column' => [['field' => 'amount', 'direction' => 'asc'], ['in_newest', 'in_middle', 'in_oldest']],
]);

it('searches invoices by number and customer email', function (string $search, string $expected) {
    expect(array_column(invoicesTable(['search' => $search])['rows'], 'id'))->toBe([$expected]);
})->with([
    'number' => ['INV-002', 'in_middle'],
    'customer email' => ['carol@example.com', 'in_newest'],
]);

it('paginates invoices', function () {
    $table = invoicesTable(['per_page' => 1, Cms::config()->getPageTriggerParam() => 2]);

    expect(array_column($table['rows'], 'id'))->toBe(['in_middle'])
        ->and($table['pagination']['current_page'])->toBe(2)
        ->and($table['pagination']['total'])->toBe(3);
});
