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
     * @var Session The Stripe checkout session object
     */
    public Session $session;
}
