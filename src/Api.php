<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Env;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\Invoice;
use CraftCms\Stripe\Models\PaymentMethod;
use Generator;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Throwable;

/**
 * Talks to the Stripe API.
 *
 * @since 3.0.0
 */
#[Singleton]
class Api
{
    /**
     * The Stripe API version the plugin is written against.
     */
    public const string STRIPE_API_VERSION = '2026-01-28.clover';

    /**
     * The Stripe Dashboard URL.
     */
    public const string DASHBOARD_URL = 'https://dashboard.stripe.com';

    private ?StripeClient $client = null;

    private ?string $clientKey = null;

    /**
     * Returns the Stripe API client, set up with the current secret key.
     *
     * Craft-based apps can also use it from Twig, as `craft.stripe.api.client`.
     */
    public function getClient(): StripeClient
    {
        $apiKey = $this->getApiKey();

        if ($this->client === null || $this->clientKey !== $apiKey) {
            $plugin = Plugin::getInstance();

            Stripe::setAppInfo($plugin->name ?? 'Stripe', $plugin->version, $plugin->documentationUrl);
            Stripe::setApiKey($apiKey);
            Stripe::setApiVersion(self::STRIPE_API_VERSION);
            Stripe::setMaxNetworkRetries(3);
            Stripe::setLogger(Log::channel());

            $this->client = new StripeClient([
                'api_key' => $apiKey,
                'stripe_version' => self::STRIPE_API_VERSION,
            ]);
            $this->clientKey = $apiKey;
        }

        return $this->client;
    }

    /**
     * Returns the secret API key, with any environment variable resolved.
     */
    public function getApiKey(): ?string
    {
        return Env::parse(Plugin::getInstance()->getSettings()->secretKey) ?: null;
    }

    /**
     * Returns the publishable API key, with any environment variable resolved.
     */
    public function getPublishableKey(): ?string
    {
        return Env::parse(Plugin::getInstance()->getSettings()->publishableKey) ?: null;
    }

    /**
     * Whether a secret API key is set.
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null;
    }

    /**
     * Returns the Stripe mode of the secret key: `test` or `live`.
     */
    public function getMode(): string
    {
        $apiKey = $this->getApiKey() ?? '';

        return str_starts_with($apiKey, 'sk_live_') || str_starts_with($apiKey, 'rk_live_') ? 'live' : 'test';
    }

