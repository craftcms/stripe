<?php

namespace craft\stripe\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\stripe\elements\Product;
use craft\stripe\Plugin;
use yii\console\ExitCode;

/**
 * Sync controller
 */
class SyncController extends Controller
{
    public $defaultAction = 'products';

    /**
     * stripe/sync command
     */
    public function actionProducts(): int
    {
        $this->stdout('Syncing Stripe products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $start = microtime(true);
        Plugin::getInstance()->getProducts()->syncAllProducts();
        $time = microtime(true) - $start;

        $this->stdout('Finished syncing ' . Product::find()->count() . ' product(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }
}
