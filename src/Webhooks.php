<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Env;
use CraftCms\Stripe\Events\StripeEventReceived;
use CraftCms\Stripe\Models\Webhook;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\WebhookEndpoint;
use Throwable;

/**
 * Registers the Stripe webhook endpoint, and syncs the objects its events are about.
 *
 * @since 3.0.0
 */
#[Singleton]
class Webhooks
{
    /**
     * The events the registered endpoint subscribes to.
     */
    public const array EVENTS = [
        'product.created',
        'product.updated',
        'product.deleted',
        'price.created',
        'price.updated',
        'price.deleted',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.paused',
        'customer.subscription.resumed',
        'customer.subscription.pending_update_applied',
        'customer.subscription.pending_update_expired',
        'customer.subscription.deleted',
        'customer.created',
        'customer.updated',
        'customer.deleted',
        'payment_method.attached',
        'payment_method.automatically_updated',
        'payment_method.updated',
        'payment_method.detached',
        'invoice.created',
        'invoice.finalized',
        'invoice.marked_uncollectible',
        'invoice.overdue',
        'invoice.paid',
        'invoice.payment_action_required',
        'invoice.payment_failed',
        'invoice.payment_succeeded',
        'invoice.updated',
        'invoice.voided',
        'invoice.deleted',
    ];

    public function __construct(
        private readonly Api $api,
    ) {}

    /**
     * Returns the saved webhook endpoint settings, if any.
     */
    public function getWebhook(): ?Webhook
    {
        return Webhook::first();
    }

    /**
     * Saves the webhook endpoint ID and signing secret. Both may be environment variable references.
     */
    public function saveWebhook(?string $webhookId, string $webhookSigningSecret): Webhook
    {
        $webhook = $this->getWebhook() ?? new Webhook;
        $webhook->webhookId = (string) $webhookId;
        $webhook->webhookSigningSecret = $webhookSigningSecret;
        $webhook->save();

        return $webhook;
    }

    /**
     * Registers a webhook endpoint for the given URL with Stripe, and saves its ID and signing secret.
     *
     * The values are written to `STRIPE_WH_ID` and `STRIPE_WH_KEY` in `.env` when it’s writable, and saved as references to them.
     */
    public function registerEndpoint(string $url): Webhook
    {
        $endpoint = $this->api->getClient()->webhookEndpoints->create([
            'enabled_events' => self::EVENTS,
            'url' => $url,
            'api_version' => Api::STRIPE_API_VERSION,
        ]);

        return $this->saveWebhook(
            $this->writeEnvVariable('STRIPE_WH_ID', $endpoint->id),
            (string) $this->writeEnvVariable('STRIPE_WH_KEY', (string) $endpoint->secret),
        );
    }

    /**
     * Returns the registered endpoint from Stripe, or `null` when none is saved or Stripe doesn’t know it.
     */
    public function fetchEndpoint(): ?WebhookEndpoint
    {
        $id = Env::parse($this->getWebhook()?->webhookId);

        if (! $id) {
            return null;
        }

        try {
            return $this->api->getClient()->webhookEndpoints->retrieve($id);
        } catch (Throwable $e) {
            Log::warning("Couldn’t retrieve Stripe webhook endpoint $id: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Deletes the registered endpoint in Stripe, and the saved settings.
     */
    public function deleteEndpoint(): void
    {
        $webhook = $this->getWebhook();

        if ($webhook === null) {
            return;
        }

        if ($id = Env::parse($webhook->webhookId)) {
            $this->api->getClient()->webhookEndpoints->delete($id);
        }

        $webhook->delete();
    }

    /**
     * Syncs the object a Stripe event is about, then fires {@see StripeEventReceived}.
     */
    public function processEvent(Event $event): void
    {
        $object = $event->data['object'];

        match ($event->type) {
            'product.created', 'product.updated' => app(Products::class)->createOrUpdateProduct($this->api->fetchProductById($object->id)),
            'product.deleted' => app(Products::class)->deleteProductByStripeId($object->id),
            'price.created', 'price.updated' => app(Prices::class)->createOrUpdatePrice($this->api->fetchPriceById($object->id)),
            'price.deleted' => app(Prices::class)->deletePriceByStripeId($object->id),
            'customer.subscription.created' => $this->syncCreatedSubscription($object->id),
            'customer.subscription.updated',
            'customer.subscription.paused',
            'customer.subscription.resumed',
            'customer.subscription.pending_update_applied',
            'customer.subscription.pending_update_expired',
            'customer.subscription.deleted' => app(Subscriptions::class)->createOrUpdateSubscription($this->api->fetchSubscriptionById($object->id)),
            'customer.created', 'customer.updated' => app(Customers::class)->createOrUpdateCustomer($this->api->fetchCustomerById($object->id)),
            'customer.deleted' => $this->deleteCustomer($object->id),
            'payment_method.attached',
            'payment_method.automatically_updated',
            'payment_method.updated' => app(PaymentMethods::class)->createOrUpdatePaymentMethod($this->api->fetchPaymentMethodByIds($object->customer, $object->id)),
            'payment_method.detached' => app(PaymentMethods::class)->deletePaymentMethodByStripeId($object->id),
            'invoice.created',
            'invoice.finalized',
            'invoice.marked_uncollectible',
            'invoice.overdue',
            'invoice.paid',
            'invoice.payment_action_required',
            'invoice.payment_failed',
            'invoice.payment_succeeded',
            'invoice.updated',
            'invoice.voided' => app(Invoices::class)->createOrUpdateInvoice($this->api->fetchInvoiceById($object->id)),
            'invoice.deleted' => app(Invoices::class)->deleteInvoiceByStripeId($object->id),
            default => null,
        };

        event(new StripeEventReceived($event));
    }

    /**
     * Syncs a new subscription, publishing the draft its checkout created, if any.
     */
    private function syncCreatedSubscription(string $id): bool
    {
        $subscriptions = app(Subscriptions::class);
        $subscription = $this->api->fetchSubscriptionById($id);

        return $subscriptions->createOrUpdateSubscription($subscription, $subscriptions->getUnsavedDraftByUid($subscription));
    }

    private function deleteCustomer(string $id): void
    {
        app(Customers::class)->deleteCustomerDataByStripeId($id);
        app(PaymentMethods::class)->deletePaymentMethodsByCustomerId($id);
    }

    /**
     * Writes a value to `.env` and returns a reference to it, or returns the value itself when `.env` can’t be written.
     */
    private function writeEnvVariable(string $name, string $value): string
    {
        try {
            Env::writeVariable($name, $value, app()->environmentFilePath(), overwrite: true);
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("$name=$value");

            return '$'.$name;
        } catch (Throwable $e) {
            Log::warning("Couldn’t save $name to .env: {$e->getMessage()}");

            return $value;
        }
    }
}
