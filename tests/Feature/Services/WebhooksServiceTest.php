<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\elements\Product;
use craft\stripe\elements\Price;
use craft\stripe\elements\Subscription;
use craft\stripe\events\StripeEvent;
use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\services\Subscriptions;
use craft\stripe\services\Webhooks;
use craft\stripe\tests\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\Event as StripeEventObject;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Subscription as StripeSubscription;
use yii\base\Event;

class WebhooksServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);
        Plugin::getInstance()->set('subscriptions', Subscriptions::class);
        Event::off(Webhooks::class, Webhooks::EVENT_STRIPE_EVENT);

        parent::tearDown();
    }

    public function testProductUpdatedFetchesAndSyncsProduct(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchProductById')->willReturn(StripeProduct::constructFrom([
            'id' => 'prod_webhook',
            'name' => 'Webhook Product',
            'active' => true,
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('product.updated', ['id' => 'prod_webhook']);

        $this->assertNotNull(Product::find()->stripeId('prod_webhook')->one());
    }

    public function testProductDeletedRemovesProduct(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_webhook_delete',
            'name' => 'To Delete',
            'active' => true,
        ]));

        $this->processEvent('product.deleted', ['id' => 'prod_webhook_delete']);

        $this->assertNull(Product::find()->stripeId('prod_webhook_delete')->status(null)->one());
    }

    public function testPriceUpdatedFetchesAndSyncsPrice(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_price_webhook',
            'name' => 'Product',
            'active' => true,
        ]));

        $api = $this->createMock(Api::class);
        $api->method('fetchPriceById')->willReturn(StripePrice::constructFrom([
            'id' => 'price_webhook',
            'active' => true,
            'currency' => 'usd',
            'unit_amount' => 1000,
            'product' => 'prod_for_price_webhook',
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('price.updated', ['id' => 'price_webhook']);

        $this->assertNotNull(Price::find()->stripeId('price_webhook')->one());
    }

    public function testPriceDeletedRemovesPrice(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_price_webhook_delete',
            'name' => 'Product',
            'active' => true,
        ]));
        Plugin::getInstance()->getPrices()->createOrUpdatePrice(StripePrice::constructFrom([
            'id' => 'price_webhook_delete',
            'active' => true,
            'currency' => 'usd',
            'unit_amount' => 1000,
            'product' => 'prod_for_price_webhook_delete',
        ]));

        $this->processEvent('price.deleted', ['id' => 'price_webhook_delete']);

        $this->assertNull(Price::find()->stripeId('price_webhook_delete')->status(null)->one());
    }

    public function testCustomerSubscriptionCreatedFetchesAndCreatesSubscription(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchSubscriptionById')->willReturn(StripeSubscription::constructFrom([
            'id' => 'sub_webhook_created',
            'status' => 'active',
            'items' => ['data' => []],
        ]));
        Plugin::getInstance()->set('api', $api);

        // `getUnsavedDraftByUid()` looks up the originating checkout session via the real Stripe
        // client to find a matching draft — not reachable in tests, so stub only that one method
        // and let `createOrUpdateSubscriptionElement()` run for real via a partial mock.
        $subscriptions = $this->getMockBuilder(Subscriptions::class)
            ->onlyMethods(['getUnsavedDraftByUid'])
            ->getMock();
        $subscriptions->method('getUnsavedDraftByUid')->willReturn(new Subscription());
        Plugin::getInstance()->set('subscriptions', $subscriptions);

        $this->processEvent('customer.subscription.created', ['id' => 'sub_webhook_created']);

        $this->assertNotNull(Subscription::find()->stripeId('sub_webhook_created')->one());
    }

    public function testCustomerSubscriptionUpdatedFetchesAndSyncsSubscription(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchSubscriptionById')->willReturn(StripeSubscription::constructFrom([
            'id' => 'sub_webhook_updated',
            'status' => 'canceled',
            'items' => ['data' => []],
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('customer.subscription.updated', ['id' => 'sub_webhook_updated']);

        $subscription = Subscription::find()->stripeId('sub_webhook_updated')->status(null)->one();
        $this->assertNotNull($subscription);
        $this->assertSame('canceled', $subscription->stripeStatus);
    }

    public function testCustomerUpdatedFetchesAndSyncsCustomer(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchCustomerById')->willReturn(StripeCustomer::constructFrom([
            'id' => 'cus_webhook',
            'email' => 'webhook@example.com',
            'created' => 1700000000,
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('customer.updated', ['id' => 'cus_webhook']);

        $this->assertNotNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_webhook'));
    }

    public function testCustomerDeletedRemovesCustomerAndPaymentMethods(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(StripeCustomer::constructFrom([
            'id' => 'cus_webhook_delete',
            'email' => 'webhook-delete@example.com',
            'created' => 1700000000,
        ]));
        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom([
            'id' => 'pm_webhook_delete',
            'customer' => 'cus_webhook_delete',
            'type' => 'card',
        ]));

        $this->processEvent('customer.deleted', ['id' => 'cus_webhook_delete']);

        $this->assertNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_webhook_delete'));
        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_delete'));
    }

    public function testPaymentMethodAttachedFetchesAndSyncsPaymentMethod(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchPaymentMethodByIds')->willReturn(StripePaymentMethod::constructFrom([
            'id' => 'pm_webhook_attached',
            'customer' => 'cus_webhook_attached',
            'type' => 'card',
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('payment_method.attached', ['id' => 'pm_webhook_attached', 'customer' => 'cus_webhook_attached']);

        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_attached'));
    }

    public function testPaymentMethodDetachedRemovesPaymentMethod(): void
    {
        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom([
            'id' => 'pm_webhook_detached',
            'customer' => 'cus_x',
            'type' => 'card',
        ]));

        $this->processEvent('payment_method.detached', ['id' => 'pm_webhook_detached']);

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_detached'));
    }

    public function testInvoiceCreatedFetchesAndSyncsInvoice(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchInvoiceById')->willReturn(StripeInvoice::constructFrom([
            'id' => 'in_webhook',
            'number' => 'INV-WEBHOOK',
            'status' => 'open',
            'currency' => 'usd',
            'total' => 1000,
            'customer_email' => 'invoice@example.com',
            'created' => 1700000000,
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->processEvent('invoice.created', ['id' => 'in_webhook']);

        $this->assertNotNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_webhook'));
    }

    public function testInvoiceDeletedRemovesInvoice(): void
    {
        Plugin::getInstance()->getInvoices()->createOrUpdateInvoice(StripeInvoice::constructFrom([
            'id' => 'in_webhook_delete',
            'status' => 'draft',
            'currency' => 'usd',
            'total' => 1000,
            'created' => 1700000000,
        ]));

        $this->processEvent('invoice.deleted', ['id' => 'in_webhook_delete']);

        $this->assertNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_webhook_delete'));
    }

    public function testUnknownEventTypeDoesNothingButStillFiresEvent(): void
    {
        $fired = false;
        Event::on(Webhooks::class, Webhooks::EVENT_STRIPE_EVENT, function() use (&$fired) {
            $fired = true;
        });

        $this->processEvent('some.unhandled.event', ['id' => 'irrelevant']);

        $this->assertTrue($fired);
    }

    public function testProcessEventAlwaysFiresStripeEventWithTheOriginalEvent(): void
    {
        $received = null;
        Event::on(Webhooks::class, Webhooks::EVENT_STRIPE_EVENT, function(StripeEvent $event) use (&$received) {
            $received = $event->stripeEvent;
        });

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_event_check',
            'name' => 'Product',
            'active' => true,
        ]));

        $this->processEvent('product.deleted', ['id' => 'prod_for_event_check']);

        $this->assertNotNull($received);
        $this->assertSame('product.deleted', $received->type);
    }

    private function processEvent(string $type, array $object): void
    {
        $event = StripeEventObject::constructFrom([
            'id' => 'evt_' . $type,
            'type' => $type,
            'data' => ['object' => $object],
        ]);

        Plugin::getInstance()->getWebhooks()->processEvent($event);
    }
}
