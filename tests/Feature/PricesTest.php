<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Events\PriceSyncing;
use CraftCms\Stripe\Prices;
use CraftCms\Stripe\Products;
use Illuminate\Support\Facades\Event;
use Stripe\Price as StripePrice;

beforeEach(function () {
    app(Products::class)->syncAllProducts();
});

it('creates a price element owned by its product, then updates it instead of adding another', function () {
    $source = $this->stripe->state['prices']['price_ebook'];

    expect(app(Prices::class)->createOrUpdatePrice(StripePrice::constructFrom($source)))->toBeTrue();

    $price = Price::find()->stripeId('price_ebook')->one();
    expect($price->getProduct()->stripeId)->toBe('prod_ebook')
        ->and($price->unitAmount)->toEqual(15);

    app(Prices::class)->createOrUpdatePrice(StripePrice::constructFrom(['active' => false] + $source));

    $elements = Price::find()->stripeId('price_ebook')->status(null)->all();
    expect($elements)->toHaveCount(1)
        ->and($elements[0]->stripeStatus)->toBe(Price::STRIPE_STATUS_ARCHIVED);
});

it('skips a price when a listener invalidates its sync', function () {
    Event::listen(PriceSyncing::class, function (PriceSyncing $event) {
        $event->isValid = false;
    });

    expect(app(Prices::class)->createOrUpdatePrice(StripePrice::constructFrom($this->stripe->state['prices']['price_ebook'])))->toBeFalse()
        ->and(Price::find()->stripeId('price_ebook')->status(null)->exists())->toBeFalse();
});

it('deletes a price by its Stripe ID', function () {
    app(Prices::class)->syncAllPrices();
    app(Prices::class)->deletePriceByStripeId('price_ebook');

    expect(Price::find()->stripeId('price_ebook')->status(null)->exists())->toBeFalse()
        ->and(Price::find()->stripeId('price_pro_monthly')->exists())->toBeTrue();
});

it('deletes the elements of prices Stripe no longer has', function () {
    app(Prices::class)->syncAllPrices();
    unset($this->stripe->state['prices']['price_basic_yearly']);
    app(Prices::class)->syncAllPrices();

    expect(Price::find()->status(null)->collect()->pluck('stripeId')->sort()->values()->all())
        ->toBe(['price_basic_monthly', 'price_ebook', 'price_pro_monthly']);
});
