<?php

namespace craft\stripe\elements\db;

use Craft;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\stripe\elements\Product;

/**
 * Product query
 */
class ProductQuery extends ElementQuery
{
    /**
     * @var mixed The Stripe product ID(s) that the resulting products must have.
     */
    public mixed $stripeId = null;

    /**
     * @var mixed
     */
    public mixed $stripeStatus = null;

//    public mixed $handle = null;
//    public mixed $productType = null;
//    public mixed $publishedScope = null;
//    public mixed $tags = null;
//    public mixed $vendor = null;
//    public mixed $images = null;
//    public mixed $options = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['stripe_productdata.stripeId' => SORT_ASC];

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'enabled';
        }

        parent::__construct($elementType, $config);
    }

//    /**
//     * Narrows the query results based on the Stripe product type
//     */
//    public function productType(mixed $value): self
//    {
//        $this->productType = $value;
//        return $this;
//    }

//    /**
//     * Narrows the query results based on the Stripe product type
//     */
//    public function publishedScope(mixed $value): self
//    {
//        $this->publishedScope = $value;
//        return $this;
//    }

    /**
     * Narrows the query results based on the Stripe status
     */
    public function stripeStatus(mixed $value): self
    {
        $this->stripeStatus = $value;
        return $this;
    }

//    /**
//     * Narrows the query results based on the Stripe product handle
//     */
//    public function handle(mixed $value): self
//    {
//        $this->handle = $value;
//        return $this;
//    }

//    /**
//     * Narrows the query results based on the Stripe product vendor
//     */
//    public function vendor(mixed $value): self
//    {
//        $this->vendor = $value;
//        return $this;
//    }

//    /**
//     * Narrows the query results based on the Stripe product tags
//     */
//    public function tags(mixed $value): self
//    {
//        $this->tags = $value;
//        return $this;
//    }

    /**
     * Narrows the query results based on the Stripe product ID
     */
    public function stripeId(mixed $value): self
    {
        $this->stripeId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'live'` _(default)_ | that are live (enabled in Craft, with an Active Stripe Status).
     * | `'stripeArchived'` | that are enabled, with an Archived Stripe Status.
     * | `'disabled'` | that are disabled in Craft (Regardless of Stripe Status).
     * | `['live', 'stripeArchived']` | that are live or with an Archived Stripe Status.
     *
     * ---
     *
     * ```twig
     * {# Fetch disabled {elements} #}
     * {% set {elements-var} = {twig-method}
     *   .status('disabled')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch disabled {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('disabled')
     *     ->all();
     * ```
     */
    public function status(array|string|null $value): static
    {
        parent::status($value);
        return $this;
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
                'stripe_productdata.stripeStatus' => 'active',
            ],
            strtolower(Product::STATUS_STRIPE_ARCHIVED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripe_productdata.stripeStatus' => 'archived',
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

        //$t = $this->query->getRawSql();
        //$t1 = $this->subQuery->getRawSql();


        if (isset($this->stripeId)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.stripeId', $this->stripeId));
        }
//
//        if (isset($this->productType)) {
//            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.productType', $this->productType));
//        }
//
//        if (isset($this->publishedScope)) {
//            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.publishedScope', $this->publishedScope));
//        }
//
        if (isset($this->stripeStatus)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.stripeStatus', $this->stripeStatus));
        }
//
//        if (isset($this->handle)) {
//            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.handle', $this->handle));
//        }
//
//        if (isset($this->vendor)) {
//            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.vendor', $this->vendor));
//        }
//
//        if (isset($this->tags)) {
//            $this->subQuery->andWhere(Db::parseParam('stripe_productdata.tags', $this->tags));
//        }

        return parent::beforePrepare();
    }
}
