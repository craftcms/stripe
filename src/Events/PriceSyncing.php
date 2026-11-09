<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Stripe\Elements\Price;
use Stripe\Price as StripePrice;

/**
 * Fired before a Stripe price is synced to its element. Set `$isValid` to `false` to skip it.
 *
 * @since 3.0.0
 */
class PriceSyncing
{
    use ValidatableEvent;

    public function __construct(
        public Price $element,
        public StripePrice $source,
    ) {}
}
