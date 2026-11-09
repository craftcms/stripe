<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Queries;

use Closure;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Concerns\QueriesNestedElements;
use CraftCms\Cms\Element\Queries\Contracts\NestedElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Enums\PriceType;
use CraftCms\Stripe\Support\QueryParams;
use Illuminate\Database\Query\Builder;
use Override;

/**
 * Queries Stripe prices.
 *
 * @extends ElementQuery<Price>
 *
 * @implements NestedElementQueryInterface<Price>
 *
 * @since 3.0.0
 */
class PriceQuery extends ElementQuery implements NestedElementQueryInterface
{
    use QueriesNestedElements;

    #[Override]
    protected string $table = Table::PRICES;

    #[Override]
    protected array $defaultOrderBy = ['stripe_pricedata.stripeId' => SORT_ASC];

    public mixed $stripeId = null;

    public mixed $stripeStatus = null;

    /**
     * @var mixed `one_time` or `recurring`
     */
    public mixed $type = null;

    public mixed $primaryCurrency = null;

    /**
     * @var mixed A currency the price is offered in, whether or not it’s the primary one
     */
    public mixed $currency = null;

    public mixed $unitAmount = null;

    public mixed $stripeProductId = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $config['status'] ??= Element::STATUS_ENABLED;

        parent::__construct(Price::class, $config);

        $this->query->join(Table::PRICEDATA.' as stripe_pricedata', 'stripe_pricedata.stripeId', '=', 'stripe_prices.stripeId');
        $this->query->addSelect([
            'stripe_prices.stripeId as stripeId',
            'stripe_prices.primaryOwnerId as primaryOwnerId',
            'stripe_pricedata.stripeStatus as stripeStatus',
            'stripe_pricedata.data as data',
            'stripe_pricedata.currencies as currencies',
            'stripe_pricedata.unitAmount as unitAmount',
            'stripe_pricedata.type as type',
            'stripe_pricedata.productId as stripeProductId',
            'stripe_pricedata.primaryCurrency as primaryCurrency',
        ]);

        $this->beforeQuery(static function (self $query) {
            if ($query->stripeId === []) {
                throw new QueryAbortedException;
            }

            foreach ([
                'stripeId' => 'stripe_pricedata.stripeId',
                'stripeStatus' => 'stripe_pricedata.stripeStatus',
                'type' => 'stripe_pricedata.type',
                'primaryCurrency' => 'stripe_pricedata.primaryCurrency',
                'stripeProductId' => 'stripe_pricedata.productId',
            ] as $param => $column) {
                if (isset($query->$param)) {
                    $query->whereParam($column, $query->$param);
                }
            }

            if (isset($query->unitAmount)) {
                $query->whereNumericParam('stripe_pricedata.unitAmount', $query->unitAmount, columnType: null);
            }

            if (isset($query->currency)) {
                $query->whereParam('stripe_pricedata.currencies', QueryParams::contains($query->currency), caseInsensitive: true);
            }
        });
    }

    /**
     * Prices don’t belong to a field, so “no field”, as embedded element indexes ask for, matches every price.
     */
    public function shouldApplyNestedElementParams(): bool
    {
        if ($this->fieldId === false) {
            $this->fieldId = null;
        }

        return true;
    }

    public static function getFieldIdColumn(): string
    {
        // Prices don’t belong to a field
        return 'stripe_prices.primaryOwnerId';
    }

    public static function getPrimaryOwnerIdColumn(): string
    {
        return 'stripe_prices.primaryOwnerId';
    }

    public static function mustHaveField(): bool
    {
        return false;
    }

    public static function mustHaveOwner(): bool
    {
        return false;
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

    public function type(mixed $value): static
    {
        $this->type = $value instanceof PriceType ? $value->value : $value;

        return $this;
    }

    public function primaryCurrency(mixed $value): static
    {
        $this->primaryCurrency = $value;

        return $this;
    }

    public function currency(mixed $value): static
    {
        $this->currency = $value;

        return $this;
    }

    public function unitAmount(mixed $value): static
    {
        $this->unitAmount = $value;

        return $this;
    }

    public function stripeProductId(mixed $value): static
    {
        $this->stripeProductId = $value;

        return $this;
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        return match (strtolower($status)) {
            strtolower(Price::STATUS_LIVE) => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('stripe_pricedata.stripeStatus', Product::STRIPE_STATUS_ACTIVE),
            strtolower(Price::STATUS_STRIPE_ARCHIVED) => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('stripe_pricedata.stripeStatus', Product::STRIPE_STATUS_ARCHIVED),
            default => parent::statusCondition($status),
        };
    }
}
