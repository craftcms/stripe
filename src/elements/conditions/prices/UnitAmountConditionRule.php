<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\prices;

use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\stripe\elements\db\PriceQuery;
use craft\stripe\elements\Price;

/**
 * Class UnitAmountConditionRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class UnitAmountConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Unit Amount');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['unitAmount'];
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Price $element */
        return $this->matchValue($element->unitAmount);
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var PriceQuery $query */
        $query->unitAmount($this->paramValue());
    }
}
