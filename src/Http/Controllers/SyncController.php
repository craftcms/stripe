<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Jobs\SyncCustomerData;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Prices;
use CraftCms\Stripe\Products;
use CraftCms\Stripe\Subscriptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Syncs data from Stripe on request.
 *
 * @since 3.0.0
 */
class SyncController
{
    use RespondsWithFlash;

    /**
     * Syncs everything. Large accounts may time out here; `php artisan stripe:sync` doesn’t.
     */
    public function all(): Response
    {
        app(Products::class)->syncAllProducts();
        app(Prices::class)->syncAllPrices();
        app(Subscriptions::class)->syncAllSubscriptions();
        app(Customers::class)->syncAllCustomers();
        app(PaymentMethods::class)->syncAllPaymentMethods();
        app(Invoices::class)->syncAllInvoices();

        return $this->asSuccess(t('Stripe Products, Prices, Subscriptions, Customers, Invoices and Payment Methods successfully synced', category: 'stripe'));
    }

    /**
     * Syncs the Stripe customers with a user’s email, with their subscriptions, invoices and payment methods.
     */
    public function user(Request $request, Api $api): Response
    {
        $userId = (int) $request->validate(['userId' => ['required', 'integer']])['userId'];
        $user = User::find()->id($userId)->status(null)->one();

        abort_unless($user instanceof User && $user->email, 404);

        foreach ($api->fetchAllCustomers(['email' => $user->email]) as $customer) {
            dispatch_sync(new SyncCustomerData($customer->id));
        }

        return $this->asSuccess(t('Stripe Customers successfully synced', category: 'stripe'));
    }
}
