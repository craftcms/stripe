<?php

namespace craft\stripe\elements\conditions\products;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\elements\Product;

class StripeStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Stripe Status');
    }

    /**
     * @inheritDoc
     */
    protected function options(): array
    {
        return [
            ['value' => Product::SHOPIFY_STATUS_ACTIVE, 'label' => StringHelper::titleize(Product::SHOPIFY_STATUS_ACTIVE)],
            ['value' => Product::SHOPIFY_STATUS_ARCHIVED, 'label' => StringHelper::titleize(Product::SHOPIFY_STATUS_ARCHIVED)],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['stripeStatus'];
    }

    /**
     * @inheritDoc
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->stripeStatus);
    }

    /**
     * @inheritDoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ProductQuery $query */
        $query->stripeStatus($this->paramValue());
    }
}
