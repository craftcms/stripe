<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\elements\Product;
use craft\stripe\helpers\Product as ProductHelper;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * The ProductsController handles listing and showing Stripe products elements.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ProductsController extends Controller
{
    /**
     * Displays the product index page.
     *
     * @return Response
     */
    public function actionProductIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $dashboardUrl = $plugin->dashboardUrl;
        $mode = $plugin->stripeMode;

        $newProductUrl = "{$dashboardUrl}/{$mode}/products/create";

        return $this->renderTemplate('stripe/products/_index', compact('newProductUrl'));
    }

    /**
     * Renders the meta card HTML that shows when editing Product element in the CP.
     *
     * @return string
     */
    public function actionRenderMetaCardHtml(): string
    {
        $id = (int)Craft::$app->request->getParam('id');
        /** @var Product $product */
        $product = Product::find()->id($id)->status(null)->one();
        return ProductHelper::renderCardHtml($product);
    }
}
