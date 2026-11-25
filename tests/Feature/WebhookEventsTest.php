<?php

declare(strict_types=1);

use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Events\StripeEventReceived;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Subscriptions;
use CraftCms\Stripe\Webhooks;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Stripe\Event as StripeEvent;
use Stripe\PaymentMethod as StripePaymentMethod;

beforeEach(function () {
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();
});

/**
 * Processes a Stripe event, as the webhook controller does once the signature checks out.
 *
 * @param  array<string, mixed>  $object
 */
function processStripeEvent(string $type, array $object): void
{
    app(Webhooks::class)->processEvent(StripeEvent::constructFrom([
        'id' => 'evt_test_1',
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ]));
}

it('syncs a price when Stripe updates it, and deletes it when Stripe does', function () {
    $this->stripe->state['prices']['price_ebook']['unit_amount'] = 2000;
    processStripeEvent('price.updated', ['id' => 'price_ebook', 'object' => 'price']);

    expect(Price::find()->stripeId('price_ebook')->one()->unitAmount)->toEqual(20);

    processStripeEvent('price.deleted', ['id' => 'price_ebook', 'object' => 'price']);

    expect(Price::find()->stripeId('price_ebook')->status(null)->exists())->toBeFalse();
});

it('syncs a new subscription', function () {
    // The fake Stripe API doesn’t list Checkout sessions, which the plugin checks for a draft to publish
    $this->partialMock(Subscriptions::class, fn (MockInterface $mock) => $mock->shouldReceive('getUnsavedDraftByUid')->andReturnNull());
    app(Subscriptions::class)->deleteSubscriptionByStripeId('sub_alice');

    processStripeEvent('customer.subscription.created', ['id' => 'sub_alice', 'object' => 'subscription']);

    expect(Subscription::find()->stripeId('sub_alice')->exists())->toBeTrue();
});

it('syncs a subscription when Stripe updates it', function () {
    $this->stripe->state['subscriptions']['sub_alice']['cancel_at_period_end'] = true;
    processStripeEvent('customer.subscription.updated', ['id' => 'sub_alice', 'object' => 'subscription']);

    expect(Subscription::find()->stripeId('sub_alice')->one()->getData()['cancel_at_period_end'])->toBeTrue();
});

it('syncs a customer when Stripe updates it', function () {
    $this->stripe->state['customers']['cus_alice']['email'] = 'alice@example.org';
    processStripeEvent('customer.updated', ['id' => 'cus_alice', 'object' => 'customer']);

    expect(app(Customers::class)->getCustomerByStripeId('cus_alice')->email)->toBe('alice@example.org');
});

it('deletes a customer and their payment methods when Stripe deletes the customer', function () {
    app(PaymentMethods::class)->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom($this->stripe->state['payment_methods']['pm_alice_card']));

    processStripeEvent('customer.deleted', ['id' => 'cus_alice', 'object' => 'customer']);

    expect(app(Customers::class)->getCustomerByStripeId('cus_alice'))->toBeNull()
        ->and(app(PaymentMethods::class)->getPaymentMethodsByCustomerId('cus_alice'))->toBe([]);
});

it('syncs a payment method when it’s attached, and deletes it when it’s detached', function () {
    $object = ['id' => 'pm_alice_card', 'object' => 'payment_method', 'customer' => 'cus_alice'];

    processStripeEvent('payment_method.attached', $object);
    expect(app(PaymentMethods::class)->getPaymentMethodById('pm_alice_card'))->not->toBeNull();

    processStripeEvent('payment_method.detached', $object);
    expect(app(PaymentMethods::class)->getPaymentMethodById('pm_alice_card'))->toBeNull();
});

it('syncs an invoice when Stripe creates it, and deletes it when Stripe does', function () {
    app(Invoices::class)->deleteInvoiceByStripeId('in_alice_1');

    processStripeEvent('invoice.created', ['id' => 'in_alice_1', 'object' => 'invoice']);
    expect(app(Invoices::class)->getInvoiceById('in_alice_1'))->not->toBeNull();

    processStripeEvent('invoice.deleted', ['id' => 'in_alice_1', 'object' => 'invoice']);
    expect(app(Invoices::class)->getInvoiceById('in_alice_1'))->toBeNull();
});

it('fires StripeEventReceived with the original event, even for events it ignores', function (string $type) {
    $received = null;
    Event::listen(StripeEventReceived::class, function (StripeEventReceived $event) use (&$received) {
        $received = $event->stripeEvent;
    });

    processStripeEvent($type, ['id' => 'prod_ebook', 'object' => 'product']);

    expect($received?->type)->toBe($type);
})->with([
    'product.deleted',
    'some.unhandled.event',
]);
