<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use Override;

/**
 * The condition for Stripe price queries and element indexes.
 *
 * @since 2.0.0
 */
class PriceCondition extends ElementCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            StripeStatusConditionRule::class,
            PriceTypeConditionRule::class,
            CurrencyConditionRule::class,
            UnitAmountConditionRule::class,
        ]);
    }
}
