<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\elements\Price;
use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * The PricesController handles listing and showing Stripe prices elements.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PricesController extends Controller
{
//    /**
//     * Displays the price index page.
//     *
//     * @return Response
//     */
//    public function actionProductIndex(): Response
//    {
//        $plugin = Plugin::getInstance();
//        $dashboardUrl = $plugin->dashboardUrl;
//        $mode = $plugin->stripeMode;
//
//        $newProductUrl = "{$dashboardUrl}/{$mode}/prices/create";
//
//        return $this->renderTemplate('stripe/prices/_index', compact('newProductUrl'));
//    }

    /**
     * Renders the meta card HTML that shows when editing Product element in the CP.
     *
     * @return string
     */
    public function actionRenderMetaCardHtml(): string
    {
        $id = (int)Craft::$app->request->getParam('id');
        /** @var Price $price */
        $price = Price::find()->id($id)->status(null)->one();
        return PriceHelper::renderCardHtml($price);
    }
}
