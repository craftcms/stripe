<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\console\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Subscription as CommerceSubscription;
use craft\commerce\Plugin as CommercePlugin;
use craft\console\Controller;
use craft\errors\DeprecationException;
use craft\helpers\Console;
use craft\helpers\StringHelper;
use craft\stripe\elements\Subscription;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;

/**
 * Commerce controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
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

        if (empty($this->_stripeCommerceGatewayIds())) {
            $this->stderr("No Stripe Commerce gateways found.\n", Console::FG_RED);
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
        $this->_migrateSubscriptionData();

        $this->stdout('Finished migrating data from Craft Commerce. Please run the stripe/sync/all command…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * @return array
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    private function _stripeCommerceGatewayIds(): array
    {
        // Get all Stripe gateways
        $gateways = CommercePlugin::getInstance()->getGateways()->getAllGateways();

        return $gateways->filter(function(Gateway $gateway) {
            return $gateway::class == 'craft\commerce\stripe\gateways\PaymentIntents';
        })->map(function(Gateway $gateway) {
            return $gateway->id;
        })->all();
    }

    /**
     * @return void
     * @throws \Throwable
     */
    private function _migrateSubscriptionData(): void
    {
        $gatewayIds = $this->_stripeCommerceGatewayIds();

        // get all subscriptions from commerce
        $commerceSubscriptions = CommerceSubscription::find()
            ->status(null)
            ->gatewayId(array_values($gatewayIds))
            ->with(['subscriber'])
            ->all();

        foreach ($commerceSubscriptions as $commerceSubscription) {
            // Check to see if we have already migrated this subscription
            if (Subscription::find()->stripeId($commerceSubscription->reference)->exists()) {
                continue;
            }

            // find this subscription in the Stripe data
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
