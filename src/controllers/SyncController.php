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
use craft\web\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use yii\web\Response as YiiResponse;


/**
 * The WebhookController handles Stripe webhook event.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class SyncController extends Controller
{
    public function actionAll(): YiiResponse
    {
        Plugin::getInstance()->getProducts()->syncAllProducts();

        return $this->asSuccess(Craft::t('stripe', 'Stripe Products, Prices, Subscriptions, Customers, Invoices and Payment Methods successfully synced'));
    }
}
