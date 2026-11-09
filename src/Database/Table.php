<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Database;

/**
 * The Stripe plugin’s database table names.
 *
 * @since 3.0.0
 */
final class Table
{
    public const string PRODUCTS = 'stripe_products';

    public const string PRODUCTDATA = 'stripe_productdata';

    public const string PRICES = 'stripe_prices';

    public const string PRICEDATA = 'stripe_pricedata';

    public const string SUBSCRIPTIONS = 'stripe_subscriptions';

    public const string SUBSCRIPTIONDATA = 'stripe_subscriptiondata';

    public const string CUSTOMERDATA = 'stripe_customerdata';

    public const string INVOICEDATA = 'stripe_invoicedata';

    public const string PAYMENTMETHODDATA = 'stripe_paymentmethoddata';

    public const string WEBHOOKS = 'stripe_webhooks';
}
