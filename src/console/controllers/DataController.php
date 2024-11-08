<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\ActiveRecord;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\records\CustomerData;
use craft\stripe\records\InvoiceData;
use craft\stripe\records\PaymentMethodData;
use Exception;
use yii\console\ExitCode;

/**
 * Data controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.2.1
 */
class DataController extends Controller
{
    public $defaultAction = 'reset';

    /**
     * Deletes all Stripe plugin data.
     *
     * @return int
     * @throws \Throwable
     */
    public function actionReset(): int
    {
        $this->stdout('Resetting Stripe plugin data will permanently delete all:' . PHP_EOL);
        $this->stdout('  > products' . PHP_EOL);
        $this->stdout('  > prices' . PHP_EOL);
        $this->stdout('  > subscriptions' . PHP_EOL);
        $this->stdout('  > customers' . PHP_EOL);
        $this->stdout('  > invoices' . PHP_EOL);
        $this->stdout('  > payment methods' . PHP_EOL);
        $this->stdout('from Craft. (Data in Stripe will not be affected.)' . PHP_EOL . PHP_EOL);

        if (!$this->confirm(
            'Do you wish to continue?'
        )) {
            return ExitCode::OK;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $this->deleteProductsAndPrices();
            $this->deleteSubscriptions();

            $nonElementData = [
                'customers' => [
                    'record' => CustomerData::class,
                    'label' => 'Customers',
                ],
                'invoices' => [
                    'record' => InvoiceData::class,
                    'label' => 'Invoices',
                ],
                'paymentMethods' => [
                    'record' => PaymentMethodData::class,
                    'label' => 'Payment Methods',
                ],
            ];

            foreach ($nonElementData as $item) {
                $this->stdout("  > Removing {$item['label']} ...");
                /** @var ActiveRecord $record */
                $record = $item['record'];
                if (Craft::$app->getDb()->tableExists($record::tableName())) {
                    Craft::$app->getDb()->createCommand()
                        ->delete($record::tableName())
                        ->execute();
                }
                $this->stdout(' done' . PHP_EOL, Console::FG_GREEN);
            }

            $this->stdout(PHP_EOL . 'Finished.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

            $transaction->commit();
        } catch (Exception $e) {
            $this->stdout($e->getmessage() . PHP_EOL, Console::FG_RED);
            $transaction->rollBack();
        }

        return ExitCode::OK;
    }

    /**
     * Deletes product and price elements and contents of the corresponding data tables
     *
     * @return void
     * @throws \Throwable
     */
    private function deleteProductsAndPrices(): void
    {
        $elementsService = Craft::$app->getElements();

        // delete all Products (which will delete all Prices too)
        $productQuery = Product::find()
            ->status(null)
            ->unique();

        $this->stdout('  > Removing Products and Prices ...');
        foreach (Db::each($productQuery) as $product) {
            $elementsService->deleteElement($product, true);
        }

        $this->stdout(' done' . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Deletes subscription elements and contents of the corresponding data table
     *
     * @return void
     * @throws \Throwable
     */
    private function deleteSubscriptions(): void
    {
        $elementsService = Craft::$app->getElements();

        $subscriptionQuery = Subscription::find()
            ->status(null)
            ->unique();

        $this->stdout('  > Removing Subscriptions ...');
        foreach (Db::each($subscriptionQuery) as $subscription) {
            $elementsService->deleteElement($subscription, true);
        }

        $this->stdout(' done' . PHP_EOL, Console::FG_GREEN);
    }
}
