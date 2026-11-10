<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Json;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Events\PriceSynced;
use CraftCms\Stripe\Events\PriceSyncing;
use CraftCms\Stripe\Models\PriceData;
use CraftCms\Stripe\Support\PriceFormatter;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Price as StripePrice;

/**
 * Syncs Stripe prices to price elements.
 *
 * @since 2.0.0
 */
#[Singleton]
class Prices
{
    public function __construct(
        private readonly Api $api,
    ) {}

    /**
     * Syncs every price from Stripe, and deletes the elements of prices no longer there.
     */
    public function syncAllPrices(): void
    {
        $prices = $this->api->fetchAllPrices();

        foreach ($prices as $price) {
            $this->createOrUpdatePrice($price);
        }

        $stripeIds = array_map(fn (StripePrice $price) => $price->id, $prices);

        foreach (Price::find()->stripeId(['not', ...$stripeIds])->status(null)->all() as $element) {
            Elements::deleteElement($element);
        }
    }

    /**
     * Creates or updates the element for a Stripe price, owned by its product’s element.
     */
    public function createOrUpdatePrice(StripePrice $price): bool
    {
        return Cache::lock("stripe-price:$price->id", 15)->block(15, function () use ($price) {
            $element = Price::find()->stripeId($price->id)->status(null)->one() ?? new Price;
            $data = $price->toArray();

            $attributes = [
                'stripeId' => $price->id,
                'title' => PriceFormatter::asUnitPrice($data),
                'stripeStatus' => $price->active ? Price::STRIPE_STATUS_ACTIVE : Price::STRIPE_STATUS_ARCHIVED,
                'data' => $data,
                'unitAmount' => PriceFormatter::asUnitAmountNumber($data),
                'currencies' => array_keys($data['currency_options'] ?? []) ?: null,
            ];
            Price::configure($element, $attributes);

            $productId = is_string($price->product) ? $price->product : $price->product->id;
            $product = $productId ? Product::find()->stripeId($productId)->status(null)->one() : null;

            if ($product) {
                $element->setOwnerId($product->id);
                $element->setPrimaryOwnerId($product->id);
            }

            event($event = new PriceSyncing($element, $price));

            if (! $event->isValid) {
                Log::warning("Synchronization of Stripe price ID #$price->id was stopped by a plugin.");

                return false;
            }

            if (! Elements::saveElement($element)) {
                Log::error("Failed to synchronize Stripe price ID #$price->id.", ['errors' => $element->errors()->toArray()]);

                return false;
            }

            PriceData::updateOrCreate(['stripeId' => $price->id], [
                'priceId' => $element->id,
                'stripeStatus' => $attributes['stripeStatus'],
                'data' => $data,
                'unitAmount' => $attributes['unitAmount'],
                'currencies' => $attributes['currencies'] ? Json::encode($attributes['currencies']) : null,
            ]);

            event(new PriceSynced($element, $price));

            return true;
        });
    }

    public function getPriceByStripeId(string $stripeId): ?Price
    {
        $price = Price::find()->stripeId($stripeId)->one();

        return $price instanceof Price ? $price : null;
    }

    public function deletePriceByStripeId(string $stripeId): void
    {
        if ($price = Price::find()->stripeId($stripeId)->status(null)->one()) {
            Elements::deleteElement($price);
        }

        PriceData::where('stripeId', $stripeId)->delete();
    }
}
