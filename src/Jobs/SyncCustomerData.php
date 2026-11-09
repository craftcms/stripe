<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Jobs;

use CraftCms\Cms\Queue\Job;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Subscriptions;
use Override;

use function CraftCms\Cms\t;

/**
 * Syncs a Stripe customer, with their subscriptions, invoices and payment methods.
 *
 * @since 3.0.0
 */
class SyncCustomerData extends Job
{
    public function __construct(
        public readonly string $stripeCustomerId,
    ) {
        parent::__construct();
    }

    public function handle(Api $api, Customers $customers, Subscriptions $subscriptions, Invoices $invoices, PaymentMethods $paymentMethods): void
    {
        $customer = $api->fetchCustomerById($this->stripeCustomerId);

        $customers->createOrUpdateCustomer($customer);
        $subscriptions->syncCustomerSubscriptions($customer);
        $invoices->syncCustomerInvoices($customer);
        $paymentMethods->syncCustomerPaymentMethods($customer);
    }

    #[Override]
    protected function defaultDescription(): string
    {
        return t('Sync Stripe data for customer {customerId}.', ['customerId' => $this->stripeCustomerId], 'stripe');
    }
}
