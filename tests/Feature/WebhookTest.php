<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Tests\TestCase;
use CraftCms\Stripe\Webhooks;

beforeEach(function () {
    $this->artisan('stripe:sync', ['type' => 'products-and-prices'])->assertSuccessful();
    app(Webhooks::class)->saveWebhook('we_test_plugin', TestCase::WEBHOOK_SECRET);
    $this->stripe->state['products']['prod_pro']['name'] = 'Pro Plus';
});

it('syncs the object of a signed event', function (string $url) {
    [$payload, $signature] = signedStripeEvent('product.updated', ['id' => 'prod_pro', 'object' => 'product']);

    $this->call('POST', $url, server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $payload)
        ->assertOk();

    expect(Product::find()->stripeId('prod_pro')->one()->title)->toBe('Pro Plus');
})->with([
    'site route' => '/stripe/webhooks/handle',
    'Craft 5 action path' => '/actions/stripe/webhooks/handle',
]);

it('rejects an event signed with another secret', function () {
    [$payload, $signature] = signedStripeEvent('product.updated', ['id' => 'prod_pro', 'object' => 'product'], 'whsec_test_other');

    $this->call('POST', '/stripe/webhooks/handle', server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $payload)
        ->assertStatus(400);

    expect(Product::find()->stripeId('prod_pro')->one()->title)->toBe('Pro')
        ->and($this->stripe->requestsTo('GET', '/v1/products/prod_pro'))->toBe([]);
});

it('deletes a product when Stripe does', function () {
    [$payload, $signature] = signedStripeEvent('product.deleted', ['id' => 'prod_ebook', 'object' => 'product']);

    $this->call('POST', '/stripe/webhooks/handle', server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $payload)
        ->assertOk();

    expect(Product::find()->stripeId('prod_ebook')->status(null)->exists())->toBeFalse()
        ->and(Product::find()->stripeId('prod_basic')->exists())->toBeTrue();
});
