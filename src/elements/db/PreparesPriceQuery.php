<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\db;

use Closure;
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\helpers\Db;
use craft\stripe\elements\Product;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException as Craft6QueryAbortedException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

if (!class_exists(\CraftCms\Cms\Element\Queries\ElementQuery::class)) {
    /**
     * Builds price queries on Craft 5.
     *
     * @internal
     */
    trait PreparesPriceQuery
    {
        private function initPriceQuery(): void
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
                    'stripe_pricedata.stripeStatus' => Product::STRIPE_STATUS_ACTIVE,
                ],
                strtolower(Product::STATUS_STRIPE_ARCHIVED) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'stripe_pricedata.stripeStatus' => Product::STRIPE_STATUS_ARCHIVED,
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
            if (!parent::beforePrepare()) {
                return false;
            }

            if ($this->stripeId === []) {
                return false;
            }

            try {
                $this->primaryOwnerId = $this->normalizeOwnerId($this->primaryOwnerId);
            } catch (InvalidArgumentException) {
                throw new InvalidConfigException('Invalid primaryOwnerId param value');
            }

            try {
                $this->ownerId = $this->normalizeOwnerId($this->ownerId);
            } catch (InvalidArgumentException) {
                throw new InvalidConfigException('Invalid ownerId param value');
            }

            $priceTable = 'stripe_prices';
            $priceDataTable = 'stripe_pricedata';

            // join standard price element table that only contains the stripeId
            $this->joinElementTable($priceTable);

            $priceDataJoinTable = [$priceDataTable => "{{%$priceDataTable}}"];
            $this->query->innerJoin($priceDataJoinTable, "[[$priceDataTable.stripeId]] = [[$priceTable.stripeId]]");
            $this->subQuery->innerJoin($priceDataJoinTable, "[[$priceDataTable.stripeId]] = [[$priceTable.stripeId]]");

            $this->query->select([
                'stripe_prices.stripeId',
                'stripe_prices.primaryOwnerId',
                'stripe_pricedata.stripeStatus',
                'stripe_pricedata.data',
                'stripe_pricedata.currencies',
                'stripe_pricedata.unitAmount',
                'stripe_pricedata.type',
                'stripe_pricedata.productId as stripeProductId',
                'stripe_pricedata.primaryCurrency',

            ]);

            if (!empty($this->ownerId) || !empty($this->primaryOwnerId)) {
                // Join in the elements_owners table
                $ownersCondition = [
                    'and',
                    '[[elements_owners.elementId]] = [[elements.id]]',
                    $this->ownerId ? ['elements_owners.ownerId' => $this->ownerId] : '[[elements_owners.ownerId]] = [[stripe_prices.primaryOwnerId]]',
                ];

                $this->query
                    ->addSelect([
                        'elements_owners.ownerId',
                        'elements_owners.sortOrder',
                    ])
                    ->innerJoin(['elements_owners' => Table::ELEMENTS_OWNERS], $ownersCondition);
                $this->subQuery->innerJoin(['elements_owners' => Table::ELEMENTS_OWNERS], $ownersCondition);

                //$this->subQuery->andWhere(['addresses.fieldId' => $this->fieldId]);

                if ($this->primaryOwnerId) {
                    $this->subQuery->andWhere(['stripe_prices.primaryOwnerId' => $this->primaryOwnerId]);
                }

                // Ignore revision/draft blocks by default
                $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->primaryOwnerId || $this->ownerId);
                $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->primaryOwnerId || $this->ownerId);

                if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
                    $this->subQuery->innerJoin(
                        ['owners' => Table::ELEMENTS],
                        $this->ownerId ? '[[owners.id]] = [[elements_owners.ownerId]]' : '[[owners.id]] = [[stripe_prices.primaryOwnerId]]'
                    );

                    if (!$allowOwnerDrafts) {
                        $this->subQuery->andWhere(['owners.draftId' => null]);
                    }

                    if (!$allowOwnerRevisions) {
                        $this->subQuery->andWhere(['owners.revisionId' => null]);
                    }
                }

                $this->defaultOrderBy = ['elements_owners.sortOrder' => SORT_ASC];
            } elseif (isset($this->primaryOwnerId) || isset($this->ownerId)) {
                $this->subQuery->andWhere(['stripe_prices.primaryOwnerId' => $this->primaryOwnerId ?? $this->ownerId]);
            }

            if (isset($this->stripeId)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.stripeId', $this->stripeId));
            }

            if (isset($this->stripeStatus)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.stripeStatus', $this->stripeStatus));
            }

            if (isset($this->type)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.type', $this->type));
            }

            if (isset($this->primaryCurrency)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.primaryCurrency', $this->primaryCurrency));
            }

            if (isset($this->currency)) {
                $currency = \craft\stripe\helpers\Db::prepareForLikeSearch($this, 'currency');
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.currencies', $currency));
            }

            if (isset($this->unitAmount)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.unitAmount', $this->unitAmount));
            }

            if (isset($this->stripeProductId)) {
                $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.productId', $this->stripeProductId));
            }

            return parent::beforePrepare();
        }
    }
} else {
    /**
     * Builds price queries on Craft 6, where element queries are a single Laravel query and `beforePrepare()` is no longer called.
     *
     * @internal
     */
    trait PreparesPriceQuery
    {
        protected string $table = 'stripe_prices';

        private function initPriceQuery(): void
        {
            $this->query
            ->join('stripe_pricedata', 'stripe_pricedata.stripeId', '=', 'stripe_prices.stripeId')
            ->addSelect([
                'stripe_prices.stripeId',
                'stripe_prices.primaryOwnerId',
                'stripe_pricedata.stripeStatus',
                'stripe_pricedata.data',
                'stripe_pricedata.currencies',
                'stripe_pricedata.unitAmount',
                'stripe_pricedata.type',
                'stripe_pricedata.productId as stripeProductId',
                'stripe_pricedata.primaryCurrency',
            ]);

            $this->beforeQuery(static function(PriceQuery $query): void {
                if ($query->stripeId === []) {
                    throw new Craft6QueryAbortedException();
                }

                try {
                    $query->primaryOwnerId = $query->normalizeOwnerId($query->primaryOwnerId);
                } catch (InvalidArgumentException) {
                    throw new InvalidConfigException('Invalid primaryOwnerId param value');
                }

                try {
                    $query->ownerId = $query->normalizeOwnerId($query->ownerId);
                } catch (InvalidArgumentException) {
                    throw new InvalidConfigException('Invalid ownerId param value');
                }

                $query->applyOwnerParams();

                foreach ([
                'stripeId' => 'stripe_pricedata.stripeId',
                'stripeStatus' => 'stripe_pricedata.stripeStatus',
                'type' => 'stripe_pricedata.type',
                'primaryCurrency' => 'stripe_pricedata.primaryCurrency',
                'stripeProductId' => 'stripe_pricedata.productId',
            ] as $param => $column) {
                    if (isset($query->$param)) {
                        $query->query->whereParam($column, $query->$param);
                    }
                }

                if (isset($query->currency)) {
                    $query->query->whereParam('stripe_pricedata.currencies', \craft\stripe\helpers\Db::prepareForLikeSearch($query, 'currency'));
                }

                if (isset($query->unitAmount)) {
                    $query->query->whereNumericParam('stripe_pricedata.unitAmount', $query->unitAmount, columnType: null);
                }
            });
        }

        private function applyOwnerParams(): void
        {
            if (empty($this->ownerId) && empty($this->primaryOwnerId)) {
                return;
            }

            $this->query
            ->addSelect(['elements_owners.ownerId', 'elements_owners.sortOrder'])
            ->join('elements_owners', function(JoinClause $join) {
                $join->on('elements_owners.elementId', '=', 'elements.id');

                if ($this->ownerId) {
                    $join->whereIn('elements_owners.ownerId', $this->ownerId);
                } else {
                    $join->on('elements_owners.ownerId', '=', 'stripe_prices.primaryOwnerId');
                }
            });

            if ($this->primaryOwnerId) {
                $this->query->whereIn('stripe_prices.primaryOwnerId', $this->primaryOwnerId);
            }

            // Ignore revision/draft blocks by default
            $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->primaryOwnerId || $this->ownerId);
            $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->primaryOwnerId || $this->ownerId);

            if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
                $this->query->join('elements as owners', 'owners.id', '=', $this->ownerId ? 'elements_owners.ownerId' : 'stripe_prices.primaryOwnerId');

                if (!$allowOwnerDrafts) {
                    $this->query->whereNull('owners.draftId');
                }

                if (!$allowOwnerRevisions) {
                    $this->query->whereNull('owners.revisionId');
                }
            }

            $this->defaultOrderBy = ['elements_owners.sortOrder' => SORT_ASC];
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
            ->where('stripe_pricedata.stripeStatus', $stripeStatus);
        }
    }
}
