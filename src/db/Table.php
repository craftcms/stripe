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
}
