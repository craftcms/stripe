<?php

namespace craft\stripe\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use yii\console\ExitCode;

/**
 * Sync controller
 */
class SyncController extends Controller
{
    public $defaultAction = 'products';

    /**
     * stripe/sync/products command
     */
    public function actionProducts(): int
    {
        $this->stdout('Syncing Stripe products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getProducts()->syncAllProducts();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Product::find()->count() . ' product(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);


        $this->stdout('Syncing Stripe prices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getPrices()->syncAllPrices();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Price::find()->count() . ' price(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

//    /**
//     * stripe/sync/prices command
//     */
//    public function actionPrices(): int
//    {
//        $this->stdout('Syncing Stripe prices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
//
//        $start = microtime(true);
//        Plugin::getInstance()->getPrices()->syncAllPrices();
//        $time = microtime(true) - $start;
//
//        $this->stdout('Finished syncing ' . Price::find()->count() . ' price(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
//        return ExitCode::OK;
//    }

    /**
     * stripe/sync/subscriptions command
     */
    public function actionSubscriptions(): int
    {
        $this->stdout('Syncing Stripe subscriptions…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getSubscriptions()->syncAllSubscriptions();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Subscription::find()->count() . ' subscription(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * stripe/sync/payment-methods command
     */
    public function actionPaymentMethods(): int
    {
        $this->stdout('Syncing Stripe payment methods…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getPaymentMethods()->syncAllPaymentMethods();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing payment method(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * stripe/sync/customers command
     */
    public function actionCustomers(): int
    {
        $this->stdout('Syncing Stripe customers…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getCustomers()->syncAllCustomers();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing customer(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * stripe/sync/invoices command
     */
    public function actionInvoices(): int
    {
        $this->stdout('Syncing Stripe invoices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getInvoices()->syncAllInvoices();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing invoice(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }
}
