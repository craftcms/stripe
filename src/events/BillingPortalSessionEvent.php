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
 * Class BillingPortalSessionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class BillingPortalSessionEvent extends Event
{
    /**
     * @var Customer The Customer model of the customer
     */
    public Customer $customer;

    /**
     * @var ?string The configuration ID for the billing portal session
     */
    public ?string $configurationId = null;

    /**
     * @var string|null Absolute URL to redirect the user to after checkout
     */
    public ?string $returnUrl = null;

    /**
     * @var array|null Additional params to use to instantiate the checkout session with
     */
    public ?array $params = null;
}
