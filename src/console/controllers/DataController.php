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
use craft\stripe\records\SubscriptionData;
use Exception;
use yii\console\ExitCode;

/**
 * Data controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.3.0
 */
class DataController extends Controller
{
    public $defaultAction = 'reset';

    /**
     * @var bool Whether to run in dry-run mode (no deletions)
     */
    public bool $dryRun = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'remove-duplicates') {
            $options[] = 'dryRun';
        }

        return $options;
    }

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

    /**
     * Finds and removes duplicate subscription elements.
     *
     * When duplicate subscriptions exist for the same Stripe subscription ID,
     * this command will keep the most recently updated one and delete the others.
     * If duplicates have different custom field content, it will prompt for confirmation.
     *
     * @return int
     */
    public function actionRemoveDuplicates(): int
    {
        $this->stdout('Scanning for duplicate subscription elements...' . PHP_EOL . PHP_EOL);

        // Find all subscriptions grouped by stripeId
        $allSubscriptions = Subscription::find()
            ->status(null)
            ->orderBy(['stripeId' => SORT_ASC, 'dateUpdated' => SORT_DESC])
            ->all();

        // Group by stripeId
        $groupedByStripeId = [];
        foreach ($allSubscriptions as $subscription) {
            if ($subscription->stripeId === null) {
                continue;
            }
            $groupedByStripeId[$subscription->stripeId][] = $subscription;
        }

        // Filter to only duplicates
        $duplicateGroups = array_filter($groupedByStripeId, fn($group) => count($group) > 1);

        if (empty($duplicateGroups)) {
            $this->stdout('No duplicate subscriptions found.' . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout('Found ' . count($duplicateGroups) . ' Stripe subscription(s) with duplicate elements.' . PHP_EOL . PHP_EOL);

        $totalDeleted = 0;
        $elementsService = Craft::$app->getElements();

        foreach ($duplicateGroups as $stripeId => $subscriptions) {
            $this->stdout("Stripe ID: $stripeId (" . count($subscriptions) . " elements)" . PHP_EOL, Console::FG_YELLOW);

            // The first one is the most recently updated (due to ordering)
            $keeper = array_shift($subscriptions);
            $toDelete = $subscriptions;

            // Check if custom field content differs
            $keeperFieldValues = $keeper->getSerializedFieldValues();

            $hasDifferentContent = false;
            foreach ($toDelete as $duplicate) {
                $duplicateFieldValues = $duplicate->getSerializedFieldValues();
                if ($keeperFieldValues !== $duplicateFieldValues) {
                    $hasDifferentContent = true;
                    break;
                }
            }

            if ($hasDifferentContent) {
                $this->stdout('  Duplicates have different custom field content!' . PHP_EOL, Console::FG_RED);
                $this->stdout(PHP_EOL);

                // Display options
                $options = [];
                $allElements = array_merge([$keeper], $toDelete);
                foreach ($allElements as $index => $sub) {
                    $fieldValues = $sub->getSerializedFieldValues();
                    $fieldDisplay = empty($fieldValues) ? '(no custom fields)' : json_encode($fieldValues, JSON_UNESCAPED_SLASHES);
                    $options[$index] = sprintf(
                        'ID: %d | Updated: %s | Fields: %s',
                        $sub->id,
                        $sub->dateUpdated->format('Y-m-d H:i:s'),
                        $fieldDisplay
                    );
                    $this->stdout("  [$index] {$options[$index]}" . PHP_EOL);
                }
                $this->stdout(PHP_EOL);

                if ($this->dryRun) {
                    $this->stdout('  [DRY RUN] Would prompt for which element to keep.' . PHP_EOL, Console::FG_CYAN);
                    continue;
                }

                $choice = $this->select('Which element should be kept?', $options);

                // Rebuild keeper and toDelete based on choice
                $keeper = $allElements[$choice];
                $toDelete = array_filter($allElements, fn($_, $idx) => $idx !== (int)$choice, ARRAY_FILTER_USE_BOTH);
            } else {
                $this->stdout(sprintf(
                    '  Keeping: ID %d (updated: %s)' . PHP_EOL,
                    $keeper->id,
                    $keeper->dateUpdated->format('Y-m-d H:i:s')
                ));
            }

            // Delete duplicates
            foreach ($toDelete as $duplicate) {
                $this->stdout(sprintf(
                    '  Deleting: ID %d (updated: %s)' . PHP_EOL,
                    $duplicate->id,
                    $duplicate->dateUpdated->format('Y-m-d H:i:s')
                ), Console::FG_RED);

                if (!$this->dryRun) {
                    // Re-point any SubscriptionData records to the keeper before deleting,
                    // so the CASCADE delete doesn't wipe the stripe data
                    SubscriptionData::updateAll(
                        ['subscriptionId' => $keeper->id],
                        ['subscriptionId' => $duplicate->id],
                    );

                    $elementsService->deleteElement($duplicate, true);
                }
                $totalDeleted++;
            }

            $this->stdout(PHP_EOL);
        }

        if ($this->dryRun) {
            $this->stdout("[DRY RUN] Would have deleted $totalDeleted duplicate element(s)." . PHP_EOL, Console::FG_CYAN);
        } else {
            $this->stdout("Deleted $totalDeleted duplicate element(s)." . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
