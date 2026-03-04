<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use craft\elements\User;
use craft\events\CancelableEvent;
use craft\models\UserGroup;
use craft\stripe\elements\Price;
use craft\stripe\elements\Subscription;

/**
 * Event triggered just before removing a user from a user group in response to a subscription status change.
 *
 * You may prevent the user from being removed from the group by setting the event’s {@see isValid} property.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.7.x
 */
class RevokeGroupAssignmentEvent extends CancelableEvent
{
    /**
     * @var Subscription The subscription that caused the event to be triggered.
     */
    public Subscription $subscription;

    /**
     * @var Price The relevant Stripe price that contains assignment configuration.
     */
    public Price $price;

    /**
     * @var User The user whose permissions may be changing.
     */
    public User $user;

    /**
     * @var UserGroup Group the subscriber will be added to.
     */
    public UserGroup $group;
}