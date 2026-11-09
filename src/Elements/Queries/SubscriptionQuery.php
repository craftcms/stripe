<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Queries;

use Closure;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Support\QueryParams;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Queries Stripe subscriptions.
 *
 * @extends ElementQuery<Subscription>
 *
 * @since 2.0.0
 */
class SubscriptionQuery extends ElementQuery
{
    #[Override]
    protected string $table = Table::SUBSCRIPTIONS;

    #[Override]
    protected array $defaultOrderBy = ['elements.dateCreated' => SORT_DESC];

    public mixed $stripeId = null;

    public mixed $stripeStatus = null;

    /**
     * @var mixed The ID(s) of the Craft users whose email the customer must have
     */
    public mixed $userId = null;

    public mixed $userEmail = null;

    /**
     * @var mixed The Stripe customer ID(s)
     */
    public mixed $customerId = null;

    /**
     * @var mixed The Stripe price ID(s) the subscription must include
     */
    public mixed $stripePriceId = null;

    public mixed $latestInvoiceId = null;

    public ?bool $onTrial = null;

    public mixed $nextPaymentDate = null;

    public ?bool $isCanceled = null;

    public mixed $dateCanceled = null;

    public ?bool $hasStarted = null;

    public ?bool $isSuspended = null;

    public mixed $dateSuspended = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $config['status'] ??= Element::STATUS_ENABLED;

        parent::__construct(Subscription::class, $config);

        $this->query->leftJoin(Table::SUBSCRIPTIONDATA.' as stripe_subscriptiondata', 'stripe_subscriptiondata.stripeId', '=', 'stripe_subscriptions.stripeId');
        $this->query->leftJoin(Table::CUSTOMERDATA.' as stripe_customerdata', 'stripe_customerdata.stripeId', '=', 'stripe_subscriptiondata.customerId');
        $this->query->addSelect([
            'stripe_subscriptions.stripeId as stripeId',
            'stripe_subscriptiondata.stripeStatus as stripeStatus',
            'stripe_subscriptiondata.data as data',
            'stripe_subscriptiondata.prices as prices',
            'stripe_subscriptiondata.canceledAt as canceledAt',
            'stripe_subscriptiondata.currentPeriodEnd as currentPeriodEnd',
            'stripe_subscriptiondata.customerId as customerId',
            'stripe_subscriptiondata.latestInvoiceId as latestInvoiceId',
            'stripe_subscriptiondata.startDate as startDate',
            'stripe_subscriptiondata.trialStart as trialStart',
            'stripe_subscriptiondata.trialEnd as trialEnd',
            'stripe_customerdata.stripeId as customerStripeId',
            'stripe_customerdata.email as customerEmail',
            'stripe_customerdata.data as customerData',
        ]);

