<?php

namespace craft\stripe\elements\db;

use Craft;
use craft\base\Element;
use craft\db\QueryParam;
use craft\db\Table as CraftTable;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use craft\stripe\db\Table;
use craft\stripe\elements\Price;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;
use yii\db\Expression;
use yii\db\QueryBuilder;

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
     * @var mixed The user id of the subscriber
     */
    public mixed $userId = null;

    /**
     * @var mixed The user id of the subscriber
     */
    public mixed $userEmail = null;

    /**
     * @var mixed The Stripe Customer Id of the subscriber
     */
    public mixed $customerId = null;

    /**
     * @var mixed The subscription plan id
     * @deprecated $priceId should be used instead
     */
    public mixed $planId = null;

    /**
     * @var mixed The subscription price id
     */
    public mixed $priceId = null;

    /**
     * @var mixed The subscription price Stripe id
     */
    public mixed $stripePriceId = null;

    /**
     * @var mixed The id of the latest invoice that the subscription must be a part of.
     */
    public mixed $latestInvoiceId = null;

    /**
     * @var mixed The reference for subscription
     * @deprecated $stripeId should be used instead
     */
    public mixed $reference = null;

    /**
     * @var bool|null Whether the subscription is currently on trial.
     */
    public ?bool $onTrial = null;

    /**
     * @var mixed Time of next payment for the subscription
     */
    public mixed $nextPaymentDate = null;

    /**
     * @var bool|null Whether the subscription is canceled
     */
    public ?bool $isCanceled = null;

    /**
     * @var bool|null Whether the subscription is suspended
     */
    public ?bool $isSuspended = null;

    /**
     * @var mixed The date the subscription ceased to be active
     */
    public mixed $dateSuspended = null;

    /**
     * @var bool|null Whether the subscription has started
     */
    public ?bool $hasStarted = null;

    /**
     * @var mixed The time the subscription was canceled
     */
    public mixed $dateCanceled = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['stripe_subscriptions.stripeId' => SORT_ASC];

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
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'user':
                $this->user($value);
                break;
            case 'plan':
                $this->plan($value);
                break;
            case 'price':
                $this->price($value);
                break;
            default:
                parent::__set($name, $value);
        }
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
     * | `['live', 'stripeScheduled']` | that are live or with a Scheduled Stripe Status.
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
            $this->userEmail = $user->email;
        } elseif (is_string($value)) {
            $this->userEmail = $value;
        } elseif ($value instanceof User) {
            $this->userEmail = $value->email;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a user account with an ID of 1.
     * | `[1, 2]` | for user accounts with an ID of 1 or 2.
     * | `['not', 1, 2]` | for user accounts not with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .userId(currentUser.id)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's subscriptions
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->userId($user->id)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function userId(mixed $value): SubscriptionQuery
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts’ email.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'test@test.com'` | for a user account with an email of test@test.com.
     * | `['test@test.com', 'test2@test.com']` | for user accounts with an email of test@test.com or test2@test.com.
     * | `['not', 'test@test.com', 'test2@test.com']` | for user accounts not with an email of test@test.com or test2@test.com.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .userEmail(currentUser.email)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's subscriptions
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->userEmail($user->email)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function userEmail(mixed $value): SubscriptionQuery
    {
        $this->userEmail = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Stripe Customer Id related to this {elements}.
     *
     * ```twig
     * {# Fetch subscriptions for customer cus_Ab12Cd34Ef56Gh #}
     * {% set {elements-var} = {twig-method}
     *   .customerId('cus_Ab12Cd34Ef56Gh')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch subscriptions for customer cus_Ab12Cd34Ef56Gh
     * ${elements-var} = {element-class}::find()
     *     ->customerId('cus_Ab12Cd34Ef56Gh')
     *     ->all();
     * ```
     */
    public function customerId(mixed $value): static
    {
        $this->customerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscription price (formerly plan).
     *
     * @param mixed $value
     * @return static
     * @deprecated price() should be used instead
     */
    public function plan(mixed $value): SubscriptionQuery
    {
        if ($value instanceof Price) {
            $this->stripePriceId = $value->stripeId;
        } elseif ($value !== null) {
            if (is_numeric($value)) {
                $this->stripePriceId = (new Query())
                    ->select(['stripeId'])
                    ->from([Table::PRICES])
                    ->where(Db::parseParam('id', $value))
                    ->column();
            } else {
                $this->stripePriceId = $value;
            }
        } else {
            $this->stripePriceId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscription price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a price with an ID of 1.
     * | `[1, 2]` | for prices with an ID of 1 or 2.
     * | a [[Price|Price]] object | for a price represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch subscriptions for a price with an id of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .price(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch subscriptions for a price with an id of 1
     * ${elements-var} = {php-method}
     *     ->price(1)
     *     ->all();
     * ```
     *
     * @param mixed $value
     * @return static
     */
    public function price(mixed $value): SubscriptionQuery
    {
        if ($value instanceof Price) {
            $this->stripePriceId = $value->stripeId;
        } elseif ($value !== null) {
            if (is_numeric($value)) {
                $this->stripePriceId = (new Query())
                    ->select(['stripeId'])
                    ->from([Table::PRICES])
                    ->where(Db::parseParam('id', $value))
                    ->column();
            } else {
                $this->stripePriceId = $value;
            }
        } else {
            $this->stripePriceId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscription price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'price_1234'` | for a price with a Stripe ID of 'price_1234'.
     * | `['price_1234', 'price_5678']` | for prices with a Stripe ID of 'price_1234' or 'price_5678'.
     * | a [[Price|Price]] object | for a price represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch subscriptions for a price with Stripe id of 'price_1234' #}
     * {% set {elements-var} = {twig-method}
     *   .price('price_1234')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch subscriptions for a price with Stripe id of 'price_1234'
     * ${elements-var} = {php-method}
     *     ->price('price_1234')
     *     ->all();
     * ```
     *
     * @param mixed $value
     * @return static
     */
    public function stripePrice(mixed $value): SubscriptionQuery
    {
        if ($value instanceof Price) {
            $this->stripePriceId = $value->stripeId;
        } elseif ($value !== null) {
            $this->stripePriceId = $value;
        } else {
            $this->stripePriceId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscription prices’ IDs.
     *
     * @param mixed $value The property value
     * @deprecated priceId() should be used instead
     * @return static
     */
    public function planId(mixed $value): SubscriptionQuery
    {
        $this->stripePriceId = (new Query())
            ->select(['stripeId'])
            ->from([Table::PRICES])
            ->where(Db::parseParam('id', $value))
            ->column();
        return $this;
    }

    /**
     * Narrows the query results based on the subscription prices’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a plan with a Stripe ID of 1.
     * | `[1, 2]` | for plans with a Stripe ID of 1 or 2.
     * | `['not', 1, 2]` | for plans not with a Stipe ID of 1 or 2.
     *
     * @param mixed $value The property value
     * @return static
     */
    public function priceId(mixed $value): SubscriptionQuery
    {
        $this->stripePriceId = (new Query())
            ->select(['stripeId'])
            ->from([Table::PRICES])
            ->where(Db::parseParam('id', $value))
            ->column();
        return $this;
    }

    /**
     * Narrows the query results based on the subscription prices’ Stripe IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'price_1234'` | for a plan with a Stripe ID of 'price_1234'.
     * | `['price_1234', 'price_5678']` | for plans with a Stripe ID of 'price_1234' or 'price_5678'.
     * | `['not', 'price_1234', 'price_5678']` | for plans not with a Stipe ID of 'price_1234' or 'price_5678'.
     *
     * @param mixed $value The property value
     * @return static
     */
    public function stripePriceId(mixed $value): SubscriptionQuery
    {
        $this->stripePriceId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the latest invoice, per its ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with an order with an ID of 1.
     * | `'not 1'` | not with an order with an ID of 1.
     * | `[1, 2]` | with an order with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with an order with an ID of 1 or 2.
     *
     * @param mixed $value The property value
     * @return static
     */
    public function latestInvoiceId(mixed $value): SubscriptionQuery
    {
        $this->latestInvoiceId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the reference.
     *
     * @param mixed $value The property value
     * @deprecated priceId() should be used instead
     * @return static
     */
    public function reference(mixed $value): SubscriptionQuery
    {
        $this->stripePriceId = (new Query())
            ->select(['stripeId'])
            ->from([Table::PRICES])
            ->where(Db::parseParam('id', $value))
            ->column();
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are on trial.
     *
     * ---
     *
     * ```twig
     * {# Fetch trialed subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .onTrial()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch trialed subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isPaid()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static
     */
    public function onTrial(?bool $value = true): SubscriptionQuery
    {
        $this->onTrial = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ next payment dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | with a next payment on or after 2018-04-01.
     * | `'< 2018-05-01'` | with a next payment before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | with a next payment between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a payment due soon #}
     * {% set aWeekFromNow = date('+7 days')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *   .nextPaymentDate("< #{aWeekFromNow}")
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a payment due soon
     * $aWeekFromNow = new \DateTime('+7 days')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->nextPaymentDate("< {$aWeekFromNow}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static
     */
    public function nextPaymentDate(mixed $value): SubscriptionQuery
    {
        $this->nextPaymentDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are canceled.
     *
     * ---
     *
     * ```twig
     * {# Fetch canceled subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .isCanceled()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch canceled subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isCanceled()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static
     */
    public function isCanceled(?bool $value = true): SubscriptionQuery
    {
        $this->isCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ cancellation date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were canceled on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were canceled before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were canceled between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were canceled recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *   .dateCanceled(">= #{aWeekAgo}")
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were canceled recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateCanceled(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static
     */
    public function dateCanceled(mixed $value): SubscriptionQuery
    {
        $this->dateCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that have started.
     *
     * ---
     *
     * ```twig
     * {# Fetch started subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .hasStarted()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch started subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->hasStarted()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static
     */
    public function hasStarted(?bool $value = true): SubscriptionQuery
    {
        $this->hasStarted = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are suspended.
     *
     * ---
     *
     * ```twig
     * {# Fetch suspended subscriptions #}
     * {% set {elements-var} = {twig-method}
     *   .isSuspended()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch suspended subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isSuspended()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static
     */
    public function isSuspended(?bool $value = true): SubscriptionQuery
    {
        $this->isSuspended = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ suspension date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were suspended on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were suspended before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were suspended between 2018-04-01 and 2018-05-01.
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were suspended recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *   .dateSuspended(">= #{aWeekAgo}")
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were suspended recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateSuspended(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static
     */
    public function dateSuspended(mixed $value): SubscriptionQuery
    {
        $this->dateSuspended = $value;
        return $this;
    }

    /**
     * @inheritdoc
     * @throws QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        if ($this->stripeId === [] || $this->stripePriceId === [] || $this->priceId === [] || $this->planId === [] || $this->reference === []) {
            return false;
        }

        $qb = Craft::$app->getDb()->getQueryBuilder();

        $subscriptionTable = 'stripe_subscriptions';
        $subscriptionDataTable = 'stripe_subscriptiondata';

        // join standard subscription element table that only contains the stripeId
        $this->joinElementTable($subscriptionTable);

        $subscriptionDataJoinTable = [$subscriptionDataTable => "{{%$subscriptionDataTable}}"];
        $this->query->leftJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");
        $this->subQuery->leftJoin($subscriptionDataJoinTable, "[[$subscriptionDataTable.stripeId]] = [[$subscriptionTable.stripeId]]");

        $customerDataJoinTable = ['stripe_customerdata' => '{{%stripe_customerdata}}'];
        $this->query->leftJoin(
            $customerDataJoinTable,
            "[[stripe_customerdata.stripeId]] = [[$subscriptionDataTable.customerId]]",
        );
        $this->subQuery->leftJoin(
            $customerDataJoinTable,
            "[[stripe_customerdata.stripeId]] = [[$subscriptionDataTable.customerId]]",
        );

        $this->query->select([
            'stripe_subscriptions.stripeId',
            'stripe_subscriptiondata.stripeStatus',
            'stripe_subscriptiondata.data',
            'stripe_customerdata.stripeId AS customerStripeId',
            'stripe_customerdata.email AS customerEmail',
            'stripe_customerdata.data AS customerData',
        ]);

        if (isset($this->stripeId)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_subscriptions.stripeId', $this->stripeId));
        }

        if (isset($this->customerId)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_customerdata.stripeId', $this->customerId));
        }

        if (isset($this->userId)) {
            // TODO: there must be a better way that accounts for when user(s) is/are not found?
            $userEmails = (new Query())
                ->select('email')
                ->from([CraftTable::USERS])
                ->where(['id' => $this->userId])
                ->column();

            if (empty($userEmails)) {
                $userEmails = ':empty:';
            }
            $this->subQuery->andWhere(Db::parseParam('stripe_customerdata.email', $userEmails));
        }

        if (isset($this->userEmail)) {
            $this->subQuery->andWhere(Db::parseParam('stripe_customerdata.email', $this->userEmail));
        }

        if (isset($this->priceId) || isset($this->planId) || isset($this->reference)) {
            if (isset($this->planId)) {
                $this->stripePriceId = (new Query())
                    ->select(['stripeId'])
                    ->from([Table::PRICES])
                    ->where(Db::parseParam('id', $this->planId))
                    ->column();
            }

            if (isset($this->reference)) {
                $this->stripePriceId = (new Query())
                    ->select(['stripeId'])
                    ->from([Table::PRICES])
                    ->where(Db::parseParam('id', $this->reference))
                    ->column();
            }

            if (isset($this->priceId)) {
                $this->stripePriceId = (new Query())
                    ->select(['stripeId'])
                    ->from([Table::PRICES])
                    ->where(Db::parseParam('id', $this->priceId))
                    ->column();
                //$qb->jsonContains("[[stripe_subscriptiondata.prices]]", $this->priceId)
            }
        }

        if (isset($this->stripePriceId)) {
            $stripePriceId = $this->prepareForPriceIdSearch('stripePriceId');
            $this->subQuery->andWhere(
                Db::parseParam('stripe_subscriptiondata.prices', $stripePriceId)
            );
        }

        if (isset($this->latestInvoiceId)) {
            $this->subQuery->leftJoin(
                ['stripe_invoicedata' => '{{%stripe_invoicedata}}'],
                "[[stripe_subscriptions.stripeId]] = [[stripe_invoicedata.subscriptionId]]",
            );
            $this->subQuery->andWhere(Db::parseParam(
                "stripe_subscriptiondata.latestInvoiceId",
                $this->latestInvoiceId
            ));
        }

        if (isset($this->nextPaymentDate)) {
            $this->subQuery->andWhere(Db::parseTimestampParam(
                "stripe_subscriptiondata.currentPeriodEnd",
                $this->nextPaymentDate,
            ));
        }

        if (isset($this->isCanceled)) {
            if ($this->isCanceled) {
                $this->subQuery->andWhere(Db::parseParam(
                    'stripe_subscriptiondata.stripeStatus',
                    Subscription::STRIPE_STATUS_CANCELED
                ));
            } else {
                $this->subQuery->andWhere(Db::parseParam(
                    'stripe_subscriptiondata.stripeStatus',
                    Subscription::STRIPE_STATUS_CANCELED,
                    'not'
                ));
            }
        }

        if (isset($this->dateCanceled)) {
            $this->subQuery->andWhere(Db::parseParam(
                "stripe_subscriptiondata.canceledAt",
                $this->dateCanceled,
            ));
        }

        if (isset($this->hasStarted)) {
            if ($this->hasStarted) {
                $q = new Expression("stripe_subscriptiondata.startDate <= NOW()");
            } else {
                $q = new Expression("stripe_subscriptiondata.startDate > NOW()");
            }
            $this->subQuery->andWhere($q);
        }

        if (isset($this->isSuspended)) {
            if ($this->isSuspended) {
                $this->subQuery->andWhere(Db::parseParam(
                    'stripe_subscriptiondata.stripeStatus',
                    \Stripe\Subscription::STATUS_PAST_DUE,
                ));
            } else {
                $this->subQuery->andWhere(Db::parseParam(
                    'stripe_subscriptiondata.stripeStatus',
                    \Stripe\Subscription::STATUS_PAST_DUE,
                    'not'
                ));
            }
        }

        if (isset($this->dateSuspended)) {
            // todo: should we check for isSuspended too?
            $this->subQuery->leftJoin(
                ['stripe_invoicedata' => '{{%stripe_invoicedata}}'],
                "[[stripe_subscriptions.stripeId]] = [[stripe_invoicedata.subscriptionId",
            );

            $this->subQuery->andWhere(Db::parseTimestampParam(
                "stripe_invoicedata.created",
                $this->dateSuspended
            ));
        }

        if (isset($this->onTrial)) {
            $this->subQuery->andWhere($this->getTrialCondition($this->onTrial, $qb));
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
                    'stripeId' => $row['customerStripeId'],
                    'email' => $row['customerEmail'],
                    'data' => $row['customerData'],
                ], false);

                $row['customer'] = $model;
            }

            unset($row['customerStripeId']);
            unset($row['customerEmail']);
            unset($row['customerData']);
        }

        return parent::populate($rows);
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
     * Prepare parameter for searching through subscription price ids.
     *
     * @param string $param
     * @return string|string[]
     */
    private function prepareForPriceIdSearch(string $param): string|array
    {
        // Prices are stored as a string representation of an array.
        // In order to support the usual syntax e.g. ['id1', 'id2'] or ['not', 'id1', 'id2']
        // we need to search with `like` condition.
        // So if the parameter is an array, all the query values need to start and end with '*'.

        $result = $this->{$param};

        if (is_array($this->{$param})) {
            $queryParam = QueryParam::parse($this->{$param});
            if (!empty($queryParam->values)) {
                $queryParam->values = array_map(function ($val) {
                    if (!str_starts_with($val, ':')) {
                        return "*" . $val . "*";
                    }
                    return $val;

                }, $queryParam->values);

                $result = array_merge([$queryParam->operator], $queryParam->values);
            }
        }

        return $result;
    }

    /**
     * Returns the SQL condition to use for trial status.
     *
     * @param bool $onTrial
     * @param QueryBuilder $qb
     * @return mixed
     */
    private function getTrialCondition(bool $onTrial, QueryBuilder $qb): array
    {
        if ($onTrial === true) {
            // on trial so when trial start is <= now and trial end is > now
            if (Craft::$app->getDb()->getIsPgsql()) {
                return [
                    'and',
                    new Expression("stripe_subscriptiondata.trialStart <= EXTRACT(EPOCH FROM now())"),
                    new Expression("stripe_subscriptiondata.trialEnd > EXTRACT(EPOCH FROM now())"),
                ];
            }

            return [
                'and',
                new Expression("stripe_subscriptiondata.trialStart <= UNIX_TIMESTAMP()"),
                new Expression("stripe_subscriptiondata.trialEnd > UNIX_TIMESTAMP()"),
            ];
        }

        // not on trial so when trial_start > now or trial_end < now
        if (Craft::$app->getDb()->getIsPgsql()) {
            return [
                'or',
                new Expression("stripe_subscriptiondata.trialStart > EXTRACT(EPOCH FROM now())"),
                new Expression("stripe_subscriptiondata.trialEnd < EXTRACT(EPOCH FROM now())"),
            ];
        }

        return [
            'or',
            new Expression("stripe_subscriptiondata.trialStart > UNIX_TIMESTAMP()"),
            new Expression("stripe_subscriptiondata.trialEnd < UNIX_TIMESTAMP()"),
        ];
    }
}
