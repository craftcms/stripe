<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Elements\Db;

use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\enums\PriceType;
use craft\stripe\Plugin;
use craft\stripe\tests\TestCase;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;

class PriceQueryTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $stripeProduct = StripeProduct::constructFrom([
            'id' => 'prod_for_prices',
            'name' => 'Product for prices',
            'active' => true,
        ]);
        Plugin::getInstance()->getProducts()->createOrUpdateProduct($stripeProduct);
        $this->product = Product::find()->stripeId('prod_for_prices')->one();
    }

    public function testStatusLiveReturnsOnlyActivePrices(): void
    {
        $this->seedPrice('price_active', true, 'usd', 'one_time');
        $this->seedPrice('price_archived', false, 'usd', 'one_time');

        $stripeIds = array_map(
            fn(Price $price) => $price->stripeId,
            Price::find()->status('live')->all()
        );

        $this->assertContains('price_active', $stripeIds);
        $this->assertNotContains('price_archived', $stripeIds);
    }

    public function testStatusStripeArchivedReturnsOnlyArchivedPrices(): void
    {
        $this->seedPrice('price_active2', true, 'usd', 'one_time');
        $this->seedPrice('price_archived2', false, 'usd', 'one_time');

        $stripeIds = array_map(
            fn(Price $price) => $price->stripeId,
            Price::find()->status('stripeArchived')->all()
        );

        $this->assertContains('price_archived2', $stripeIds);
        $this->assertNotContains('price_active2', $stripeIds);
    }

    public function testPrimaryCurrencyFiltersByPricesCurrency(): void
    {
        $this->seedPrice('price_gbp', true, 'gbp', 'one_time');
        $this->seedPrice('price_usd', true, 'usd', 'one_time');

        $stripeIds = array_map(
            fn(Price $price) => $price->stripeId,
            Price::find()->primaryCurrency('gbp')->all()
        );

        $this->assertContains('price_gbp', $stripeIds);
        $this->assertNotContains('price_usd', $stripeIds);
    }

    public function testTypeFiltersRecurringPrices(): void
    {
        $this->seedPrice('price_recurring', true, 'usd', 'recurring');
        $this->seedPrice('price_onetime', true, 'usd', 'one_time');

        $stripeIds = array_map(
            fn(Price $price) => $price->stripeId,
            Price::find()->type(PriceType::Recurring)->all()
        );

        $this->assertContains('price_recurring', $stripeIds);
        $this->assertNotContains('price_onetime', $stripeIds);
    }

    public function testPrimaryOwnerIdReturnsPricesOwnedByProduct(): void
    {
        $this->seedPrice('price_owned', true, 'usd', 'one_time');

        $stripeIds = array_map(
            fn(Price $price) => $price->stripeId,
            Price::find()->primaryOwnerId($this->product->id)->all()
        );

        $this->assertContains('price_owned', $stripeIds);
    }

    private function seedPrice(string $stripeId, bool $active, string $currency, string $type): void
    {
        $stripePrice = StripePrice::constructFrom([
            'id' => $stripeId,
            'active' => $active,
            'currency' => $currency,
            'type' => $type,
            'unit_amount' => 1000,
            'product' => $this->product->stripeId,
            'recurring' => $type === 'recurring' ? ['interval' => 'month', 'interval_count' => 1] : null,
        ]);

        $this->assertTrue(Plugin::getInstance()->getPrices()->createOrUpdatePrice($stripePrice));
    }
}
