<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Conditions;

use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Queries\ProductQuery;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

/**
 * Filters products by the types of their prices.
 *
 * @since 2.0.0
 */
class ProductPriceTypeConditionRule extends PriceTypeConditionRule
{
    #[Override]
    public static function isSelectableForCondition(ConditionInterface $condition): bool
    {
        return $condition instanceof ProductCondition;
    }

    #[Override]
    public function getLabel(): string
    {
        return t('Price Type', category: 'stripe');
    }

    #[Override]
    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        ProductQuery::applyHasPrice($query, ['type' => $this->paramValue(), 'status' => null]);
    }

    #[Override]
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $element->getPrices()->contains(fn (Price $price) => $this->matchValue($price->type));
    }
}
