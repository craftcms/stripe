<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Stripe\Elements\Product;
use Stripe\Product as StripeProduct;

/**
 * Fired after a Stripe product is synced to its element.
 *
 * @since 3.0.0
 */
class ProductSynced
{
    public function __construct(
        public Product $element,
        public StripeProduct $source,
    ) {}
}
