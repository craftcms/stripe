<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\models;

use craft\stripe\base\Model;
use craft\stripe\Plugin;

/**
 * Stripe invoice model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Invoice extends Model
{
    /**
     * @var array|string[] Array of params that should be expanded when fetching Invoice from the Stripe API
     */
    public static array $expandParams = [];

    /**
     * Return URL to edit the invoice in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/invoices/{$this->stripeId}";
    }
}
