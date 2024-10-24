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
use yii\web\Response as YiiResponse;

/**
 * The SyncController handles syncing data from Stripe.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class SyncController extends Controller
{
    public function actionAll(): YiiResponse
    {
        Plugin::getInstance()->getProducts()->syncAllProducts();
        Plugin::getInstance()->getPrices()->syncAllPrices();
        Plugin::getInstance()->getSubscriptions()->syncAllSubscriptions();
        Plugin::getInstance()->getCustomers()->syncAllCustomers();
        Plugin::getInstance()->getPaymentMethods()->syncAllPaymentMethods();
        Plugin::getInstance()->getInvoices()->syncAllInvoices();

        return $this->asSuccess(Craft::t('stripe', 'Stripe Products, Prices, Subscriptions, Customers, Invoices and Payment Methods successfully synced'));
    }

    /**
     * @return YiiResponse
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCustomer(): YiiResponse
    {
        $stripeIds = Craft::$app->getRequest()->getRequiredParam('stripeIds');
        foreach (explode(',', $stripeIds) as $stripeId) {
            $stripeCustomer = Plugin::getInstance()->getApi()->fetchCustomerById($stripeId);
            Plugin::getInstance()->getCustomers()->createOrUpdateCustomer($stripeCustomer);
        }

        return $this->asSuccess(Craft::t('stripe', 'Stripe Customers successfully synced'));
    }
}
