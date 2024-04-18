<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\events;

use craft\stripe\models\Customer;
use yii\base\Event;

/**
 * Class CheckoutSessionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CheckoutSessionEvent extends Event
{
    /**
     * @var string|Customer The Customer model or email address of the customer
     */
    public Customer|string $customer;

    /**
     * @var array array of prices and quantities for the checkout
     */
    public array $lineItems;

    /**
     * @var string|null Absolute URL to redirect the user to after checkout
     */
    public ?string $successUrl = null;

    /**
     * @var string|null Absolute URL to redirect the user to if they choose to cancel the checkout; e.g. click the back button
     */
    public ?string $cancelUrl = null;

    /**
     * @var array|null Additional params to use to instantiate the checkout session with
     */
    public ?array $params = null;
}
