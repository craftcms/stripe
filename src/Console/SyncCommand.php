<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Jobs\SyncCustomerData;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Prices;
use CraftCms\Stripe\Products;
use CraftCms\Stripe\Subscriptions;
use Illuminate\Console\Command;
use Override;

/**
 * Syncs data from Stripe.
 *
 * @since 2.0.0
 */
class SyncCommand extends Command
{
    use CraftCommand;

    public const array TYPES = ['all', 'products-and-prices', 'subscriptions', 'customers', 'payment-methods', 'invoices'];

    #[Override]
    protected $signature = 'stripe:sync
        {type=all : What to sync: all, products-and-prices, subscriptions, customers, payment-methods or invoices.}
        {--customer= : A Stripe customer ID, to sync only that customer, with their subscriptions, invoices and payment methods.}';

    #[Override]
    protected $description = 'Syncs data from Stripe.';

    public function handle(Api $api): int
    {
        if (! $api->isConfigured()) {
            $this->components->error('No Stripe secret key is set. Set one in the plugin settings first.');

            return self::FAILURE;
        }

        if ($customerId = $this->option('customer')) {
            $this->components->task("Syncing customer $customerId", fn () => dispatch_sync(new SyncCustomerData($customerId)));

            return self::SUCCESS;
        }

        $type = $this->argument('type');

        if (! in_array($type, self::TYPES, true)) {
            $this->components->error(sprintf('Unknown type “%s”. Use one of: %s.', $type, implode(', ', self::TYPES)));

            return self::FAILURE;
        }

        $all = $type === 'all';

        if ($all || $type === 'products-and-prices') {
            $this->components->task('Syncing products', fn () => app(Products::class)->syncAllProducts());
            $this->components->task('Syncing prices', fn () => app(Prices::class)->syncAllPrices());
        }

        if ($all || $type === 'subscriptions') {
            $this->components->task('Syncing subscriptions', fn () => app(Subscriptions::class)->syncAllSubscriptions());
        }

        if ($all || $type === 'customers') {
            $this->components->task('Syncing customers', fn () => app(Customers::class)->syncAllCustomers());
        }

        if ($all || $type === 'payment-methods') {
            $this->components->task('Syncing payment methods', fn () => app(PaymentMethods::class)->syncAllPaymentMethods());
        }

        if ($all || $type === 'invoices') {
            $this->components->task('Syncing invoices', fn () => app(Invoices::class)->syncAllInvoices());
        }

        return self::SUCCESS;
    }
}
