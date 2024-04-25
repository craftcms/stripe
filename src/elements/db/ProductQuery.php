<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\db;

use Craft;
use craft\base\Element;
use craft\db\Connection;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use yii\db\Expression;

/**
 * Product query
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @method Product[]|array all($db = null)
 * @method Product|array|null one($db = null)
 * @method Product|array|null nth(int $n, Connection $db = null)
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

    /**
     * @var mixed only return products that match the resulting price query.
     */
    public mixed $hasPrice = null;

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
            $config['status'] = Element::STATUS_ENABLED;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * Narrows the query results based on the Stripe status
     */
    public function stripeStatus(mixed $value): self
    {
        $this->stripeStatus = $value;
        return $this;
    }

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
     * Narrows the query results to only products that have certain prices.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[PriceQuery|PriceQuery]] object | with prices that match the query.
     *
     * @param PriceQuery|array $value The property value
     * @return static self reference
     * @noinspection PhpUnused
     */
    public function hasPrice(mixed $value): static
    {
        $this->hasPrice = $value;
        return $this;
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
