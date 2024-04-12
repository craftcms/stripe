<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\App;
use craft\log\MonologTarget;
use craft\stripe\Plugin;
use Stripe\Stripe;
use yii\base\Component;
use Stripe\StripeClient;

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
        return $this->fetchAll('products', ['expand' => ['data.default_price']]);
    }

    /**
     * Retrieve all prices.
     *
     * @return array
     */
    public function fetchAllPrices(): array
    {
        return $this->fetchAll('prices'/*, ['expand' => ['data.product']]*/);
    }

    /**
     * Retrieve all subscriptions.
     *
     * @return array
     */
    public function fetchAllSubscriptions(): array
    {
        return $this->fetchAll('subscriptions', ['status' => 'all']);
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
            $results = $customer->allPaymentMethods($customer->id);
            foreach ($results as $result) {
                $paymentMethods[] = $result;
            }
        }

        return $paymentMethods;
    }

    /**
     * Retrieve all customers.
     *
     * @return array
     */
    public function fetchAllCustomers(): array
    {
        return $this->fetchAll('customers');
    }

    /**
     * Retrieve all invoices.
     *
     * @return array
     */
    public function fetchAllInvoices(): array
    {
        return $this->fetchAll('invoices');
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
}
