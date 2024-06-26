<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\products;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\stripe\elements\db\ProductQuery;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

/**
 * Class StripeStatusConditionRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class StripeStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Stripe Status');
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        return [
            ['value' => Product::STRIPE_STATUS_ACTIVE, 'label' => StringHelper::titleize(Product::STRIPE_STATUS_ACTIVE)],
            ['value' => Product::STRIPE_STATUS_ARCHIVED, 'label' => StringHelper::titleize(Product::STRIPE_STATUS_ARCHIVED)],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['stripeStatus'];
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product|Price $element */
        return $this->matchValue($element->stripeStatus);
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ProductQuery $query */
        $query->stripeStatus($this->paramValue());
    }
}
