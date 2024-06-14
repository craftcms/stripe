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

        // process line items
        $postLineItems = $request->getRequiredBodyParam('lineItems');
        $lineItems = collect($postLineItems)->filter(fn($item) => $item['quantity'] > 0)->all();

        if (empty($lineItems)) {
            return $this->asFailure(Craft::t('stripe', 'Please specify the quantity'));
        }

        $successUrl = $request->getValidatedBodyParam('successUrl');
        $cancelUrl = $request->getValidatedBodyParam('cancelUrl');
        $customer = $request->getBodyParam('customer');

        if ($customer == 'false' || $customer == '0' || $customer === false || $customer === 0) {
            // if customer was explicitly set to something falsy,
            // go with false to prevent trying to find the currently logged in user further down the line
            $customer = false;
        }

        // start checkout session
        $url = Plugin::getInstance()->getCheckout()->getCheckoutUrl($lineItems, $customer, $successUrl, $cancelUrl);

        return $this->redirect($url);
    }
}
