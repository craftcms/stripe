<?php

namespace craft\stripe\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\stripe\Plugin;

/**
 * Sync Single Customer Data queue job
 */
class SyncSingleCustomerData extends BaseJob
{
    /**
     * @var string The Stripe customer ID to sync
     */
    public string $stripeCustomerId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $plugin = Plugin::getInstance();
        $api = $plugin->getApi();

        // Fetch the customer from Stripe
        $stripeCustomer = $api->fetchCustomerById($this->stripeCustomerId);

        // Sync the customer data
        $plugin->getCustomers()->createOrUpdateCustomer($stripeCustomer);
        $this->setProgress($queue, 0.25);

        // Sync customer's subscriptions
        $plugin->getSubscriptions()->syncCustomerSubscriptions($stripeCustomer);
        $this->setProgress($queue, 0.5);

        // Sync customer's invoices
        $plugin->getInvoices()->syncCustomerInvoices($stripeCustomer);
        $this->setProgress($queue, 0.75);

        // Sync customer's payment methods
        $plugin->getPaymentMethods()->syncCustomerPaymentMethods($stripeCustomer);
        $this->setProgress($queue, 1);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('stripe', 'Sync Stripe data for customer {customerId}.', [
            'customerId' => $this->stripeCustomerId,
        ]);
    }
}
