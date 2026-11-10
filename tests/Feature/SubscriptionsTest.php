<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Subscriptions;

beforeEach(function () {
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();
});

it('cancels a subscription at the end of its period, then resumes it', function () {
    $subscriptions = app(Subscriptions::class);

    expect($subscriptions->cancelSubscriptionByStripeId('sub_alice'))->toBeTrue()
        ->and($this->stripe->requestsTo('POST', '/v1/subscriptions/sub_alice'))->toBe([['cancel_at_period_end' => 'true']])
        ->and(Subscription::find()->stripeId('sub_alice')->one()->getData()['cancel_at_period_end'])->toBeTrue()
        ->and(Subscription::find()->stripeId('sub_alice')->one()->getData()['status'])->toBe('active');

    expect($subscriptions->resumeSubscriptionByStripeId('sub_alice'))->toBeTrue()
        ->and(Subscription::find()->stripeId('sub_alice')->one()->getData()['cancel_at_period_end'])->toBeFalse();
});

it('cancels a subscription immediately', function () {
    expect(app(Subscriptions::class)->cancelSubscriptionByStripeId('sub_alice', immediately: true))->toBeTrue()
        ->and($this->stripe->requestsTo('DELETE', '/v1/subscriptions/sub_alice'))->toHaveCount(1)
        ->and(Subscription::find()->stripeId('sub_alice')->status(null)->one()->getData()['status'])->toBe('canceled');
});

it('reports a failure when Stripe rejects the change', function () {
    expect(app(Subscriptions::class)->cancelSubscriptionByStripeId('sub_missing'))->toBeFalse();
});
