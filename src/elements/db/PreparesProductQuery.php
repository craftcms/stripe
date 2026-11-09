<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\db;

use Closure;
use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\helpers\Db;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException as Craft6QueryAbortedException;
use Illuminate\Database\Query\Builder;
use yii\db\Expression;

if (!class_exists(\CraftCms\Cms\Element\Queries\ElementQuery::class)) {
    /**
     * Builds product queries on Craft 5.
     *
     * @internal
     */
    trait PreparesProductQuery
    {
        private function initProductQuery(): void
        {
        }

        /**
         * @inheritdoc
         */
        protected function statusCondition(string $status): mixed
        {
            $res = match ($status) {
                strtolower(Product::STATUS_LIVE) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'stripe_productdata.stripeStatus' => Product::STRIPE_STATUS_ACTIVE,
                ],
                strtolower(Product::STATUS_STRIPE_ARCHIVED) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'stripe_productdata.stripeStatus' => Product::STRIPE_STATUS_ARCHIVED,
                ],
                default => parent::statusCondition($status),
            };

            return $res;
        }
        /**
         * @inheritdoc
         * @throws QueryAbortedException
         */
        protected function beforePrepare(): bool
        {
            if ($this->stripeId === []) {
                return false;
            }

            $productTable = 'stripe_products';
            $productDataTable = 'stripe_productdata';

            // join standard product element table that only contains the stripeId
            $this->joinElementTable($productTable);

            $productDataJoinTable = [$productDataTable => "{{%$productDataTable}}"];
            $this->query->innerJoin($productDataJoinTable, "[[$productDataTable.stripeId]] = [[$productTable.stripeId]]");
            $this->subQuery->innerJoin($productDataJoinTable, "[[$productDataTable.stripeId]] = [[$productTable.stripeId]]");

            $this->query->select([
                'stripe_products.stripeId',
                'stripe_productdata.stripeStatus',
                'stripe_productdata.data',
            ]);

            if (isset($this->stripeId)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_productdata.stripeId', $this->stripeId));
            }

            if (isset($this->stripeStatus)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_productdata.stripeStatus', $this->stripeStatus));
            }

            $this->_applyHasPriceParam();

            return parent::beforePrepare();
        }
        /**
         * Applies the hasPrice query condition
         */
        private function _applyHasPriceParam(): void
        {
            if ($this->hasPrice === null) {
                return;
            }

            if ($this->hasPrice instanceof PriceQuery) {
                $priceQuery = $this->hasPrice;
            } elseif (is_array($this->hasPrice)) {
                $query = Price::find();
                $priceQuery = Craft::configure($query, $this->hasPrice);
            } else {
                throw new QueryAbortedException('Invalid param used. ProductQuery::hasPrice param only expects a price query or price query config.');
            }

            $priceQuery->limit = null;
            $priceQuery->select('stripe_prices.primaryOwnerId');

            // Remove any blank product IDs (if any)
            $priceQuery->andWhere(['not', ['stripe_prices.primaryOwnerId' => null]]);

            // Uses exists subquery for speed to check for the variant
            $existsQuery = (new Query())
                ->from(['existssub' => $priceQuery])
                ->where(['existssub.primaryOwnerId' => new Expression('[[stripe_products.id]]')]);
            $this->subQuery->andWhere(['exists', $existsQuery]);
        }
    }
} else {
    /**
     * Builds product queries on Craft 6, where element queries are a single Laravel query and `beforePrepare()` is no longer called.
     *
     * @internal
     */
    trait PreparesProductQuery
    {
        protected string $table = 'stripe_products';

        private function initProductQuery(): void
        {
            $this->query
            ->join('stripe_productdata', 'stripe_productdata.stripeId', '=', 'stripe_products.stripeId')
            ->addSelect([
                'stripe_products.stripeId',
                'stripe_productdata.stripeStatus',
                'stripe_productdata.data',
            ]);

            $this->beforeQuery(static function(ProductQuery $query): void {
                if ($query->stripeId === []) {
                    throw new Craft6QueryAbortedException();
                }

                if (isset($query->stripeId)) {
                    $query->query->whereParam('stripe_productdata.stripeId', $query->stripeId);
                }

                if (isset($query->stripeStatus)) {
                    $query->query->whereParam('stripe_productdata.stripeStatus', $query->stripeStatus);
                }

                $query->applyHasPriceParam();
            });
        }

        /**
         * @inheritdoc
         */
        protected function statusCondition(string $status): Closure
        {
            // Craft 6 doesn’t lowercase the status
            $stripeStatus = match (strtolower($status)) {
                strtolower(Product::STATUS_LIVE) => Product::STRIPE_STATUS_ACTIVE,
            strtolower(Product::STATUS_STRIPE_ARCHIVED) => Product::STRIPE_STATUS_ARCHIVED,
            default => null,
            };

            if ($stripeStatus === null) {
                return parent::statusCondition($status);
            }

            return fn(Builder $query) => $query
            ->whereBool('elements.enabled', true)
            ->whereBool('elements_sites.enabled', true)
            ->where('stripe_productdata.stripeStatus', $stripeStatus);
        }

        /**
         * Applies the hasPrice query condition
         */
        private function applyHasPriceParam(): void
        {
            if ($this->hasPrice === null) {
                return;
            }

            if ($this->hasPrice instanceof PriceQuery) {
                $priceQuery = $this->hasPrice;
            } elseif (is_array($this->hasPrice)) {
                $priceQuery = Craft::configure(Price::find(), $this->hasPrice);
            } else {
                throw new Craft6QueryAbortedException('Invalid param used. ProductQuery::hasPrice param only expects a price query or price query config.');
            }

            $priceQuery->limit(null);
            $priceQuery->applyBeforeQueryCallbacks();
            $priceQuery->query->whereNotNull('stripe_prices.primaryOwnerId');
            $priceQuery->query->select('stripe_prices.primaryOwnerId');

            $this->query->whereIn('stripe_products.id', $priceQuery->query);
        }
    }
}
