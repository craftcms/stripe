<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use craft\events\CancelableEvent;
use craft\stripe\elements\Subscription;

/**
 * Event triggered when a subscription changes status.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.7.x
 */
class StripeSubscriptionStatusChangeEvent extends CancelableEvent
{
    /**
     * @var Subscription Craft subscription element being synchronized.
     */
    public Subscription $subscription;

    /**
     * @var string Status the subscription moved into.
     */
    public string $newStatus;

    /**
     * @var string|null Status the subscription moved away from.
     */
    public ?string $oldStatus = null;
}
