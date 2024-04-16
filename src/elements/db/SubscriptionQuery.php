<?php

namespace craft\stripe\elements\db;

use Craft;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;
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

    /**
     * Narrows the query results based on the User related to this {elements}.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | User with id of `1`.
     * | `'test@test.com'` | User with email address of `test@test.com`.
     * | `$user` | User element.
     *
     * ---
     *
     * ```twig
     * {# Fetch subscriptions for userId 1 #}
     * {% set {elements-var} = {twig-method}
     *   .user(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch subscriptions for userId 1
     * ${elements-var} = {element-class}::find()
     *     ->user(1)
     *     ->all();
     * ```
     */
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
            $qb = Craft::$app->getDb()->getQueryBuilder();
            $this->where([
                'in',
                $qb->jsonExtract("[[stripestore_subscriptiondata.data]]", ["customer"]),
                array_keys($customers)]
            );
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

        $customerDataJoinTable = ['stripestore_customerdata' => '{{%stripestore_customerdata}}'];
        $qb = Craft::$app->getDb()->getQueryBuilder();

        $this->query->leftJoin(
            $customerDataJoinTable,
            "[[stripestore_customerdata.stripeId]] = ".$qb->jsonExtract("[[$subscriptionDataTable.data]]", ["customer"])
        );
        $this->subQuery->leftJoin(
            $customerDataJoinTable,
            "[[stripestore_customerdata.stripeId]] = ".$qb->jsonExtract("[[$subscriptionDataTable.data]]", ["customer"])
        );

        $this->query->select([
            'stripestore_subscriptions.stripeId',
            'stripestore_subscriptiondata.stripeStatus',
            'stripestore_subscriptiondata.data',
            'stripestore_customerdata.email AS customerEmail',
            'stripestore_customerdata.data AS customerData',
        ]);

        if (isset($this->stripeId)) {
            $this->subQuery->andWhere(Db::parseParam('stripestore_subscriptiondata.stripeId', $this->stripeId));
        }

        if (isset($this->stripeStatus)) {
            $this->subQuery->andWhere(Db::parseParam('stripestore_subscriptiondata.stripeStatus', $this->stripeStatus));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    public function populate($rows): array
    {
        foreach ($rows as &$row) {
            if (isset($row['customerData'])) {
                $model = new Customer();
                $model->setAttributes([
                    'email' => $row['customerEmail'],
                    'data' => $row['customerData'],
                ], false);

                $row['customer'] = $model;
            }

            unset($row['customerEmail']);
            unset($row['customerData']);
        }

        return parent::populate($rows);
    }
}
