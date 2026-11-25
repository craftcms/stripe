<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Query;
use CraftCms\Stripe\Elements\Price;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

/**
 * Filters prices by their primary currency.
 *
 * @since 2.0.0
 */
class CurrencyConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public static function isSelectableForCondition(ConditionInterface $condition): bool
    {
        return $condition instanceof PriceCondition;
    }

    #[Override]
    public function getLabel(): string
    {
        return t('Currency', category: 'stripe');
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        Query::whereParam($query, 'stripe_pricedata.primaryCurrency', $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Price $element */
        return $this->matchValue($element->primaryCurrency);
    }
}
