<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use craft\events\CancelableEvent;
use craft\stripe\elements\Subscription as SubscriptionElement;
use Stripe\Subscription as StripeSubscription;

/**
 * Event triggered just before a synchronized subscription element is going to be saved.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class StripeSubscriptionSyncEvent extends CancelableEvent
{
    /**
     * @var SubscriptionElement Craft subscription element being synchronized.
     */
    public SubscriptionElement $element;

    /**
     * @var StripeSubscription Stripe API Subscription object.
     */
    public StripeSubscription $source;
}
