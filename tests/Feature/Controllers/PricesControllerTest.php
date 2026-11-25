<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Controllers;

use Craft;
use craft\stripe\controllers\PricesController;
use craft\stripe\elements\Price;
use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\web\ForbiddenHttpException;

class PricesControllerTest extends TestCase
{
    public function testRenderMetaCardHtmlReturnsPriceCard(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_price_controller_test', ['name' => 'Product for price controller test'])
        );
        Plugin::getInstance()->getPrices()->createOrUpdatePrice(
            StripeApiObjectFactory::price('price_controller_test', 'prod_for_price_controller_test')
        );
        $price = Price::find()->stripeId('price_controller_test')->one();

        Craft::$app->getRequest()->setQueryParams(['id' => $price->id]);

        $controller = new PricesController('prices', Craft::$app);
        $html = $controller->runAction('render-meta-card-html');

        $this->assertIsString($html);
        $this->assertStringContainsString($price->getStripeEditUrl(), $html);
    }

    public function testRenderMetaCardHtmlRequiresPermission(): void
    {
        $this->expectException(ForbiddenHttpException::class);

        $controller = new PricesController('prices', Craft::$app);
        $controller->runAction('render-meta-card-html');
    }
}
