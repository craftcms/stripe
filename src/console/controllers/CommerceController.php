<?php

namespace craft\stripe\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\commerce\elements\Subscription as CommerceSubscription;
use craft\helpers\StringHelper;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use yii\console\ExitCode;

/**
 * Commerce controller
 */
class CommerceController extends Controller
{
    public $defaultAction = 'migrate';

    /**
     * stripe/commerce/migrate command
     */
    public function actionMigrate(): int
    {
        $commercePlugin = Craft::$app->getPlugins()->getPlugin('commerce');
        if (!$commercePlugin || !$commercePlugin->isInstalled) {
            $this->stderr("Craft Commerce Plugin must be installed and enabled for the migration to proceed.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Starting to migrate data from Craft Commerce…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $fieldsService = Craft::$app->getFields();
        // get craft\commerce\elements\Subscription field layout and save a copy as craft\stripe\elements\Subscription
        $layout = $fieldsService->getLayoutByType(CommerceSubscription::class);
        if ($layout->id !== null) {
            $newLayout = clone $layout;
            $newLayout->id = null;
            $newLayout->uid = StringHelper::UUID();
            $newLayout->type = Subscription::class;
            if (!$fieldsService->saveLayout($newLayout, false)) {
                $this->stderr("Unable to duplicate field layout.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        // migrate subscriptions
        $this->migrateSubscriptionData();

        $this->stdout('Finished migrating data from Craft Commerce…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    private function migrateSubscriptionData(): void
    {
        // get all subscriptions from commerce
        $commerceSubscriptions = CommerceSubscription::find()
            ->status(null)
            ->with(['subscriber'])
            ->all();

        foreach ($commerceSubscriptions as $commerceSubscription) {
            $customerId = $commerceSubscription->getSubscriptionData()['customer'];

            // find this subscription in the Stripe data
            /** @var Subscription $stripeSubscription */
            $stripeSubscription = new Subscription();
            $stripeSubscription->dateCreated = $commerceSubscription->dateCreated;
            $stripeSubscription->stripeId = $commerceSubscription->reference;
            $data = $commerceSubscription->getFieldValues();
            $stripeSubscription->setFieldValues($data);

            try {
                Craft::$app->getElements()->saveElement($stripeSubscription, false);
            } catch (\Exception $e) {
                $t = 1;
            }
        }
    }
}
