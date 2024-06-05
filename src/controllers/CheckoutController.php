<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * The CheckoutController handles creating a Stripe checkout session
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CheckoutController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public $defaultAction = 'checkout';

    /**
     * @return Response
     * @throws \Throwable
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionCheckout(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $currentUser = Craft::$app->getUser()->getIdentity();

        // process line items
        $postLineItems = $request->getRequiredBodyParam('lineItems');
        $lineItems = collect($postLineItems)->filter(fn($item) => $item['quantity'] > 0)->all();

        if (empty($lineItems)) {
            return $this->asFailure(Craft::t('stripe', 'Please specify the quantity'));
        }

        $successUrl = $request->getValidatedBodyParam('successUrl');
        $cancelUrl = $request->getValidatedBodyParam('cancelUrl');

        // start checkout session
        $url = Plugin::getInstance()->getCheckout()->getCheckoutUrl($lineItems, $currentUser, $successUrl, $cancelUrl);

        return $this->redirect($url);
    }
}
