<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\models;

use craft\stripe\base\Model;
use craft\stripe\Plugin;
use DateTime;

/**
 * Stripe customer model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Customer extends Model
{
    /**
     * @var string|null The customer's email from Stripe
     */
    public ?string $email = null;

    /**
     * @var ?DateTime The customer creation date in Stripe
     */
    public ?DateTime $stripeCreated = null;

    /**
     * @var array|string[] Array of params that should be expanded when fetching Customer from the Stripe API
     */
    public static array $expandParams = [];

    /**
     * Return URL to edit the customer in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/customers/{$this->stripeId}";
    }
}
