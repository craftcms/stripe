<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\elements\Subscription;
use craft\stripe\elements\Subscription as SubscriptionElement;
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
        $newSubscriptionUrl = Plugin::getInstance()->stripeBaseUrl . "/subscriptions/create";

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

    public function actionCancel(): Response
    {
        $this->requirePostRequest();
        $stripeId = Craft::$app->getRequest()->getRequiredParam('stripeId');
        $immediately = (bool)Craft::$app->getRequest()->getBodyParam('immediately', false);

        $subscriptionElement = SubscriptionElement::find()->stripeId($stripeId)->one();
        if (!$subscriptionElement) {
            Craft::error("Cancel subscription - subscription element with Stripe ID {$stripeId} not found.", 'stripe');
            return $this->asFailure(Craft::t('app', 'Unable to cancel subscription.'));
        }

        if (!Plugin::getInstance()->getSubscriptions()->cancelSubscriptionByStripeId($subscriptionElement->stripeId, $immediately)) {
            return $this->asFailure(Craft::t('app', 'Unable to cancel subscription.'));
        }

        return $this->asSuccess(Craft::t('app', 'Subscription cancelled'));
    }
}
