<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;

it('syncs products, prices and subscriptions from Stripe', function () {
    $this->artisan('stripe:sync')->assertSuccessful();

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
    $this->artisan('stripe:sync')->assertSuccessful();

    expect(Product::find()->hasPrice(['type' => 'one_time'])->collect()->pluck('stripeId')->all())->toBe(['prod_ebook'])
        ->and(Price::find()->stripeProductId('prod_pro')->collect()->pluck('stripeId')->all())->toBe(['price_pro_monthly'])
        ->and(Subscription::find()->userEmail('alice@example.com')->count())->toBe(1)
        ->and(Subscription::find()->userEmail('bob@example.com')->count())->toBe(0);
});
