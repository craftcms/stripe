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
    public const PRODUCTDATA = '{{%stripestore_productdata}}';
    public const PRODUCTS = '{{%stripestore_products}}';
    public const PRICEDATA = '{{%stripestore_pricedata}}';
    public const PRICES = '{{%stripestore_prices}}';
    public const SUBSCRIPTIONDATA = '{{%stripestore_subscriptiondata}}';
    public const SUBSCRIPTIONS = '{{%stripestore_subscriptions}}';
    public const PAYMENTMETHODDATA = '{{%stripestore_paymentmethoddata}}';
    public const CUSTOMERDATA = '{{%stripestore_customerdata}}';
    public const INVOICEDATA = '{{%stripestore_invoicedata}}';
}
