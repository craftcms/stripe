<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use Stripe\Event as StripeEventObject;
use yii\base\Event;

/**
 * Event triggered once an event sent by Stripe is received and after the plugin is done processing it.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.2.0
 */
class StripeEvent extends Event
{
    /**
     * @var StripeEventObject The event from Stripe
     */
    public StripeEventObject $stripeEvent;
}
