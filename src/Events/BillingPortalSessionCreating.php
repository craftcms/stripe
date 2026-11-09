<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

/**
 * Fired before a Stripe billing portal session is created. Change `$params` to change what is sent to Stripe.
 *
 * @since 3.0.0
 */
class BillingPortalSessionCreating
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public array $params,
    ) {}
}
