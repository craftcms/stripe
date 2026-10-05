<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\elements\Product;
use craft\stripe\events\StripeProductSyncEvent;
use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\services\Products;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\base\Event;

class ProductsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::off(Products::class, Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT);

        parent::tearDown();
    }

    public function testCreateOrUpdateProductCreatesNewElement(): void
    {
        $this->assertTrue($this->syncProduct('prod_new', ['name' => 'New Product', 'active' => true]));

        $product = Product::find()->stripeId('prod_new')->one();

        $this->assertNotNull($product);
        $this->assertSame('New Product', $product->title);
        $this->assertSame(Product::STRIPE_STATUS_ACTIVE, $product->stripeStatus);
    }

    public function testCreateOrUpdateProductUpdatesExistingElementInsteadOfDuplicating(): void
    {
        $this->syncProduct('prod_update', ['name' => 'Original Name', 'active' => true]);
        $this->syncProduct('prod_update', ['name' => 'Updated Name', 'active' => false]);

        $products = Product::find()->stripeId('prod_update')->status(null)->all();

        $this->assertCount(1, $products);
        $this->assertSame('Updated Name', $products[0]->title);
        $this->assertSame(Product::STRIPE_STATUS_ARCHIVED, $products[0]->stripeStatus);
    }

    public function testCreateOrUpdateProductRespectsBeforeSyncEventCancellation(): void
    {
        Event::on(
            Products::class,
            Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
            function(StripeProductSyncEvent $event) {
                $event->isValid = false;
            }
        );

        $result = $this->syncProduct('prod_cancelled', ['name' => 'Should Not Save']);

        $this->assertFalse($result);
        $this->assertNull(Product::find()->stripeId('prod_cancelled')->status(null)->one());
    }

    public function testDeleteProductByStripeIdRemovesElement(): void
    {
        $this->syncProduct('prod_delete');
        $this->assertNotNull(Product::find()->stripeId('prod_delete')->status(null)->one());

        Plugin::getInstance()->getProducts()->deleteProductByStripeId('prod_delete');

        $this->assertNull(Product::find()->stripeId('prod_delete')->status(null)->one());
    }

    public function testSyncAllProductsCreatesAndRemovesOrphans(): void
    {
        $this->syncProduct('prod_orphan', ['name' => 'Orphaned Product']);

        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchAllProducts')->willReturn([
            StripeApiObjectFactory::product('prod_from_stripe', ['name' => 'Fetched Product']),
        ]);

        Plugin::getInstance()->getProducts()->syncAllProducts();

        $this->assertNotNull(Product::find()->stripeId('prod_from_stripe')->one());
        $this->assertNull(Product::find()->stripeId('prod_orphan')->status(null)->one());
    }

    private function syncProduct(string $stripeId, array $overrides = []): bool
    {
        return Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product($stripeId, $overrides)
        );
    }
}
