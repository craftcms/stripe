<?php

namespace craft\stripe\elements\db;

use Craft;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use yii\db\Expression;

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
    protected array $defaultOrderBy = ['stripestore_subscriptiondata.stripeId' => SORT_ASC];

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

    public function user(User|int|string $value): static
    {
        if (is_numeric($value)) {
            $user = Craft::$app->getUsers()->getUserById($value);
        } elseif (is_string($value)) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($value);
        } elseif ($value instanceof User) {
            $user = $value;
        }

        if (!empty($user)) {
            // get stripe customers for this user
            $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($user->email);
            $customerIds = "'" . implode("','", array_keys($customers)) . "'";

            $db = Craft::$app->getDb();

            if ($db->getIsPgsql()) {
                // TODO: TEST ME!!!!
                $this->where(new Expression(sprintf('data::customer" IN (%s)', $customerIds)));
            } else {
                $this->where(new Expression(sprintf('data->"$.customer" IN (%s)', $customerIds)));
            }
        }

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
                'stripestore_subscriptiondata.stripeStatus' => 'active',
            ],
            strtolower(Subscription::STATUS_STRIPE_SCHEDULED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripestore_subscriptiondata.stripeStatus' => 'scheduled',
            ],
            strtolower(Subscription::STATUS_STRIPE_CANCELED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'stripestore_subscriptiondata.stripeStatus' => 'canceled',
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

        $subscriptionTable = 'stripestore_subscriptions';
        $subscriptionDataTable = 'stripestore_subscriptiondata';

        // join standard subscription element table that only contains the stripeId
        $this->joinElementTable($subscriptionTable);

        $subscriptionDataJoinTable = [$subscriptionDataTable => "{{%$subscriptionDataTable}}"];
        $this->query->innerJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");
        $this->subQuery->innerJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");

        $this->query->select([
            'stripestore_subscriptions.stripeId',
            'stripestore_subscriptiondata.stripeStatus',
            'stripestore_subscriptiondata.data',
        ]);

        if (isset($this->stripeId)) {
            $this->subQuery->andWhere(Db::parseParam('stripestore_subscriptiondata.stripeId', $this->stripeId));
        }

        if (isset($this->stripeStatus)) {
            $this->subQuery->andWhere(Db::parseParam('stripestore_subscriptiondata.stripeStatus', $this->stripeStatus));
        }

        return parent::beforePrepare();
    }
}
