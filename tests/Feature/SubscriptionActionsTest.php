<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\Elements\Subscription;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Edition::set(Edition::Pro);
    actingAs(UserModel::factory()->admin()->createElement());
});

/**
 * Returns the Stripe actions in a subscription’s action menu, such as `cancel immediately`.
 *
 * @return list<string>
 */
function subscriptionStripeActions(Subscription $subscription): array
{
    return collect($subscription->actionMenuDescriptors())
        ->map(fn (array $item) => preg_match('#stripe/subscriptions/(\w+)#', $item['behavior']['actionUrl'] ?? '', $match)
            ? $match[1].(isset($item['behavior']['params']['immediately']) ? ' immediately' : '')
            : null)
        ->filter()
        ->values()
        ->all();
}

it('offers the cancel and resume actions that fit the subscription', function (array $changes, array $expected) {
    $this->stripe->state['subscriptions']['sub_alice'] = $changes + $this->stripe->state['subscriptions']['sub_alice'];
    $this->artisan('stripe:sync', ['type' => 'subscriptions'])->assertSuccessful();

    expect(subscriptionStripeActions(Subscription::find()->stripeId('sub_alice')->status(null)->one()))->toBe($expected);
})->with([
    'active' => [[], ['cancel', 'cancel immediately']],
    'canceling at period end' => [['cancel_at_period_end' => true], ['resume', 'cancel immediately']],
    'canceled' => [['status' => 'canceled'], []],
]);

it('cancels and resumes a subscription from the control panel', function () {
    $this->artisan('stripe:sync', ['type' => 'subscriptions'])->assertSuccessful();

    $this->postJson('/actions/stripe/subscriptions/cancel', ['stripeId' => 'sub_alice'])->assertOk();
    expect($this->stripe->requestsTo('POST', '/v1/subscriptions/sub_alice'))->toBe([['cancel_at_period_end' => 'true']]);

    $this->postJson('/actions/stripe/subscriptions/resume', ['stripeId' => 'sub_alice'])->assertOk();
    $this->postJson('/actions/stripe/subscriptions/cancel', ['stripeId' => 'sub_alice', 'immediately' => true])->assertOk();
    expect($this->stripe->requestsTo('DELETE', '/v1/subscriptions/sub_alice'))->toHaveCount(1);
});

it('can’t cancel or resume an unknown subscription', function (string $action) {
    $this->postJson("/actions/stripe/subscriptions/$action", ['stripeId' => 'sub_missing'])->assertNotFound();
})->with(['cancel', 'resume']);
