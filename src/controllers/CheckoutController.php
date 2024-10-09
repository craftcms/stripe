<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\web\Controller;
use Stripe\Checkout\Session as StripeCheckoutSession;
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
        $checkoutService = Plugin::getInstance()->getCheckout();

        // process line items
        $postLineItems = $request->getRequiredBodyParam('lineItems');
        $lineItems = collect($postLineItems)->filter(fn($item) => $item['quantity'] > 0)->all();

        if (empty($lineItems)) {
            return $this->asFailure(Craft::t('stripe', 'Please specify the quantity'));
        }

        $successUrl = $request->getValidatedBodyParam('successUrl');
        $cancelUrl = $request->getValidatedBodyParam('cancelUrl');
        $customer = $request->getBodyParam('customer');
        // we're intentionally not passing any params from the form,
        // so that you can't change what you get checked out with;
        // you can pass params via EVENT_BEFORE_START_CHECKOUT_SESSION event

        if ($customer == 'false' || $customer == '0' || $customer === false || $customer === 0) {
            // if customer was explicitly set to something falsy,
            // go with false to prevent trying to find the currently logged in user further down the line
            $customer = false;
        }

        $params = [];
        $fields = $request->getBodyParam('fields');
        if (!empty($fields)) {
            // check the checkout mode - if it's subscription, proceed with creating a draft
            $mode = $checkoutService->getCheckoutMode($lineItems);
            if ($mode === StripeCheckoutSession::MODE_SUBSCRIPTION) {
                // create an unpublished & unsaved draft subscription in Craft;
                $subscription = Craft::createObject([
                    'class' => Subscription::class,
                    'attributes' => ['title' => DateTimeHelper::now()->format('Y-m-d H:i:s')],
                ]);
                $subscription->setFieldValuesFromRequest('fields');
                if (Craft::$app->getDrafts()->saveElementAsDraft($subscription, markAsSaved: false)) {
                    // send the uid of it to Stripe to be stored as metadata on the Session(!)
                    $params['metadata']['craftSubscriptionUid'] = $subscription->uid;
                }
            }
        }

        // start checkout session
        $url = $checkoutService->getCheckoutUrl($lineItems, $customer, $successUrl, $cancelUrl, $params);

        return $this->redirect($url);
    }
}
