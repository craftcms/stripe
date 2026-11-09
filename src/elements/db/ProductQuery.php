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
use craft\elements\db\ElementQuery;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

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
    use PreparesProductQuery;

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

        $this->initProductQuery();
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
}
