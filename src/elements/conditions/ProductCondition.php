<?php

namespace craft\stripe\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Product condition
 */
class ProductCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
