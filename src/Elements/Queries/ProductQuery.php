<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Elements\Queries;

use Closure;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Override;

/**
 * Queries Stripe products.
 *
 * @extends ElementQuery<Product>
 *
 * @since 2.0.0
 */
class ProductQuery extends ElementQuery
{
    #[Override]
    protected string $table = Table::PRODUCTS;

    #[Override]
    protected array $defaultOrderBy = ['stripe_productdata.stripeId' => SORT_ASC];

    /**
     * @var mixed The Stripe ID(s) to match
     */
    public mixed $stripeId = null;

    /**
     * @var mixed The Stripe status(es) to match: `active` or `archived`
     */
    public mixed $stripeStatus = null;

    /**
     * @var mixed A price query, or price query criteria, that a product’s prices must match
     */
    public mixed $hasPrice = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $config['status'] ??= Element::STATUS_ENABLED;

        parent::__construct(Product::class, $config);

        $this->query->join(Table::PRODUCTDATA.' as stripe_productdata', 'stripe_productdata.stripeId', '=', 'stripe_products.stripeId');
        $this->query->addSelect([
            'stripe_products.stripeId as stripeId',
            'stripe_productdata.stripeStatus as stripeStatus',
            'stripe_productdata.data as data',
        ]);

        $this->beforeQuery(static function (self $query) {
            if ($query->stripeId === []) {
                throw new QueryAbortedException;
            }

            if (isset($query->stripeId)) {
                $query->whereParam('stripe_productdata.stripeId', $query->stripeId);
            }

            if (isset($query->stripeStatus)) {
                $query->whereParam('stripe_productdata.stripeStatus', $query->stripeStatus);
            }

            static::applyHasPrice($query, $query->hasPrice);
        });
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

    public function hasPrice(mixed $value): static
    {
        $this->hasPrice = $value;

        return $this;
    }

    /**
     * Limits the query to products with a price that matches a price query or price query criteria.
     */
    public static function applyHasPrice(BuilderContract $query, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if ($value instanceof PriceQuery) {
            $priceQuery = $value;
        } elseif (is_array($value)) {
            $priceQuery = Price::find();
            self::configure($priceQuery, $value);
        } else {
            throw new QueryAbortedException('Invalid param used. ProductQuery::hasPrice param only expects a price query or price query config.');
        }

        $priceQuery->limit(null);
        $priceQuery->whereNotNull('stripe_prices.primaryOwnerId');
        $priceQuery->applyBeforeQueryCallbacks();
        $priceQuery->query->select('stripe_prices.primaryOwnerId');

        $query->whereIn('stripe_products.id', $priceQuery->query);
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        return match (strtolower($status)) {
            strtolower(Product::STATUS_LIVE) => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('stripe_productdata.stripeStatus', Product::STRIPE_STATUS_ACTIVE),
            strtolower(Product::STATUS_STRIPE_ARCHIVED) => fn (Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('stripe_productdata.stripeStatus', Product::STRIPE_STATUS_ARCHIVED),
            default => parent::statusCondition($status),
        };
    }
}
