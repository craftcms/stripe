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
use yii\web\ForbiddenHttpException;
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
        // user can do a full sync if they have permissions to access the sync all utility
        if (!Craft::$app->getUser()->checkPermission('utility:stripe-sync-all')) {
            throw new ForbiddenHttpException('User is not authorized to perform this action.');
        }

        // TODO: This will likely time out if you have a lot of data, maybe we look to move all this into the SyncData job.

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
        if (!Craft::$app->getUser()->checkPermission('accessPlugin-stripe')) {
            throw new ForbiddenHttpException('User is not authorized to perform this action.');
        }

        // TODO: Look to allow this to use the new SyncSingleCustomerData job in future?

        $stripeIds = Craft::$app->getRequest()->getRequiredParam('stripeIds');
        foreach (explode(',', $stripeIds) as $stripeId) {
            $stripeCustomer = Plugin::getInstance()->getApi()->fetchCustomerById($stripeId);
            Plugin::getInstance()->getCustomers()->createOrUpdateCustomer($stripeCustomer);
        }

        return $this->asSuccess(Craft::t('stripe', 'Stripe Customers successfully synced'));
    }
}
