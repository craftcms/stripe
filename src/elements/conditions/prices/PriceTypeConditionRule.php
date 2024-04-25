<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\prices;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\stripe\elements\db\PriceQuery;
use craft\stripe\elements\Price;
use craft\stripe\enums\PriceType;

/**
 * Class PriceTypeConditionRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PriceTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Type');
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        return array_map(function($option) {
            return [
                'value' => $option,
                'label' => StringHelper::humanize($option),
            ];
        }, [
            PriceType::OneTime->value,
            PriceType::Recurring->value,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['type'];
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Price $element */
        return $this->matchValue($element->priceType);
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var PriceQuery $query */
        $query->priceType($this->paramValue());
    }
}
