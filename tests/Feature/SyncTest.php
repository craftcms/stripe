<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;

it('syncs products, prices and subscriptions from Stripe', function () {
    $this->artisan('stripe:sync')
        ->expectsChoice('What do you want to sync?', 'all', ['all', 'products-and-prices', 'subscriptions', 'customers', 'payment-methods', 'invoices', 'Everything', 'Products and prices', 'Subscriptions', 'Customers', 'Payment methods', 'Invoices'])
        ->assertSuccessful();

    $products = Product::find()->status(null)->orderBy('title')->collect();
    expect($products->pluck('stripeId')->all())->toBe(['prod_basic', 'prod_ebook', 'prod_pro'])
        ->and($products->first()->title)->toBe('Basic');

    $basicPrices = $products->first()->getPrices();
    expect($basicPrices->pluck('stripeId')->sort()->values()->all())->toBe(['price_basic_monthly', 'price_basic_yearly'])
        ->and(Price::find()->stripeId('price_basic_monthly')->one()->unitAmount)->toEqual(10);

    $subscription = Subscription::find()->one();
    expect($subscription->stripeId)->toBe('sub_alice')
        ->and($subscription->getCustomer()->email)->toBe('alice@example.com');
});

it('filters synced elements with the Craft 5 query params', function () {
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();

    expect(Product::find()->hasPrice(['type' => 'one_time'])->collect()->pluck('stripeId')->all())->toBe(['prod_ebook'])
        ->and(Price::find()->stripeProductId('prod_pro')->collect()->pluck('stripeId')->all())->toBe(['price_pro_monthly'])
        ->and(Subscription::find()->userEmail('alice@example.com')->count())->toBe(1)
        ->and(Subscription::find()->userEmail('bob@example.com')->count())->toBe(0);
});

it('reads the billing period from the subscription items', function () {
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();

    // The fixture's period runs from 2026-01-01 to 2026-02-01 UTC, on its only item
    expect(Subscription::find()->one()->getCurrentPeriodEnd())->toBe(1769904000)
        ->and(Subscription::find()->nextPaymentDate('> 2026-01-15')->count())->toBe(1)
        ->and(Subscription::find()->nextPaymentDate('> 2026-02-15')->count())->toBe(0);
});
