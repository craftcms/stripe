<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Fields;

use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Queries\ProductQuery;
use Override;

use function CraftCms\Cms\t;

/**
 * Relates Stripe products.
 *
 * @since 1.0.0
 */
class Products extends BaseRelationField
{
    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Products', category: 'stripe');
    }

    #[Override]
    public static function icon(): string
    {
        return 'box-archive';
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a product', category: 'stripe');
    }

    public static function elementType(): string
    {
        return Product::class;
    }

    #[Override]
    public static function phpType(): string
    {
        return sprintf('\\%s|\\%s<\\%s>', ProductQuery::class, ElementCollection::class, Product::class);
    }
}
