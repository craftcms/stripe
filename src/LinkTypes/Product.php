<?php

declare(strict_types=1);

namespace CraftCms\Stripe\LinkTypes;

use CraftCms\Cms\Field\LinkTypes\BaseElementLinkType;
use CraftCms\Stripe\Elements\Product as ProductElement;
use Override;

/**
 * Links to Stripe products.
 *
 * @since 1.0.0
 */
class Product extends BaseElementLinkType
{
    #[Override]
    protected static function elementType(): string
    {
        return ProductElement::class;
    }

    #[Override]
    public static function displayName(): string
    {
        return ProductElement::lowerDisplayName();
    }
}
