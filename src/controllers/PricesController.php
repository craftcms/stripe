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
use craft\web\Controller;

/**
 * The PricesController handles listing and showing Stripe prices elements.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PricesController extends Controller
{
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
