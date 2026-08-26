<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\events\StripePriceSyncEvent;
use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\services\Prices;
use craft\stripe\tests\TestCase;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use yii\base\Event;

class PricesServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_price_tests',
            'name' => 'Product for price tests',
            'active' => true,
        ]));
    }

    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);
        Event::off(Prices::class, Prices::EVENT_BEFORE_SYNCHRONIZE_PRICE);

        parent::tearDown();
    }

    public function testCreateOrUpdatePriceCreatesNewElement(): void
    {
        $this->assertTrue($this->syncPrice('price_new', true, 1000));

        $price = Price::find()->stripeId('price_new')->one();

        $this->assertNotNull($price);
        $this->assertSame(Price::STRIPE_STATUS_ACTIVE, $price->stripeStatus);
    }

    public function testCreateOrUpdatePriceUpdatesExistingElementInsteadOfDuplicating(): void
    {
        $this->syncPrice('price_update', true, 1000);
        $this->syncPrice('price_update', false, 2000);

        $prices = Price::find()->stripeId('price_update')->status(null)->all();

        $this->assertCount(1, $prices);
        $this->assertSame(Price::STRIPE_STATUS_ARCHIVED, $prices[0]->stripeStatus);
    }

    public function testCreateOrUpdatePriceLinksToOwningProduct(): void
    {
        $this->syncPrice('price_owned', true, 1000);

        $product = Product::find()->stripeId('prod_for_price_tests')->one();
        $price = Price::find()->stripeId('price_owned')->one();

        $this->assertSame($product->id, $price->getPrimaryOwnerId());
        $this->assertSame($product->id, $price->getOwnerId());
    }

    public function testCreateOrUpdatePriceRespectsBeforeSyncEventCancellation(): void
    {
        Event::on(
            Prices::class,
            Prices::EVENT_BEFORE_SYNCHRONIZE_PRICE,
            function(StripePriceSyncEvent $event) {
                $event->isValid = false;
            }
        );

        $result = $this->syncPrice('price_cancelled', true, 1000);

        $this->assertFalse($result);
        $this->assertNull(Price::find()->stripeId('price_cancelled')->status(null)->one());
    }

    public function testDeletePriceByStripeIdRemovesElement(): void
    {
        $this->syncPrice('price_delete', true, 1000);
        $this->assertNotNull(Price::find()->stripeId('price_delete')->status(null)->one());

        Plugin::getInstance()->getPrices()->deletePriceByStripeId('price_delete');

        $this->assertNull(Price::find()->stripeId('price_delete')->status(null)->one());
    }

    public function testSyncAllPricesCreatesAndRemovesOrphans(): void
    {
        $this->syncPrice('price_orphan', true, 1000);

        $api = $this->createMock(Api::class);
        $api->method('fetchAllPrices')->willReturn([
            StripePrice::constructFrom([
                'id' => 'price_from_stripe',
                'active' => true,
                'currency' => 'usd',
                'unit_amount' => 500,
                'product' => 'prod_for_price_tests',
            ]),
        ]);
        Plugin::getInstance()->set('api', $api);

        Plugin::getInstance()->getPrices()->syncAllPrices();

        $this->assertNotNull(Price::find()->stripeId('price_from_stripe')->one());
        $this->assertNull(Price::find()->stripeId('price_orphan')->status(null)->one());
    }

    private function syncPrice(string $stripeId, bool $active, int $unitAmount): bool
    {
        $stripePrice = StripePrice::constructFrom([
            'id' => $stripeId,
            'active' => $active,
            'currency' => 'usd',
            'unit_amount' => $unitAmount,
            'product' => 'prod_for_price_tests',
        ]);

        return Plugin::getInstance()->getPrices()->createOrUpdatePrice($stripePrice);
    }
}