        $this->beforeQuery(static fn (self $query) => self::applyParams($query));
    }

    private static function applyParams(self $query): void
    {
        if ($query->stripeId === [] || $query->stripePriceId === []) {
            throw new QueryAbortedException;
        }

        if (isset($query->stripeId)) {
            $query->whereParam('stripe_subscriptions.stripeId', $query->stripeId);
        }

        if (isset($query->customerId)) {
            $query->whereParam('stripe_subscriptiondata.customerId', $query->customerId);
        }

        if (isset($query->userId)) {
            $emails = DB::table(CraftTable::USERS)->whereParam('id', $query->userId)->pluck('email')->all();

            if ($emails === []) {
                throw new QueryAbortedException;
            }

            $query->whereIn('stripe_customerdata.email', $emails);
        }

        if (isset($query->userEmail)) {
            $query->whereParam('stripe_customerdata.email', $query->userEmail, caseInsensitive: true);
        }

        if (isset($query->stripePriceId)) {
            $query->whereParam('stripe_subscriptiondata.prices', QueryParams::contains($query->stripePriceId));
        }

        if (isset($query->latestInvoiceId)) {
            $query->whereParam('stripe_subscriptiondata.latestInvoiceId', $query->latestInvoiceId);
        }

        if (isset($query->nextPaymentDate)) {
            $query->whereParam('stripe_subscriptiondata.currentPeriodEnd', QueryParams::timestamps($query->nextPaymentDate));
        }

        if (isset($query->dateCanceled)) {
            $query->whereParam('stripe_subscriptiondata.canceledAt', QueryParams::timestamps($query->dateCanceled));
        }

        if (isset($query->stripeStatus)) {
            $query->whereParam('stripe_subscriptiondata.stripeStatus', $query->stripeStatus);
        }

        if ($query->isCanceled !== null) {
            $query->where(
                'stripe_subscriptiondata.stripeStatus',
                $query->isCanceled ? '=' : '!=',
                Subscription::STRIPE_STATUS_CANCELED,
            );
        }

        if ($query->isSuspended !== null) {
            $healthy = [Subscription::STRIPE_STATUS_ACTIVE, Subscription::STRIPE_STATUS_TRIALING, Subscription::STRIPE_STATUS_CANCELED];
            $query->isSuspended
                ? $query->whereNotIn('stripe_subscriptiondata.stripeStatus', $healthy)
                : $query->whereIn('stripe_subscriptiondata.stripeStatus', $healthy);
        }

        if (isset($query->dateSuspended)) {
            $query->whereIn('stripe_subscriptions.stripeId', DB::table(Table::INVOICEDATA)
                ->select('subscriptionId')
                ->whereNotNull('subscriptionId')
                ->whereParam('created', QueryParams::timestamps($query->dateSuspended)));
        }

        $now = (string) now()->getTimestamp();

        if ($query->hasStarted !== null) {
            $query->where('stripe_subscriptiondata.startDate', $query->hasStarted ? '<=' : '>', $now);
        }

        if ($query->onTrial !== null) {
            $onTrial = fn (Builder $q) => $q
                ->where('stripe_subscriptiondata.trialStart', '<=', $now)
                ->where('stripe_subscriptiondata.trialEnd', '>', $now);

            $query->onTrial
                ? $query->where($onTrial)
                : $query->where(fn (Builder $q) => $q
                    ->whereNull('stripe_subscriptiondata.trialEnd')
                    ->orWhereNot($onTrial));
        }
    }

    public function stripeId(mixed $value): static
    {
        $this->stripeId = $value;

        return $this;
    }

    public function stripeStatus(mixed $value): static
    {
        $this->stripeStatus = $value;

        return $this;
    }

    /**
     * Narrows the results to subscriptions of a user’s Stripe customers.
     */
    public function user(User|int|string|null $value): static
    {
        if (is_numeric($value)) {
            return $this->userId($value);
        }

        $this->userEmail = $value instanceof User ? $value->email : $value;

        return $this;
    }

    public function userId(mixed $value): static
    {
        $this->userId = $value;

        return $this;
    }

    public function userEmail(mixed $value): static
    {
        $this->userEmail = $value;

        return $this;
    }

    public function customerId(mixed $value): static
    {
        $this->customerId = $value;

        return $this;
    }

    /**
     * Narrows the results to subscriptions that include a price: a price element, its ID, or a Stripe price ID.
     */
    public function price(mixed $value): static
    {
        $this->stripePriceId = match (true) {
            $value instanceof Price => $value->stripeId,
            is_numeric($value), is_array($value) && array_filter($value, 'is_numeric') === $value && $value !== [] => self::stripePriceIds($value),
            default => $value,
        };

        return $this;
    }

    /**
     * Alias of {@see Price()}, as Commerce subscriptions call it.
     */
    public function plan(mixed $value): static
    {
        return $this->price($value);
    }

    public function stripePrice(mixed $value): static
    {
        $this->stripePriceId = $value instanceof Price ? $value->stripeId : $value;

        return $this;
    }

    /**
     * Narrows the results to subscriptions that include a price, by its element ID.
     */
    public function priceId(mixed $value): static
    {
        $this->stripePriceId = $value === null ? null : self::stripePriceIds($value);

        return $this;
    }

    public function planId(mixed $value): static
    {
        return $this->priceId($value);
    }

    public function reference(mixed $value): static
    {
        return $this->priceId($value);
    }

    public function stripePriceId(mixed $value): static
    {
        $this->stripePriceId = $value;

        return $this;
    }

    public function latestInvoiceId(mixed $value): static
    {
        $this->latestInvoiceId = $value;

        return $this;
    }

    public function onTrial(?bool $value = true): static
    {
        $this->onTrial = $value;

        return $this;
    }

    public function nextPaymentDate(mixed $value): static
    {
        $this->nextPaymentDate = $value;

        return $this;
    }

    public function isCanceled(?bool $value = true): static
    {
        $this->isCanceled = $value;

        return $this;
    }

    public function dateCanceled(mixed $value): static
    {
        $this->dateCanceled = $value;

        return $this;
    }

    public function hasStarted(?bool $value = true): static
    {
        $this->hasStarted = $value;

        return $this;
    }

    public function isSuspended(?bool $value = true): static
    {
        $this->isSuspended = $value;

        return $this;
    }

    public function dateSuspended(mixed $value): static
    {
        $this->dateSuspended = $value;

        return $this;
    }

    #[Override]
    public function createElement(array $row): ElementInterface
    {
        $customer = null;

        if (isset($row['customerStripeId'])) {
            $customer = new Customer([
                'stripeId' => $row['customerStripeId'],
                'email' => $row['customerEmail'],
                'data' => is_string($row['customerData']) ? json_decode($row['customerData'], true) : $row['customerData'],
            ]);
        }

        unset($row['customerStripeId'], $row['customerEmail'], $row['customerData']);

        /** @var Subscription $element */
        $element = parent::createElement($row);

        if ($customer !== null) {
            $element->setCustomer($customer);
        }

        return $element;
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        $enabled = fn (Builder $query) => $query
            ->whereBool('elements.enabled', true)
            ->whereBool('elements_sites.enabled', true);

        return match (strtolower($status)) {
            strtolower(Subscription::STATUS_LIVE) => fn (Builder $query) => $enabled($query)
                ->whereIn('stripe_subscriptiondata.stripeStatus', [Subscription::STRIPE_STATUS_ACTIVE, Subscription::STRIPE_STATUS_TRIALING]),
            strtolower(Subscription::STATUS_STRIPE_SCHEDULED) => fn (Builder $query) => $enabled($query)
                ->where('stripe_subscriptiondata.stripeStatus', Subscription::STRIPE_STATUS_SCHEDULED),
            strtolower(Subscription::STATUS_STRIPE_CANCELED) => fn (Builder $query) => $enabled($query)
                ->where('stripe_subscriptiondata.stripeStatus', Subscription::STRIPE_STATUS_CANCELED),
            strtolower(Subscription::STATUS_STRIPE_SUSPENDED) => fn (Builder $query) => $enabled($query)
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
     * Returns the Stripe IDs of prices by their element IDs. An empty array makes the query return nothing.
     *
     * @return string[]
     */
    private static function stripePriceIds(mixed $priceIds): array
    {
        return DB::table(Table::PRICES)->whereParam('id', $priceIds)->pluck('stripeId')->filter()->values()->all();
    }
}
