<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Stripe\Elements\Subscription;
use Stripe\Subscription as StripeSubscription;

/**
 * Fired after a Stripe subscription is synced to its element.
 *
 * @since 2.0.0
 */
class SubscriptionSynced
{
    public function __construct(
        public Subscription $element,
        public StripeSubscription $source,
    ) {}
}
