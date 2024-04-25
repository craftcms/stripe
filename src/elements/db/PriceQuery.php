<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\db;

use craft\base\ElementInterface;
use craft\db\Connection;
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\enums\PriceType;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Price query
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @method Price[]|array all($db = null)
 * @method Price|array|null one($db = null)
 * @method Price|array|null nth(int $n, Connection $db = null)
 */
class PriceQuery extends ElementQuery
{
    /**
     * @var mixed The Stripe price ID(s) that the resulting prices must have.
     */
    public mixed $stripeId = null;

    /**
     * @var mixed
     */
    public mixed $stripeStatus = null;

    /**
     * @var mixed|null Type of the price one-time or recurring
     */
    public mixed $priceType = null;

    /**
     * @var mixed|null Price's main currency
     */
    public mixed $primaryCurrency = null;

    /**
     * @var mixed|null Price's currency
     */
    public mixed $currency = null;

    /**
     * @var mixed|null Stripe id of the product the price is associated with
     */
    public mixed $stripeProductId = null;

    /**
     * @var mixed The primary owner element ID(s) that the resulting addresses must belong to.
     * @used-by primaryOwner()
     * @used-by primaryOwnerId()
     */
    public mixed $primaryOwnerId = null;

    /**
     * @var mixed The owner element ID(s) that the resulting addresses must belong to.
     * @used-by owner()
     * @used-by ownerId()
     */
    public mixed $ownerId = null;

    /**
     * @var bool|null Whether the owner elements can be drafts.
     * @used-by allowOwnerDrafts()
     */
    public ?bool $allowOwnerDrafts = null;

    /**
     * @var bool|null Whether the owner elements can be revisions.
     * @used-by allowOwnerRevisions()
     */
    public ?bool $allowOwnerRevisions = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['stripe_pricedata.stripeId' => SORT_ASC];

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

