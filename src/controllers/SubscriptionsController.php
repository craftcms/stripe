<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\elements\Subscription;
use craft\stripe\helpers\Subscription as SubscriptionHelper;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * The SubscriptionsController handles listing and showing Stripe subscription elements.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class SubscriptionsController extends Controller
{
    /**
     * Displays the product index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $dashboardUrl = $plugin->dashboardUrl;
        $mode = $plugin->stripeMode;

        $newSubscriptionUrl = "{$dashboardUrl}/{$mode}/subscriptions/create";

        return $this->renderTemplate('stripe/subscriptions/_index', compact('newSubscriptionUrl'));
    }

    /**
     * Renders the meta card HTML that shows when editing Subscription element in the CP.
     *
     * @return string
     */
    public function actionRenderMetaCardHtml(): string
    {
        $id = (int)Craft::$app->request->getParam('id');
        /** @var Subscription $product */
        $product = Subscription::find()->id($id)->status(null)->one();
        return SubscriptionHelper::renderCardHtml($product);
    }
}
