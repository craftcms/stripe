<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;

beforeEach(function () {
    $state = &$this->stripe->state;
    $state['products']['prod_ebook']['active'] = false;
    $state['prices']['price_basic_yearly']['active'] = false;
    $state['prices']['price_ebook']['currency'] = 'eur';
    $state['subscriptions']['sub_bob'] = ['id' => 'sub_bob', 'status' => 'past_due'] + $state['subscriptions']['sub_alice'];

    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();
});

it('filters prices', function (Closure $query, array $expected) {
    expect($query(Price::find())->collect()->pluck('stripeId')->sort()->values()->all())->toBe($expected);
})->with([
    'live' => [fn ($query) => $query->status(Price::STATUS_LIVE), ['price_basic_monthly', 'price_ebook', 'price_pro_monthly']],
    'archived in Stripe' => [fn ($query) => $query->status(Price::STATUS_STRIPE_ARCHIVED), ['price_basic_yearly']],
    'primary currency' => [fn ($query) => $query->primaryCurrency('eur'), ['price_ebook']],
    'type' => [fn ($query) => $query->type('recurring'), ['price_basic_monthly', 'price_basic_yearly', 'price_pro_monthly']],
    'owner' => [fn ($query) => $query->primaryOwnerId(Product::find()->stripeId('prod_basic')->one()->id), ['price_basic_monthly', 'price_basic_yearly']],
]);

it('filters products', function (Closure $query, array $expected) {
    expect($query(Product::find())->collect()->pluck('stripeId')->sort()->values()->all())->toBe($expected);
})->with([
    'live' => [fn ($query) => $query->status(Product::STATUS_LIVE), ['prod_basic', 'prod_pro']],
    'archived in Stripe' => [fn ($query) => $query->status(Product::STATUS_STRIPE_ARCHIVED), ['prod_ebook']],
    'Stripe ID' => [fn ($query) => $query->stripeId('prod_pro'), ['prod_pro']],
]);

it('filters subscriptions', function (Closure $query, array $expected) {
    expect($query(Subscription::find()->status(null))->collect()->pluck('stripeId')->sort()->values()->all())->toBe($expected);
})->with([
    'Stripe status' => [fn ($query) => $query->stripeStatus('past_due'), ['sub_bob']],
    'Stripe statuses' => [fn ($query) => $query->stripeStatus(['active', 'past_due']), ['sub_alice', 'sub_bob']],
    'Stripe ID' => [fn ($query) => $query->stripeId('sub_alice'), ['sub_alice']],
]);
