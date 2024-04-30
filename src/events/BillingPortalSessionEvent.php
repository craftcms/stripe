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
     * @var array|null Modify the params to use to instantiate the billing portal session with
     */
    public ?array $params = null;
}
