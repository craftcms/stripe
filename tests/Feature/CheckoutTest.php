<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Solo only allows the one admin account
    Edition::set(Edition::Pro);
    $this->artisan('stripe:sync')->assertSuccessful();
});

it('picks the checkout mode from the line items’ prices', function (array $prices, string $mode) {
    $lineItems = array_map(fn (string $price) => ['price' => $price, 'quantity' => 1], $prices);

    $this->post('/actions/stripe/checkout', ['lineItems' => $lineItems, 'customer' => 'false'])
        ->assertRedirectContains('https://checkout.stripe.test/c/pay/');

    $session = $this->stripe->requestsTo('POST', '/v1/checkout/sessions')[0];

    expect($session['mode'])->toBe($mode)
        ->and(array_column($session['line_items'], 'price'))->toBe($prices)
        ->and($session)->not->toHaveKeys(['customer', 'customer_email'])
        ->and($session['success_url'])->toBe('https://localhost/thanks');
})->with([
    'one-time only' => [['price_ebook'], 'payment'],
    'recurring' => [['price_basic_monthly'], 'subscription'],
    'mixed' => [['price_ebook', 'price_pro_monthly'], 'subscription'],
]);

it('checks out as the current user’s Stripe customer', function () {
    actingAs(UserModel::factory()->createElement(['email' => 'alice@example.com']));

    $this->post('/actions/stripe/checkout', ['lineItems' => [['price' => 'price_ebook', 'quantity' => 2]]])
        ->assertRedirect();

    $session = $this->stripe->requestsTo('POST', '/v1/checkout/sessions')[0];

    expect($session['customer'])->toBe('cus_alice')
        ->and($session['line_items'][0]['quantity'])->toBe(2);
});

it('sends the email of a user who isn’t a Stripe customer yet', function () {
    actingAs(UserModel::factory()->createElement(['email' => 'bob@example.com']));

    $this->post('/actions/stripe/checkout', ['lineItems' => [['price' => 'price_ebook', 'quantity' => 1]]])
        ->assertRedirect();

    $session = $this->stripe->requestsTo('POST', '/v1/checkout/sessions')[0];

    expect($session['customer_email'])->toBe('bob@example.com')
        ->and($session)->not->toHaveKey('customer');
});

it('ignores line items without a quantity', function () {
    $this->post('/actions/stripe/checkout', ['lineItems' => [['price' => 'price_ebook', 'quantity' => 0]]]);

    expect($this->stripe->requestsTo('POST', '/v1/checkout/sessions'))->toBe([]);
});
