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
use craft\elements\User;
use craft\helpers\Db;
use craft\stripe\db\Table;
use craft\stripe\elements\Price;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;

/**
 * Subscription query
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @method Subscription[]|array all($db = null)
 * @method Subscription|array|null one($db = null)
 * @method Subscription|array|null nth(int $n, Connection $db = null)
 */
class SubscriptionQuery extends ElementQuery
{
    use PreparesSubscriptionQuery;

    /**
     * @var mixed The Stripe subscription ID(s) that the resulting subscriptions must have.
     */
    public mixed $stripeId = null;

    /**
     * @var mixed The status of the subscription in Stripe.
     * @since 1.8.1
     */
    public mixed $stripeStatus = null;

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

        $this->initSubscriptionQuery();
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value): void
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
        if ($value === null) {
            unset($this->isSuspended, $this->hasStarted);
        }

        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ Stripe statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `*` or `null` | any Stripe Status.
     * | `'trialing'` or `\Stripe\Subscription::STATUS_TRIALING` | that are Trialing in Stripe.
     * | `'active'` or `\Stripe\Subscription::STATUS_ACTIVE` | that are Active in Stripe.
     * | `'incomplete'` or `\Stripe\Subscription::STATUS_INCOMPLETE` | that are Incomplete in Stripe.
     * | `'incomplete_expired'` or `\Stripe\Subscription::STATUS_INCOMPLETE_EXPIRED` | that are Incomplete Expired in Stripe.
     * | `'past_due'` or `\Stripe\Subscription::STATUS_PAST_DUE` | that are Past Due in Stripe.
     * | `'canceled'` or `\Stripe\Subscription::STATUS_CANCELED` | that are Canceled in Stripe.
     * | `'unpaid'` or `\Stripe\Subscription::STATUS_UNPAID` | that are Unpaid in Stripe.
     * | `'paused'` or `\Stripe\Subscription::STATUS_PAUSED` | that are Paused in Stripe.
     * | `['trialing', 'active']` | that are Trialing or Active in Stripe.
     *
     * ---
     *
     * ```twig
     * {# Fetch trialing {elements} #}
     * {% set {elements-var} = {twig-method}
     *   .stripeStatus('trialing')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch trialing {elements}
     * ${elements-var} = {element-class}::find()
     *     ->stripeStatus(\Stripe\Subscription::STATUS_TRIALING)
     *     ->all();
     * ```
     * @since 1.8.1
     */
    public function stripeStatus(array|string|null $value): static
    {
        if ($value === null || $value === '*') {
            $this->stripeStatus = null;
        } else {
            $this->stripeStatus = $value;
        }

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
     * Values must be a unix timestamp.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 1522558800'` | with a next payment on or after 1522558800 (2018-04-01).
     * | `'< 1525150800'` | with a next payment before 1525150800 (2018-05-01)
     * | `['and', '>= 1522558800', '< 1525150800']` | with a next payment between 1522558800 (2018-04-01) and 1525150800 (2018-05-01).
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a payment due soon #}
     * {% set aWeekFromNow = date('+7 days')|date('U') %}
     *
     * {% set {elements-var} = {twig-method}
     *   .nextPaymentDate("< #{aWeekFromNow}")
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a payment due soon
     * $aWeekFromNow = new \DateTime('+7 days')->getTimestamp()
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
}
