<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Query;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

/**
 * Filters products and prices by their status in Stripe.
 *
 * @since 2.0.0
 */
class StripeStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public static function isSelectableForCondition(ConditionInterface $condition): bool
    {
        return $condition instanceof ProductCondition || $condition instanceof PriceCondition;
    }

    #[Override]
    public function getLabel(): string
    {
        return t('Stripe Status', category: 'stripe');
    }

    #[Override]
    protected function options(): array
    {
        return [
            ['value' => Product::STRIPE_STATUS_ACTIVE, 'label' => t('Active', category: 'stripe')],
            ['value' => Product::STRIPE_STATUS_ARCHIVED, 'label' => t('Archived', category: 'stripe')],
        ];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $column = $elementQuery->elementType === Price::class ? 'stripe_pricedata.stripeStatus' : 'stripe_productdata.stripeStatus';

        Query::whereParam($query, $column, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product|Price $element */
        return $this->matchValue($element->stripeStatus);
    }
}
