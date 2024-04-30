<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\events;

use Stripe\Checkout\Session;
use yii\base\Event;

/**
 * Class CheckoutSessionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CheckoutSessionEvent extends Event
{
    /**
     * @var array The array representation of the Stripe checkout session
     */
    public array $params;
}
