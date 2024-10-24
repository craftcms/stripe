<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\db;

/**
 * This class provides public constants for defining Stripe’s database table names.
 * Do not use these in migrations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
abstract class Table
{
    public const PRODUCTDATA = '{{%stripe_productdata}}';
    public const PRODUCTS = '{{%stripe_products}}';
    public const PRICEDATA = '{{%stripe_pricedata}}';
    public const PRICES = '{{%stripe_prices}}';
    public const SUBSCRIPTIONDATA = '{{%stripe_subscriptiondata}}';
    public const SUBSCRIPTIONS = '{{%stripe_subscriptions}}';
    public const PAYMENTMETHODDATA = '{{%stripe_paymentmethoddata}}';
    public const CUSTOMERDATA = '{{%stripe_customerdata}}';
    public const INVOICEDATA = '{{%stripe_invoicedata}}';
    public const WEBHOOKS = '{{%stripe_webhooks}}';
}
