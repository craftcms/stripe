<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Stripe\Elements\Product;
use Stripe\Product as StripeProduct;

/**
 * Fired before a Stripe product is synced to its element. Set `$isValid` to `false` to skip it.
 *
 * @since 2.0.0
 */
class ProductSyncing
{
    use ValidatableEvent;

    public function __construct(
        public Product $element,
        public StripeProduct $source,
    ) {}
}
