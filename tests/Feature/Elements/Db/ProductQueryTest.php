<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Elements\Db;

use craft\stripe\elements\Product;
use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;

class ProductQueryTest extends TestCase
{
    public function testStatusLiveReturnsOnlyActiveProducts(): void
    {
        $this->seedProduct('prod_active_1', true);
        $this->seedProduct('prod_archived_1', false);

        $results = Product::find()->status('live')->all();

        $stripeIds = array_map(fn(Product $product) => $product->stripeId, $results);
        $this->assertContains('prod_active_1', $stripeIds);
        $this->assertNotContains('prod_archived_1', $stripeIds);
    }

    public function testStatusStripeArchivedReturnsOnlyArchivedProducts(): void
    {
        $this->seedProduct('prod_active_2', true);
        $this->seedProduct('prod_archived_2', false);

        $results = Product::find()->status('stripeArchived')->all();

        $stripeIds = array_map(fn(Product $product) => $product->stripeId, $results);
        $this->assertContains('prod_archived_2', $stripeIds);
        $this->assertNotContains('prod_active_2', $stripeIds);
    }

    public function testStripeIdFindsTheRightProduct(): void
    {
        $this->seedProduct('prod_findme', true);

        $result = Product::find()->stripeId('prod_findme')->one();

        $this->assertNotNull($result);
        $this->assertSame('prod_findme', $result->stripeId);
    }

    private function seedProduct(string $stripeId, bool $active): void
    {
        $stripeProduct = StripeApiObjectFactory::product($stripeId, ['active' => $active]);

        $this->assertTrue(Plugin::getInstance()->getProducts()->createOrUpdateProduct($stripeProduct));
    }
}
