<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\products;

use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\stripe\elements\conditions\prices\PriceTypeConditionRule;
use craft\stripe\elements\db\ProductQuery;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

/**
 * Class ProductPriceTypeConditionRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ProductPriceTypeConditionRule extends PriceTypeConditionRule
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return \Craft::t('stripe', 'Price Type');
    }


    /**
     * @param ElementQueryInterface $query
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $priceQuery = Price::find();
        $priceQuery->select(['stripe_prices.primaryOwnerId as id']);
        $priceQuery->priceType($this->paramValue());

        /** @var ProductQuery $query */
        $query->andWhere(['elements.id' => $priceQuery]);
    }

    /**
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        foreach ($element->getPrices() as $price) {
            /** @var Price $price */
            if ($this->matchValue($price->priceType)) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
