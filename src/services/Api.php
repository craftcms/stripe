<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\helpers\App;
use craft\log\MonologTarget;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;
use craft\stripe\models\Invoice;
use craft\stripe\models\PaymentMethod;
use craft\stripe\Plugin;
use Generator;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use yii\base\Component;

/**
 * Api service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Api extends Component
{
    /**
     * @var string
     */
    public const STRIPE_API_VERSION = '2024-04-10';

    /**
     * @var StripeClient|null
     */
    private ?StripeClient $_client = null;

    /**
     * Retrieve all products.
     *
     * @return array
     */
    public function fetchAllProducts(): array
    {
        return $this->fetchAll('products', [
            'expand' => $this->prepExpandForFetchAll(Product::$expandParams),
        ]);
    }

    /**
     * Retrieve a product by id.
     *
     * @param string $id
     * @return StripeProduct
     */
    public function fetchProductById(string $id): StripeProduct
    {
        return $this->fetchOne($id, 'products', ['expand' => Product::$expandParams]);
    }

    /**
     * Retrieve all prices.
     *
     * @return array
     */
    public function fetchAllPrices(): array
    {
        return $this->fetchAll('prices', [
            'expand' => $this->prepExpandForFetchAll(Price::$expandParams),
        ]);
    }

    /**
     * Retrieve a price by id.
     *
     * @param string $id
     * @return StripePrice
     */
    public function fetchPriceById(string $id): StripePrice
    {
        return $this->fetchOne($id, 'prices', ['expand' => Price::$expandParams]);
    }

    /**
     * Retrieve all subscriptions.
     *
     * @return array
     */
    public function fetchAllSubscriptions(): array
    {
        return $this->fetchAll('subscriptions', [
            'status' => 'all',
            'expand' => $this->prepExpandForFetchAll(Subscription::$expandParams),
        ]);
    }

    /**
     * Retrieve a subscription by id.
     *
     * @param string $id
     * @return StripeSubscription
     */
    public function fetchSubscriptionById(string $id): StripeSubscription
    {
        return $this->fetchOne($id, 'subscriptions', [
            'expand' => Subscription::$expandParams,
        ]);
    }

    /**
     * Retrieve all payment methods.
     *
     * @return array
     */
    public function fetchAllPaymentMethods(): array
    {
        $customers = $this->fetchAllCustomers();
        $paymentMethods = [];

        foreach ($customers as $customer) {
            // get user for customer's email address
            $user = $customer->email ? Craft::$app->getUsers()->getUserByUsernameOrEmail($customer->email) : null;

            // only get payment methods if the user exists
            if ($user) {
                $results = $customer->allPaymentMethods($customer->id, [
                    'expand' => $this->prepExpandForFetchAll(PaymentMethod::$expandParams),
                ]);
                foreach ($results as $result) {
                    $paymentMethods[] = $result;
                }
            }
        }

        return $paymentMethods;
    }

    /**
     * Retrieve a payment method by customer id and payment id.
     *
     * @param string $customerId
     * @param string $paymentMethodId
     * @return StripePaymentMethod
     */
    public function fetchPaymentMethodByIds(string $customerId, string $paymentMethodId): StripePaymentMethod
    {
        return $this->getClient()->customers->retrievePaymentMethod(
            $customerId,
            $paymentMethodId,
            ['expand' => PaymentMethod::$expandParams],
        );
    }

    /**
     * Retrieve all customers.
     *
     * @param array $params
     * @return array
     */
    public function fetchAllCustomers(array $params = []): array
    {
        return $this->fetchAll('customers', array_merge($params, [
            'expand' => $this->prepExpandForFetchAll(Customer::$expandParams),
        ]));
    }

    /**
     * Retrieve a customer by id.
     *
     * @param string $id
     * @return StripeCustomer
     */
    public function fetchCustomerById(string $id): StripeCustomer
    {
        return $this->fetchOne($id, 'customers', ['expand' => Customer::$expandParams]);
    }

    /**
     * Retrieve all invoices.
     *
     * @return array
     */
    public function fetchAllInvoices(): array
    {
        return $this->fetchAll('invoices', [
            'expand' => $this->prepExpandForFetchAll(Invoice::$expandParams),
        ]);
    }

    /**
     * Retrieve an invoice by id.
     *
     * @param string $id
     * @return StripeInvoice
     */
    public function fetchInvoiceById(string $id): StripeInvoice
    {
        return $this->fetchOne($id, 'invoices', ['expand' => Invoice::$expandParams]);
    }

    /**
     * Iteratively retrieves a paginated collection of API resource.
     *
     * @param string $type name of the Stripe resource
     * @param array $params
     * @return array
     */
    public function fetchAll(string $type, array $params = []): array
    {
        $resources = [];

        // Force maximum page size:
        $params['limit'] = 100;

        $batch = $this->getClient()->$type->all($params);
        foreach ($batch->autoPagingIterator() as $item) {
            $resources[] = $item;
        }

        return $resources;
    }

    /**
     * @param string $type
     * @param array $params
     * @return Generator
     */
    public function fetchAllIterator(string $type, array $params = []): Generator
    {
        $params['limit'] = 100;

        $batch = $this->getClient()->$type->all($params);
        $buffer = [];

        foreach ($batch->autoPagingIterator() as $item) {
            $buffer[] = $item;

            if (count($buffer) === 100) {
                yield $buffer;
                $buffer = [];
            }
        }

        // Yield any remaining items
        if (!empty($buffer)) {
            yield $buffer;
        }
    }

    /**
     * Retrieves single API resource by ID.
     *
     * @param string $id Stripe ID of the object to fetch
     * @param string $type name of the Stripe resource
     * @param array $params
     * @return mixed
     */
    public function fetchOne(string $id, string $type, array $params = []): mixed
    {
        return $this->getClient()->$type->retrieve($id, $params);
    }

    /**
     * Returns or sets up a StripeClient.
     *
     * @return StripeClient
     */
    public function getClient(): StripeClient
    {
        if ($this->_client === null) {
            /** @var MonologTarget $webLogTarget */
            $webLogTarget = Craft::$app->getLog()->targets['web'];

            Stripe::setAppInfo(Plugin::getInstance()->name, Plugin::getInstance()->version, Plugin::getInstance()->documentationUrl);
            Stripe::setApiKey($this->getApiKey());
            Stripe::setApiVersion(self::STRIPE_API_VERSION);
            Stripe::setMaxNetworkRetries(3);
            Stripe::setLogger($webLogTarget->getLogger());

            $this->_client = new StripeClient([
                "api_key" => $this->getApiKey(),
                "stripe_version" => self::STRIPE_API_VERSION,
            ]);
        }

        return $this->_client;
    }

    /**
     * Get parsed secret API key
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        return App::parseEnv($settings->secretKey);
    }

    /**
     * Get parsed webhook signing secret
     * @return string|null
     */
    public function getWebhookSigningSecret(): ?string
    {
        $webhookRecord = Plugin::getInstance()->getWebhooks()->getWebhookRecord();
        return App::parseEnv($webhookRecord->webhookSigningSecret);
    }

    /**
     * Prepares expand params for use with fetchAll.
     * When fetching lists (all), expand params need to be prepended with 'data.'.
     * https://docs.stripe.com/api/expanding_objects
     *
     * @param array $params
     * @return array
     */
    public function prepExpandForFetchAll(array $params): array
    {
        array_walk($params, fn(&$item) => $item = 'data.' . $item);

        return $params;
    }
}
