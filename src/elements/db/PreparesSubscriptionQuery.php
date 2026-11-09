<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\db;

use Closure;
use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craft\stripe\db\Table;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;
use CraftCms\Cms\Database\QueryParam;
use CraftCms\Cms\Element\Contracts\ElementInterface as Craft6ElementInterface;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException as Craft6QueryAbortedException;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB as DbFacade;
use yii\db\Expression;
use yii\db\QueryBuilder;

if (!class_exists(\CraftCms\Cms\Element\Queries\ElementQuery::class)) {
    /**
     * Builds subscription queries on Craft 5.
     *
     * @internal
     */
    trait PreparesSubscriptionQuery
    {
        private function initSubscriptionQuery(): void
        {
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
                'stripe_subscriptiondata.prices',
                'stripe_subscriptiondata.canceledAt',
                'stripe_subscriptiondata.currentPeriodEnd',
                'stripe_subscriptiondata.customerId',
                'stripe_subscriptiondata.latestInvoiceId',
                'stripe_subscriptiondata.startDate',
                'stripe_subscriptiondata.trialStart',
                'stripe_subscriptiondata.trialEnd',
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
                // get email address for all userIds passed here
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
                $stripePriceId = \craft\stripe\helpers\Db::prepareForLikeSearch($this, 'stripePriceId');
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

            if (isset($this->stripeStatus)) {
                $this->subQuery->andWhere(Db::parseParam(
                    "stripe_subscriptiondata.stripeStatus",
                    $this->stripeStatus,
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
                    // subscription is considered suspended if it's not active, trialing or canceled
                    $this->subQuery->andWhere(Db::parseParam(
                        'stripe_subscriptiondata.stripeStatus',
                        [
                            'and',
                            Subscription::STRIPE_STATUS_ACTIVE,
                            Subscription::STRIPE_STATUS_TRIALING,
                            Subscription::STRIPE_STATUS_CANCELED,
                        ],
                        'not'
                    ));
                } else {
                    // subscription is considered NOT suspended if it's active, trialing or canceled
                    $this->subQuery->andWhere(Db::parseParam(
                        'stripe_subscriptiondata.stripeStatus',
                        [
                            Subscription::STRIPE_STATUS_ACTIVE,
                            Subscription::STRIPE_STATUS_TRIALING,
                            Subscription::STRIPE_STATUS_CANCELED,
                        ],
                    ));
                }
            }

            if (isset($this->dateSuspended)) {
                // todo: should we check for isSuspended too?
                $this->subQuery->leftJoin(
                    ['stripe_invoicedata' => '{{%stripe_invoicedata}}'],
                    "[[stripe_subscriptions.stripeId]] = [[stripe_invoicedata.subscriptionId]]",
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
                    'and',
                    ['elements.enabled' => true],
                    ['elements_sites.enabled' => true],
                    ['or', ['stripe_subscriptiondata.stripeStatus' => [
                        Subscription::STRIPE_STATUS_ACTIVE,
                        Subscription::STRIPE_STATUS_TRIALING,
                    ]]],
                ],
                strtolower(Subscription::STATUS_STRIPE_SCHEDULED) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'stripe_subscriptiondata.stripeStatus' => Subscription::STRIPE_STATUS_SCHEDULED,
                ],
                strtolower(Subscription::STATUS_STRIPE_CANCELED) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'stripe_subscriptiondata.stripeStatus' => Subscription::STRIPE_STATUS_CANCELED,
                ],
                strtolower(Subscription::STATUS_STRIPE_SUSPENDED) => [
                    'and',
                    ['elements.enabled' => true],
                    ['elements_sites.enabled' => true],
                    ['not', ['stripe_subscriptiondata.stripeStatus' => [
                        Subscription::STRIPE_STATUS_ACTIVE,
                        Subscription::STRIPE_STATUS_TRIALING,
                        Subscription::STRIPE_STATUS_SCHEDULED,
                        Subscription::STRIPE_STATUS_CANCELED,
                    ]]],
                ],
                default => parent::statusCondition($status),
            };

            return $res;
        }
        /**
         * Returns the SQL condition to use for trial status.
         *
         * @param bool $onTrial
         * @param QueryBuilder $qb
         * @return array
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
} else {
    /**
     * Builds subscription queries on Craft 6, where element queries are a single Laravel query and `beforePrepare()` is no longer called.
     *
     * @internal
     */
    trait PreparesSubscriptionQuery
    {
        protected string $table = 'stripe_subscriptions';

        private function initSubscriptionQuery(): void
        {
            $this->query
            ->leftJoin('stripe_subscriptiondata', 'stripe_subscriptiondata.stripeId', '=', 'stripe_subscriptions.stripeId')
            ->leftJoin('stripe_customerdata', 'stripe_customerdata.stripeId', '=', 'stripe_subscriptiondata.customerId')
            ->addSelect([
                'stripe_subscriptions.stripeId',
                'stripe_subscriptiondata.stripeStatus',
                'stripe_subscriptiondata.data',
                'stripe_subscriptiondata.prices',
                'stripe_subscriptiondata.canceledAt',
                'stripe_subscriptiondata.currentPeriodEnd',
                'stripe_subscriptiondata.customerId',
                'stripe_subscriptiondata.latestInvoiceId',
                'stripe_subscriptiondata.startDate',
                'stripe_subscriptiondata.trialStart',
                'stripe_subscriptiondata.trialEnd',
                'stripe_customerdata.stripeId as customerStripeId',
                'stripe_customerdata.email as customerEmail',
                'stripe_customerdata.data as customerData',
            ]);

            $this->beforeQuery(static fn(SubscriptionQuery $query) => $query->applySubscriptionParams());
        }

        private function applySubscriptionParams(): void
        {
            if ($this->stripeId === [] || $this->stripePriceId === [] || $this->priceId === [] || $this->planId === [] || $this->reference === []) {
                throw new Craft6QueryAbortedException();
            }

            $query = $this->query;

            if (isset($this->stripeId)) {
                $query->whereParam('stripe_subscriptions.stripeId', $this->stripeId);
            }

            if (isset($this->customerId)) {
                $query->whereParam('stripe_customerdata.stripeId', $this->customerId);
            }

            if (isset($this->userId)) {
                $userEmails = DbFacade::table('users')->whereParam('id', $this->userId)->pluck('email')->all();
                $query->whereParam('stripe_customerdata.email', $userEmails ?: ':empty:');
            }

            if (isset($this->userEmail)) {
                $query->whereParam('stripe_customerdata.email', $this->userEmail);
            }

            // priceId wins over reference, which wins over planId
            foreach (['planId', 'reference', 'priceId'] as $param) {
                if (isset($this->$param)) {
                    $this->stripePriceId = DbFacade::table('stripe_prices')->whereParam('id', $this->$param)->pluck('stripeId')->filter()->values()->all();
                }
            }

            if (isset($this->stripePriceId)) {
                if ($this->stripePriceId === []) {
                    throw new Craft6QueryAbortedException();
                }

                $query->whereParam('stripe_subscriptiondata.prices', \craft\stripe\helpers\Db::prepareForLikeSearch($this, 'stripePriceId'));
            }

            if (isset($this->latestInvoiceId)) {
                $query->whereParam('stripe_subscriptiondata.latestInvoiceId', $this->latestInvoiceId);
            }

            if (isset($this->nextPaymentDate)) {
                $query->whereParam('stripe_subscriptiondata.currentPeriodEnd', self::timestampParam($this->nextPaymentDate));
            }

            if (isset($this->isCanceled)) {
                $query->where('stripe_subscriptiondata.stripeStatus', $this->isCanceled ? '=' : '!=', Subscription::STRIPE_STATUS_CANCELED);
            }

            if (isset($this->dateCanceled)) {
                $query->whereParam('stripe_subscriptiondata.canceledAt', self::timestampParam($this->dateCanceled));
            }

            if (isset($this->stripeStatus)) {
                $query->whereParam('stripe_subscriptiondata.stripeStatus', $this->stripeStatus);
            }

            // Stripe’s dates are stored as Unix timestamps
            $now = (string)time();

            if (isset($this->hasStarted)) {
                $query->where('stripe_subscriptiondata.startDate', $this->hasStarted ? '<=' : '>', $now);
            }

            if (isset($this->isSuspended)) {
                // A subscription is suspended if it’s not active, trialing or canceled
                $query->whereIn('stripe_subscriptiondata.stripeStatus', [
                Subscription::STRIPE_STATUS_ACTIVE,
                Subscription::STRIPE_STATUS_TRIALING,
                Subscription::STRIPE_STATUS_CANCELED,
            ], not: $this->isSuspended);
            }

            if (isset($this->dateSuspended)) {
                $query
                ->leftJoin('stripe_invoicedata', 'stripe_subscriptions.stripeId', '=', 'stripe_invoicedata.subscriptionId')
                ->whereParam('stripe_invoicedata.created', self::timestampParam($this->dateSuspended));
            }

            if (isset($this->onTrial)) {
                if ($this->onTrial) {
                    $query
                    ->where('stripe_subscriptiondata.trialStart', '<=', $now)
                    ->where('stripe_subscriptiondata.trialEnd', '>', $now);
                } else {
                    $query->where(fn(Builder $q) => $q
                    ->where('stripe_subscriptiondata.trialStart', '>', $now)
                    ->orWhere('stripe_subscriptiondata.trialEnd', '<', $now));
                }
            }
        }

        /**
         * @inheritdoc
         */
        public function createElement(array $row): Craft6ElementInterface
        {
            $customer = null;

            if (isset($row['customerData'])) {
                $customer = new Customer();
                $customer->setAttributes([
                'stripeId' => $row['customerStripeId'],
                'email' => $row['customerEmail'],
                'data' => $row['customerData'],
            ], false);
            }

            unset($row['customerStripeId'], $row['customerEmail'], $row['customerData']);

            /** @var Subscription $element */
            $element = parent::createElement($row);

            if ($customer) {
                $element->setCustomer($customer);
            }

            return $element;
        }

        /**
         * @inheritdoc
         */
        protected function statusCondition(string $status): Closure
        {
            $enabled = fn(Builder $query) => $query
            ->whereBool('elements.enabled', true)
            ->whereBool('elements_sites.enabled', true);

            // Craft 6 doesn’t lowercase the status
            return match (strtolower($status)) {
                strtolower(Subscription::STATUS_LIVE) => fn(Builder $query) => $enabled($query)
                ->whereIn('stripe_subscriptiondata.stripeStatus', [Subscription::STRIPE_STATUS_ACTIVE, Subscription::STRIPE_STATUS_TRIALING]),
            strtolower(Subscription::STATUS_STRIPE_SCHEDULED) => fn(Builder $query) => $enabled($query)
                ->where('stripe_subscriptiondata.stripeStatus', Subscription::STRIPE_STATUS_SCHEDULED),
            strtolower(Subscription::STATUS_STRIPE_CANCELED) => fn(Builder $query) => $enabled($query)
                ->where('stripe_subscriptiondata.stripeStatus', Subscription::STRIPE_STATUS_CANCELED),
            strtolower(Subscription::STATUS_STRIPE_SUSPENDED) => fn(Builder $query) => $enabled($query)
                ->whereNotIn('stripe_subscriptiondata.stripeStatus', [
                    Subscription::STRIPE_STATUS_ACTIVE,
                    Subscription::STRIPE_STATUS_TRIALING,
                    Subscription::STRIPE_STATUS_SCHEDULED,
                    Subscription::STRIPE_STATUS_CANCELED,
                ]),
            default => parent::statusCondition($status),
            };
        }

        /**
         * Converts the dates in a date query param, such as `>= 2026-01-01`, to the Unix timestamps Stripe stores.
         */
        private static function timestampParam(mixed $value): mixed
        {
            $param = QueryParam::parse($value);
            $values = [$param->operator];

            foreach ($param->values as $item) {
                if (is_string($item) && str_starts_with($item, ':')) {
                    $values[] = $item;
                    continue;
                }

                $operator = '';

                if (is_string($item) && preg_match('/^(not\s+|!=|<=|>=|<|>|=)\s*(.+)$/i', $item, $match)) {
                    [, $operator, $item] = $match;
                }

                $date = $item instanceof DateTimeInterface ? $item : Date::parse(is_numeric($item) ? '@' . $item : $item);
                $values[] = $operator . $date->getTimestamp();
            }

            return $values;
        }
    }
}
