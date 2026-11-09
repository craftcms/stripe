<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Stripe\Elements\Subscription;
use Stripe\Subscription as StripeSubscription;

/**
 * Fired before a Stripe subscription is synced to its element. Set `$isValid` to `false` to skip it.
 *
 * @since 2.0.0
 */
class SubscriptionSyncing
{
    use ValidatableEvent;

    public function __construct(
        public Subscription $element,
        public StripeSubscription $source,
    ) {}
}
