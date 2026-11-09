<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Events\ProductSynced;
use CraftCms\Stripe\Events\ProductSyncing;
use CraftCms\Stripe\Models\ProductData;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Product as StripeProduct;

/**
 * Syncs Stripe products to product elements.
 *
 * @since 2.0.0
 */
#[Singleton]
class Products
{
    public function __construct(
        private readonly Api $api,
    ) {}

    /**
     * Syncs every product from Stripe, and deletes the elements of products no longer there.
     */
    public function syncAllProducts(): void
    {
        $products = $this->api->fetchAllProducts();

        foreach ($products as $product) {
            $this->createOrUpdateProduct($product);
        }

        $stripeIds = array_map(fn (StripeProduct $product) => $product->id, $products);

        foreach (Product::find()->stripeId(['not', ...$stripeIds])->status(null)->all() as $element) {
            Elements::deleteElement($element);
        }
    }

    /**
     * Creates or updates the element for a Stripe product.
     */
    public function createOrUpdateProduct(StripeProduct $product): bool
    {
        return Cache::lock("stripe-product:$product->id", 15)->block(15, function () use ($product) {
            $element = Product::find()->stripeId($product->id)->status(null)->one() ?? new Product;

            $attributes = [
                'stripeId' => $product->id,
                'title' => $product->name,
                'stripeStatus' => $product->active ? Product::STRIPE_STATUS_ACTIVE : Product::STRIPE_STATUS_ARCHIVED,
                'data' => $product->toArray(),
            ];
            Product::configure($element, $attributes);

            event($event = new ProductSyncing($element, $product));

            if (! $event->isValid) {
                Log::warning("Synchronization of Stripe product ID #$product->id was stopped by a plugin.");

                return false;
            }

            if (! Elements::saveElement($element)) {
                Log::error("Failed to synchronize Stripe product ID #$product->id.", ['errors' => $element->errors()->toArray()]);

                return false;
            }

            ProductData::updateOrCreate(['stripeId' => $product->id], [
                'productId' => $element->id,
                'stripeStatus' => $attributes['stripeStatus'],
                'data' => $attributes['data'],
            ]);

            event(new ProductSynced($element, $product));

            return true;
        });
    }

    /**
     * Returns the products of a subscription’s items.
     *
     * @return Product[]|null
     */
    public function getProductsBySubscriptionId(?string $subscriptionId): ?array
    {
        if ($subscriptionId === null) {
            return null;
        }

        $subscription = Subscription::find()->stripeId($subscriptionId)->status(null)->one();

        if (! $subscription instanceof Subscription) {
            return null;
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            fn (array $item) => $item['price']['product'] ?? null,
            $subscription->getData()['items']['data'] ?? [],
        ))));

        if ($productIds === []) {
            return [];
        }

        return Product::find()->stripeId($productIds)->status(null)->all();
    }

    public function deleteProductByStripeId(string $stripeId): void
    {
        if ($product = Product::find()->stripeId($stripeId)->status(null)->one()) {
            Elements::deleteElement($product, true);
        }

        ProductData::where('stripeId', $stripeId)->delete();
    }
}
