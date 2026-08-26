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
use craft\stripe\tests\TestCase;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use yii\web\ForbiddenHttpException;

class PricesControllerTest extends TestCase
{
    public function testRenderMetaCardHtmlReturnsPriceCard(): void
    {
        $this->loginAsAdmin();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_price_controller_test',
            'name' => 'Product for price controller test',
            'active' => true,
        ]));
        Plugin::getInstance()->getPrices()->createOrUpdatePrice(StripePrice::constructFrom([
            'id' => 'price_controller_test',
            'active' => true,
            'currency' => 'usd',
            'unit_amount' => 1000,
            'product' => 'prod_for_price_controller_test',
            'created' => 1700000000,
            'currency_options' => [],
            'recurring' => null,
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'metadata' => [],
        ]));
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
