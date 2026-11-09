<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use Stripe\Event;

/**
 * Fired for every verified Stripe webhook event, after the plugin has handled it.
 *
 * @since 3.0.0
 */
class StripeEventReceived
{
    public function __construct(
        public Event $stripeEvent,
    ) {}
}
