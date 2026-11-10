<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Stripe\Elements\Price;
use Stripe\Price as StripePrice;

/**
 * Fired after a Stripe price is synced to its element.
 *
 * @since 2.0.0
 */
class PriceSynced
{
    public function __construct(
        public Price $element,
        public StripePrice $source,
    ) {}
}
