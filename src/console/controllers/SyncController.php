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
    public $defaultAction = 'all';

    /**
     * stripe/sync/all command
     */
    public function actionAll(): int
    {
        $this->stdout('This command will sync Stripe Products, Prices, Subscriptions, Customers, Invoices and Payment Methods…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        if ($this->confirm('Do you want to continue?')) {
            $this->syncProducts();
            $this->syncPrices();
            $this->syncSubscriptions();
            $this->syncCustomers();
            $this->syncPaymentMethods();
            $this->syncInvoices();

            $this->stdout('Finished syncing all Stripe data…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * stripe/sync/products-and-prices command
     */
    public function actionProductsAndPrices(): int
    {
        $this->syncProducts();
        $this->syncPrices();

        return ExitCode::OK;
    }

    /**
     * stripe/sync/subscriptions command
     */
    public function actionSubscriptions(): int
    {
        $this->syncSubscriptions();

        return ExitCode::OK;
    }

    /**
     * stripe/sync/customers command
     */
    public function actionCustomers(): int
    {
        $this->syncCustomers();

        return ExitCode::OK;
    }

    /**
     * stripe/sync/payment-methods command
     */
    public function actionPaymentMethods(): int
    {
        $this->syncPaymentMethods();

        return ExitCode::OK;
    }


    /**
     * stripe/sync/invoices command
     */
    public function actionInvoices(): int
    {
        $this->syncInvoices();

        return ExitCode::OK;
    }

    /**
     * Sync Products from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncProducts(): void
    {
        $this->stdout('Syncing Stripe products and prices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getProducts()->syncAllProducts();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Product::find()->count() . ' product(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Sync Prices from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncPrices(): void
    {
        $this->stdout('Syncing Stripe prices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getPrices()->syncAllPrices();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Price::find()->count() . ' price(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Sync subscriptions from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncSubscriptions(): void
    {
        $this->stdout('Syncing Stripe subscriptions…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getSubscriptions()->syncAllSubscriptions();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Subscription::find()->count() . ' subscription(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Sync customers from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncCustomers(): void
    {
        $this->stdout('Syncing Stripe customers…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        $count = Plugin::getInstance()->getCustomers()->syncAllCustomers();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . $count . ' customer(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Sync payment methods from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncPaymentMethods(): void
    {
        $this->stdout('Syncing Stripe payment methods…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        $count = Plugin::getInstance()->getPaymentMethods()->syncAllPaymentMethods();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . $count . ' payment method(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Sync invoices from Stripe
     *
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    private function syncInvoices(): void
    {
        $this->stdout('Syncing Stripe invoices…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        $count = Plugin::getInstance()->getInvoices()->syncAllInvoices();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . $count . ' invoice(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }
}
