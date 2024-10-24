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
 * Stripe payment method model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PaymentMethod extends Model
{
    /**
     * @var array|string[] Array of params that should be expanded when fetching Payment Method from the Stripe API
     */
    public static array $expandParams = [];

    /**
     * Return URL to edit the payment method in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/customers/{$this->getData()['customer']}";
    }
}