    /**
     * Returns the Stripe account ID for the current API key, cached for a day.
     */
    public function getAccountId(): ?string
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === null) {
            return null;
        }

        return Cache::remember('stripe-account-id-'.md5($apiKey), 86400, function () {
            try {
                return $this->getClient()->accounts->retrieve()->id;
            } catch (Throwable $e) {
                Log::warning("Failed to retrieve Stripe account ID: {$e->getMessage()}");

                return null;
            }
        });
    }

    /**
     * Returns a Stripe Dashboard URL for the given path, in the account and mode of the current key.
     */
    public function dashboardUrl(string $path = ''): string
    {
        $mode = $this->getMode() === 'test' ? '/test' : '';
        $accountId = $this->getAccountId();
        $base = self::DASHBOARD_URL.($accountId ? "/$accountId" : '').$mode;

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    /**
     * Returns the webhook signing secret, with any environment variable resolved.
     */
    public function getWebhookSigningSecret(): ?string
    {
        $webhook = app(Webhooks::class)->getWebhook();

        return Env::parse($webhook?->webhookSigningSecret) ?: null;
    }

    /**
     * @return StripeProduct[]
     */
    public function fetchAllProducts(): array
    {
        return $this->fetchAll('products', ['expand' => $this->prepExpandForFetchAll(Product::$expandParams)]);
    }

    public function fetchProductById(string $id): StripeProduct
    {
        return $this->fetchOne($id, 'products', ['expand' => Product::$expandParams]);
    }

    /**
     * @return StripePrice[]
     */
    public function fetchAllPrices(): array
    {
        return $this->fetchAll('prices', ['expand' => $this->prepExpandForFetchAll(Price::$expandParams)]);
    }

    public function fetchPriceById(string $id): StripePrice
    {
        return $this->fetchOne($id, 'prices', ['expand' => Price::$expandParams]);
    }

    /**
     * @return StripeSubscription[]
     */
    public function fetchAllSubscriptions(): array
    {
        return $this->fetchAll('subscriptions', [
            'status' => 'all',
            'expand' => $this->prepExpandForFetchAll(Subscription::$expandParams),
        ]);
    }

    public function fetchSubscriptionById(string $id): StripeSubscription
    {
        return $this->fetchOne($id, 'subscriptions', ['expand' => Subscription::$expandParams]);
    }

    /**
     * Fetches the payment methods of every customer whose email belongs to a Craft user.
     *
     * @return StripePaymentMethod[]
     */
    public function fetchAllPaymentMethods(): array
    {
        $paymentMethods = [];

        foreach ($this->fetchAllCustomers() as $customer) {
            if (! $customer->email || ! app(Customers::class)->userExistsForEmail($customer->email)) {
                continue;
            }

            foreach ($this->fetchPaymentMethodsForCustomer($customer->id) as $paymentMethod) {
                $paymentMethods[] = $paymentMethod;
            }
        }

        return $paymentMethods;
    }

    /**
     * @return StripePaymentMethod[]
     */
    public function fetchPaymentMethodsForCustomer(string $customerId): array
    {
        $paymentMethods = [];
        $list = $this->getClient()->customers->allPaymentMethods($customerId, [
            'limit' => 100,
            'expand' => $this->prepExpandForFetchAll(PaymentMethod::$expandParams),
        ]);

        foreach ($list->autoPagingIterator() as $paymentMethod) {
            $paymentMethods[] = $paymentMethod;
        }

        return $paymentMethods;
    }

    public function fetchPaymentMethodByIds(string $customerId, string $paymentMethodId): StripePaymentMethod
    {
        return $this->getClient()->customers->retrievePaymentMethod($customerId, $paymentMethodId, [
            'expand' => PaymentMethod::$expandParams,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return StripeCustomer[]
     */
    public function fetchAllCustomers(array $params = []): array
    {
        return $this->fetchAll('customers', [
            ...$params,
            'expand' => $this->prepExpandForFetchAll(Customer::$expandParams),
        ]);
    }

    public function fetchCustomerById(string $id): StripeCustomer
    {
        return $this->fetchOne($id, 'customers', ['expand' => Customer::$expandParams]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return StripeInvoice[]
     */
    public function fetchAllInvoices(array $params = []): array
    {
        return $this->fetchAll('invoices', [
            ...$params,
            'expand' => $this->prepExpandForFetchAll(Invoice::$expandParams),
        ]);
    }

    public function fetchInvoiceById(string $id): StripeInvoice
    {
        return $this->fetchOne($id, 'invoices', ['expand' => Invoice::$expandParams]);
    }

    /**
     * Fetches every object of a Stripe resource, following pagination.
     *
     * @param  string  $type  The client service name, such as `products`
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    public function fetchAll(string $type, array $params = []): array
    {
        $resources = [];

        foreach ($this->fetchAllIterator($type, $params) as $batch) {
            array_push($resources, ...$batch);
        }

        return $resources;
    }

    /**
     * Yields every object of a Stripe resource in batches of up to 100.
     *
     * @param  array<string, mixed>  $params
     * @return Generator<int, array<int, mixed>>
     */
    public function fetchAllIterator(string $type, array $params = []): Generator
    {
        $params['limit'] = 100;
        $buffer = [];

        foreach ($this->getClient()->$type->all($params)->autoPagingIterator() as $item) {
            $buffer[] = $item;

            if (count($buffer) === 100) {
                yield $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            yield $buffer;
        }
    }

    /**
     * Fetches a single object of a Stripe resource by its ID.
     *
     * @param  array<string, mixed>  $params
     */
    public function fetchOne(string $id, string $type, array $params = []): mixed
    {
        return $this->getClient()->$type->retrieve($id, $params);
    }

    /**
     * Prefixes expand params with `data.`, as lists need.
     *
     * @param  string[]  $params
     * @return string[]
     */
    public function prepExpandForFetchAll(array $params): array
    {
        return array_map(fn (string $param) => "data.$param", $params);
    }
}
