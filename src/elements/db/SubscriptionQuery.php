<?php

namespace craft\stripe\elements\db;

use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\stripe\elements\Subscription;

/**
 * Subscription query
 */
class SubscriptionQuery extends ElementQuery
{
    /**
     * @var mixed The Stripe subscription ID(s) that the resulting subscriptions must have.
     */
    public mixed $stripeId = null;

    /**
     * @var mixed
     */
    public mixed $stripeStatus = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['stripe_subscriptiondata.stripeId' => SORT_ASC];

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
     * Narrows the query results based on the Stripe subscription ID
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
     * | `'stripeScheduled'` | that are enabled, with a Scheduled Stripe Status.
     * | `'stripeCanceled'` | that are enabled, with a Canceled Stripe Status.
     * | `'disabled'` | that are disabled in Craft (Regardless of Stripe Status).
     * | `['live', 'stripeScheduled']` | that are live or with an Scheduled Stripe Status.
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
            strtolower(Subscription::STATUS_LIVE) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripe_subscriptiondata.stripeStatus' => 'active',
            ],
            strtolower(Subscription::STATUS_STRIPE_SCHEDULED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripe_subscriptiondata.stripeStatus' => 'scheduled',
            ],
            strtolower(Subscription::STATUS_STRIPE_CANCELED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripe_subscriptiondata.stripeStatus' => 'canceled',
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

        $subscriptionTable = 'stripe_subscriptions';
        $subscriptionDataTable = 'stripe_subscriptiondata';

        // join standard subscription element table that only contains the stripeId
        $this->joinElementTable($subscriptionTable);

        $subscriptionDataJoinTable = [$subscriptionDataTable => "{{%$subscriptionDataTable}}"];
        $this->query->innerJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");
        $this->subQuery->innerJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");

        $this->query->select([
            'stripe_subscriptions.stripeId',
            'stripe_subscriptiondata.stripeStatus',
            'stripe_subscriptiondata.data',
        ]);

        if (isset($this->stripeId)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_subscriptiondata.stripeId', $this->stripeId));
        }

        if (isset($this->stripeStatus)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_subscriptiondata.stripeStatus', $this->stripeStatus));
        }

        return parent::beforePrepare();
    }
}
