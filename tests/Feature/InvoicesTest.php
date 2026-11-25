<?php

declare(strict_types=1);

use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Models\Invoice;
use CraftCms\Stripe\Support\InvoiceTable;
use Stripe\Invoice as StripeInvoice;

it('creates an invoice record, then updates it instead of adding another', function () {
    $source = $this->stripe->state['invoices']['in_alice_1'];

    expect(app(Invoices::class)->createOrUpdateInvoice(StripeInvoice::constructFrom($source)))->toBeTrue();

    app(Invoices::class)->createOrUpdateInvoice(StripeInvoice::constructFrom(['status' => 'void'] + $source));

    expect(Invoice::where('stripeId', 'in_alice_1')->count())->toBe(1)
        ->and(app(Invoices::class)->getInvoiceById('in_alice_1')->getData()['status'])->toBe('void');
});

it('syncs every invoice from Stripe, and finds them by Stripe ID', function () {
    expect(app(Invoices::class)->syncAllInvoices())->toBe(1)
        ->and(app(Invoices::class)->getInvoiceById('in_alice_1'))->not->toBeNull()
        ->and(app(Invoices::class)->getInvoiceById('in_missing'))->toBeNull();
});

it('deletes an invoice by its Stripe ID', function () {
    app(Invoices::class)->syncAllInvoices();
    app(Invoices::class)->deleteInvoiceByStripeId('in_alice_1');

    expect(app(Invoices::class)->getInvoiceById('in_alice_1'))->toBeNull();
});

it('formats invoice table rows', function (array $overrides, string $number, string $amount) {
    app(Invoices::class)->createOrUpdateInvoice(StripeInvoice::constructFrom($overrides + $this->stripe->state['invoices']['in_alice_1']));

    $row = InvoiceTable::rows(app(Invoices::class)->getAllInvoices())[0];

    expect($row['number']['label'])->toBe($number)
        ->and($row['amount'])->toBe($amount)
        ->and($row['customerEmail'])->toBe('alice@example.com');
})->with([
    'numbered' => [[], 'ALICE-0001', '$10.00'],
    'draft without a number' => [['number' => null], 'Draft', '$10.00'],
    'zero-decimal currency' => [['currency' => 'jpy', 'total' => 1000], 'ALICE-0001', '¥1,000'],
]);
