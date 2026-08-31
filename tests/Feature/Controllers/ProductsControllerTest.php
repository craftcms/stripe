<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Controllers;

use Craft;
use craft\stripe\controllers\ProductsController;
use craft\stripe\elements\Product;
use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\web\ForbiddenHttpException;

class ProductsControllerTest extends TestCase
{
    public function testRenderMetaCardHtmlReturnsProductCard(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_controller_test', ['name' => 'Controller Test Product'])
        );
        $product = Product::find()->stripeId('prod_controller_test')->one();

        Craft::$app->getRequest()->setQueryParams(['id' => $product->id]);

        $controller = new ProductsController('products', Craft::$app);
        $html = $controller->runAction('render-meta-card-html');

        $this->assertIsString($html);
        $this->assertStringContainsString('Controller Test Product', $html);
        $this->assertStringContainsString($product->getStripeEditUrl(), $html);
    }

    public function testRenderMetaCardHtmlRequiresPermission(): void
    {
        $this->expectException(ForbiddenHttpException::class);

        $controller = new ProductsController('products', Craft::$app);
        $controller->runAction('render-meta-card-html');
    }
}