    /**
     * Narrows the query results based on the Stripe status
     */
    public function stripeStatus(mixed $value): self
    {
        $this->stripeStatus = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Stripe price ID
     */
    public function stripeId(mixed $value): self
    {
        $this->stripeId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the price's type.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'one_time'` | that are one-time prices.
     * | `'recurring'` | that are recurring prices.
     *
     * ---
     *
     * ```twig
     * {# Fetch recurring prices #}
     * {% set {elements-var} = {twig-method}
     *   .priceType('recurring')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch recurring prices
     * ${elements-var} = {element-class}::find()
     *     ->priceType(PriceType::Recurring)
     *     ->all();
     * ```
     */
    public function priceType(mixed $value): self
    {
        if ($value instanceof PriceType) {
            $this->priceType = $value->value;
        } else {
            $this->priceType = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the primary currency.
     *
     * ---
     *
     * ```twig
     * {# Fetch prices where the primary currency is GBP #}
     * {% set {elements-var} = {twig-method}
     *   .primaryCurrency('GBP')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch prices where the primary currency is GBP
     * ${elements-var} = {element-class}::find()
     *     ->primaryCurrency('GBP')
     *     ->all();
     * ```
     */
    public function primaryCurrency(mixed $value): self
    {
        $this->primaryCurrency = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the currencies supported by the price.
     *
     * ---
     *
     * ```twig
     * {# Fetch prices where the currency is GBP #}
     * {% set {elements-var} = {twig-method}
     *   .currency('GBP')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch prices where the currency is GBP
     * ${elements-var} = {element-class}::find()
     *     ->currency('GBP')
     *     ->all();
     * ```
     */
    public function currency(mixed $value): self
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the product associated with the price.
     *
     * ---
     *
     * ```twig
     * {# Fetch prices where the product id is prod_abcdefghi #}
     * {% set {elements-var} = {twig-method}
     *   .stripeProductId('prod_abcdefghi')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch prices where the product id is prod_abcdefghi
     * ${elements-var} = {element-class}::find()
     *     ->stripeProductId('prod_abcdefghi')
     *     ->all();
     * ```
     */
    public function stripeProductId(mixed $value): self
    {
        $this->stripeProductId = $value;
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
                'stripe_pricedata.stripeStatus' => 'active',
            ],
            strtolower(Product::STATUS_STRIPE_ARCHIVED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripe_pricedata.stripeStatus' => 'archived',
            ],
            default => parent::statusCondition($status),
        };

        return $res;
    }

    /**
     * Narrows the query results based on the primary owner element of the addresses, per the owners’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses…
     * | - | -
     * | `1` | created for an element with an ID of 1.
     * | `'not 1'` | not created for an element with an ID of 1.
     * | `[1, 2]` | created for an element with an ID of 1 or 2.
     * | `['not', 1, 2]` | not created for an element with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch addresses created for an element with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .primaryOwnerId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch addresses created for an element with an ID of 1
     * ${elements-var} = {php-method}
     *     ->primaryOwnerId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     * @uses $primaryOwnerId
     */
    public function primaryOwnerId(mixed $value): static
    {
        $this->primaryOwnerId = $value;
        return $this;
    }

    /**
     * Sets the [[primaryOwnerId()]] and [[siteId()]] parameters based on a given element.
     *
     * ---
     *
     * ```twig
     * {# Fetch addresses created for this entry #}
     * {% set {elements-var} = {twig-method}
     *   .primaryOwner(myEntry)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch addresses created for this entry
     * ${elements-var} = {php-method}
     *     ->primaryOwner($myEntry)
     *     ->all();
     * ```
     *
     * @param ElementInterface $primaryOwner The primary owner element
     * @return static self reference
     * @uses $primaryOwnerId
     */
    public function primaryOwner(ElementInterface $primaryOwner): static
    {
        $this->primaryOwnerId = [$primaryOwner->id];
        $this->siteId = $primaryOwner->siteId;
        return $this;
    }

    /**
     * Sets the [[ownerId()]] parameter based on a given owner element.
     *
     * ---
     *
     * ```twig
     * {# Fetch addresses for the current user #}
     * {% set {elements-var} = {twig-method}
     *   .owner(currentUser)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch addresses created for the current user
     * ${elements-var} = {php-method}
     *     ->owner(Craft::$app->user->identity)
     *     ->all();
     * ```
     *
     * @param ElementInterface $owner The owner element
     * @return static self reference
     * @uses $ownerId
     */
    public function owner(ElementInterface $owner): static
    {
        $this->ownerId = [$owner->id];
        return $this;
    }

    /**
     * Narrows the query results based on the addresses’ owner elements, per their IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses…
     * | - | -
     * | `1` | created for an element with an ID of 1.
     * | `[1, 2]` | created for an element with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch addresses created for an element with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .ownerId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch addresses created for an element with an ID of 1
     * ${elements-var} = {php-method}
     *     ->ownerId(1)
     *     ->all();
     * ```
     *
     * @param int|int[]|null $value The property value
     * @return static self reference
     * @uses $ownerId
     */
    public function ownerId(array|int|null $value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the addresses’ owners are drafts.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses…
     * | - | -
     * | `true` | which can belong to a draft.
     * | `false` | which cannot belong to a draft.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @uses $allowOwnerDrafts
     */
    public function allowOwnerDrafts(?bool $value = true): static
    {
        $this->allowOwnerDrafts = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the addresses’ owners are revisions.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses…
     * | - | -
     * | `true` | which can belong to a revision.
     * | `false` | which cannot belong to a revision.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @uses $allowOwnerRevisions
     */
    public function allowOwnerRevisions(?bool $value = true): static
    {
        $this->allowOwnerRevisions = $value;
        return $this;
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
            'stripe_pricedata.priceType',
            'stripe_pricedata.primaryCurrency',
            'stripe_pricedata.currencies',
            'stripe_pricedata.productId as stripeProductId',
            'stripe_pricedata.data',
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

        if (isset($this->priceType)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.priceType', $this->priceType));
        }

        if (isset($this->primaryCurrency)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.primaryCurrency', $this->primaryCurrency));
        }

        if (isset($this->currency)) {
            $currency = \craft\stripe\helpers\Db::prepareForLikeSearch($this, 'currency');
            $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.currencies', $currency));
        }

        if (isset($this->stripeProductId)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_pricedata.productId', $this->stripeProductId));
        }

        return parent::beforePrepare();
    }

    /**
     * Normalizes the ownerId param to an array of IDs or null
     *
     * @param mixed $value
     * @return int[]|null
     * @throws InvalidArgumentException
     */
    private function normalizeOwnerId(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return [$value];
        }
        if (!is_array($value) || !ArrayHelper::isNumeric($value)) {
            throw new InvalidArgumentException();
        }
        return $value;
    }
}
