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
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\base\Event;

class PricesServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_price_tests', ['name' => 'Product for price tests'])
        );
    }

    protected function tearDown(): void
    {
        Event::off(Prices::class, Prices::EVENT_BEFORE_SYNCHRONIZE_PRICE);

        parent::tearDown();
    }

    public function testCreateOrUpdatePriceCreatesNewElement(): void
    {
        $this->assertTrue($this->syncPrice('price_new', ['active' => true]));

        $price = Price::find()->stripeId('price_new')->one();

        $this->assertNotNull($price);
        $this->assertSame(Price::STRIPE_STATUS_ACTIVE, $price->stripeStatus);
    }

    public function testCreateOrUpdatePriceUpdatesExistingElementInsteadOfDuplicating(): void
    {
        $this->syncPrice('price_update', ['active' => true]);
        $this->syncPrice('price_update', ['active' => false, 'unit_amount' => 2000]);

        $prices = Price::find()->stripeId('price_update')->status(null)->all();

        $this->assertCount(1, $prices);
        $this->assertSame(Price::STRIPE_STATUS_ARCHIVED, $prices[0]->stripeStatus);
    }

    public function testCreateOrUpdatePriceLinksToOwningProduct(): void
    {
        $this->syncPrice('price_owned');

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

        $result = $this->syncPrice('price_cancelled');

        $this->assertFalse($result);
        $this->assertNull(Price::find()->stripeId('price_cancelled')->status(null)->one());
    }

    public function testDeletePriceByStripeIdRemovesElement(): void
    {
        $this->syncPrice('price_delete');
        $this->assertNotNull(Price::find()->stripeId('price_delete')->status(null)->one());

        Plugin::getInstance()->getPrices()->deletePriceByStripeId('price_delete');

        $this->assertNull(Price::find()->stripeId('price_delete')->status(null)->one());
    }

    public function testSyncAllPricesCreatesAndRemovesOrphans(): void
    {
        $this->syncPrice('price_orphan');

        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchAllPrices')->willReturn([
            StripeApiObjectFactory::price('price_from_stripe', 'prod_for_price_tests', ['unit_amount' => 500]),
        ]);

        Plugin::getInstance()->getPrices()->syncAllPrices();

        $this->assertNotNull(Price::find()->stripeId('price_from_stripe')->one());
        $this->assertNull(Price::find()->stripeId('price_orphan')->status(null)->one());
    }

    private function syncPrice(string $stripeId, array $overrides = []): bool
    {
        return Plugin::getInstance()->getPrices()->createOrUpdatePrice(
            StripeApiObjectFactory::price($stripeId, 'prod_for_price_tests', $overrides)
        );
    }
}
