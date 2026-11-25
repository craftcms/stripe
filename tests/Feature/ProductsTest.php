<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Events\ProductSyncing;
use CraftCms\Stripe\Products;
use Illuminate\Support\Facades\Event;
use Stripe\Product as StripeProduct;

it('creates a product element, then updates it instead of adding another', function () {
    $source = $this->stripe->state['products']['prod_basic'];

    expect(app(Products::class)->createOrUpdateProduct(StripeProduct::constructFrom($source)))->toBeTrue()
        ->and(Product::find()->stripeId('prod_basic')->one()->title)->toBe('Basic');

    app(Products::class)->createOrUpdateProduct(StripeProduct::constructFrom(['name' => 'Basic Plus', 'active' => false] + $source));

    $elements = Product::find()->stripeId('prod_basic')->status(null)->all();
    expect($elements)->toHaveCount(1)
        ->and($elements[0]->title)->toBe('Basic Plus')
        ->and($elements[0]->stripeStatus)->toBe(Product::STRIPE_STATUS_ARCHIVED);
});

it('skips a product when a listener invalidates its sync', function () {
    Event::listen(ProductSyncing::class, function (ProductSyncing $event) {
        $event->isValid = false;
    });

    expect(app(Products::class)->createOrUpdateProduct(StripeProduct::constructFrom($this->stripe->state['products']['prod_basic'])))->toBeFalse()
        ->and(Product::find()->stripeId('prod_basic')->status(null)->exists())->toBeFalse();
});

it('deletes a product by its Stripe ID', function () {
    app(Products::class)->syncAllProducts();
    app(Products::class)->deleteProductByStripeId('prod_ebook');

    expect(Product::find()->status(null)->collect()->pluck('stripeId')->sort()->values()->all())->toBe(['prod_basic', 'prod_pro']);
});

it('deletes the elements of products Stripe no longer has', function () {
    app(Products::class)->syncAllProducts();
    unset($this->stripe->state['products']['prod_ebook']);
    app(Products::class)->syncAllProducts();

    expect(Product::find()->status(null)->collect()->pluck('stripeId')->sort()->values()->all())->toBe(['prod_basic', 'prod_pro']);
});
