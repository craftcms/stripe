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
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use Stripe\Event as StripeEventObject;
use yii\base\Event;

class WebhooksServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::off(Webhooks::class, Webhooks::EVENT_STRIPE_EVENT);

        parent::tearDown();
    }

    public function testProductUpdatedFetchesAndSyncsProduct(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchProductById')->willReturn(
            StripeApiObjectFactory::product('prod_webhook', ['name' => 'Webhook Product'])
        );

        $this->processEvent('product.updated', ['id' => 'prod_webhook']);

        $this->assertNotNull(Product::find()->stripeId('prod_webhook')->one());
    }

    public function testProductDeletedRemovesProduct(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_webhook_delete', ['name' => 'To Delete'])
        );

        $this->processEvent('product.deleted', ['id' => 'prod_webhook_delete']);

        $this->assertNull(Product::find()->stripeId('prod_webhook_delete')->status(null)->one());
    }

    public function testPriceUpdatedFetchesAndSyncsPrice(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_price_webhook')
        );

        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchPriceById')->willReturn(
            StripeApiObjectFactory::price('price_webhook', 'prod_for_price_webhook')
        );

        $this->processEvent('price.updated', ['id' => 'price_webhook']);

        $this->assertNotNull(Price::find()->stripeId('price_webhook')->one());
    }

    public function testPriceDeletedRemovesPrice(): void
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_price_webhook_delete')
        );
        Plugin::getInstance()->getPrices()->createOrUpdatePrice(
            StripeApiObjectFactory::price('price_webhook_delete', 'prod_for_price_webhook_delete')
        );

        $this->processEvent('price.deleted', ['id' => 'price_webhook_delete']);

        $this->assertNull(Price::find()->stripeId('price_webhook_delete')->status(null)->one());
    }

    public function testCustomerSubscriptionCreatedFetchesAndCreatesSubscription(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchSubscriptionById')->willReturn(
            StripeApiObjectFactory::subscription('sub_webhook_created')
        );

        // `getUnsavedDraftByUid()` looks up the originating checkout session via the real Stripe
        // client to find a matching draft — not reachable in tests, so stub only that one method
        // and let `createOrUpdateSubscriptionElement()` run for real via a partial mock.
        $subscriptions = $this->partialMockComponent('subscriptions', Subscriptions::class, ['getUnsavedDraftByUid']);
        $subscriptions->method('getUnsavedDraftByUid')->willReturn(new Subscription());

        $this->processEvent('customer.subscription.created', ['id' => 'sub_webhook_created']);

        $this->assertNotNull(Subscription::find()->stripeId('sub_webhook_created')->one());
    }

    public function testCustomerSubscriptionUpdatedFetchesAndSyncsSubscription(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchSubscriptionById')->willReturn(
            StripeApiObjectFactory::subscription('sub_webhook_updated', ['status' => 'canceled'])
        );

        $this->processEvent('customer.subscription.updated', ['id' => 'sub_webhook_updated']);

        $subscription = Subscription::find()->stripeId('sub_webhook_updated')->status(null)->one();
        $this->assertNotNull($subscription);
        $this->assertSame('canceled', $subscription->stripeStatus);
    }

    public function testCustomerUpdatedFetchesAndSyncsCustomer(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchCustomerById')->willReturn(
            StripeApiObjectFactory::customer('cus_webhook', 'webhook@example.com')
        );

        $this->processEvent('customer.updated', ['id' => 'cus_webhook']);

        $this->assertNotNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_webhook'));
    }

    public function testCustomerDeletedRemovesCustomerAndPaymentMethods(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(
            StripeApiObjectFactory::customer('cus_webhook_delete', 'webhook-delete@example.com')
        );
        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(
            StripeApiObjectFactory::paymentMethod('pm_webhook_delete', 'cus_webhook_delete')
        );

        $this->processEvent('customer.deleted', ['id' => 'cus_webhook_delete']);

        $this->assertNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_webhook_delete'));
        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_delete'));
    }

    public function testPaymentMethodAttachedFetchesAndSyncsPaymentMethod(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchPaymentMethodByIds')->willReturn(
            StripeApiObjectFactory::paymentMethod('pm_webhook_attached', 'cus_webhook_attached')
        );

        $this->processEvent('payment_method.attached', ['id' => 'pm_webhook_attached', 'customer' => 'cus_webhook_attached']);

        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_attached'));
    }

    public function testPaymentMethodDetachedRemovesPaymentMethod(): void
    {
        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(
            StripeApiObjectFactory::paymentMethod('pm_webhook_detached', 'cus_x')
        );

        $this->processEvent('payment_method.detached', ['id' => 'pm_webhook_detached']);

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_webhook_detached'));
    }

    public function testInvoiceCreatedFetchesAndSyncsInvoice(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchInvoiceById')->willReturn(
            StripeApiObjectFactory::invoice('in_webhook', ['number' => 'INV-WEBHOOK', 'customer_email' => 'invoice@example.com'])
        );

        $this->processEvent('invoice.created', ['id' => 'in_webhook']);

        $this->assertNotNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_webhook'));
    }

    public function testInvoiceDeletedRemovesInvoice(): void
    {
        Plugin::getInstance()->getInvoices()->createOrUpdateInvoice(
            StripeApiObjectFactory::invoice('in_webhook_delete', ['status' => 'draft'])
        );

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

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_event_check')
        );

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
