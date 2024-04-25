<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\stripe\elements\Product;
use craft\stripe\models\Settings;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * The SettingsController handles modifying and saving the general settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class SettingsController extends Controller
{
    /**
     * Display a form to allow an administrator to update plugin's API settings.
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionIndex(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }
        $tabs = [
            'apiConnection' => [
                'label' => Craft::t('stripe', 'API Connection'),
                'url' => '#api',
            ],
            'products' => [
                'label' => Craft::t('stripe', 'Products'),
                'url' => '#products',
            ],
            'prices' => [
                'label' => Craft::t('stripe', 'Prices'),
                'url' => '#prices',
            ],
            'subscriptions' => [
                'label' => Craft::t('stripe', 'Subscriptions'),
                'url' => '#subscriptions',
            ],
        ];
        $selectedTab = 'apiConnection';

        return $this->renderTemplate('stripe/settings/index', compact('settings', 'tabs', 'selectedTab'));
    }

    /**
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $routingSettings = Craft::$app->getRequest()->getParam('routingSettings');
        $plugin = Plugin::getInstance();

        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();

        if (isset($routingSettings['routing'])) {
            $originalUriFormat = $pluginSettings->productUriFormat;

            // Remove from editable table namespace
            $settings['productUriFormat'] = $routingSettings['routing']['productUriFormat'];
            // Could be blank if in headless mode
            if (isset($settings['routing']['productTemplate'])) {
                $settings['productTemplate'] = $routingSettings['routing']['productTemplate'];
            }
        }

        $settingsSuccess = true;
        if ($settings !== null) {
            $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);
        }

        $projectConfig = Craft::$app->getProjectConfig();

        // products field layout
        $productsLayout = Craft::$app->getFields()->assembleLayoutFromPost('products-layout');
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $productsLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe product field layout');

        $pluginSettings->setProductFieldLayout($productsLayout);

        // prices field layout
        $pricesLayout = Craft::$app->getFields()->assembleLayoutFromPost('prices-layout');
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $pricesLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_PRICE_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe price field layout');

        $pluginSettings->setPriceFieldLayout($pricesLayout);

        // subscriptions field layout
        $subscriptionsLayout = Craft::$app->getFields()->assembleLayoutFromPost('subscriptions-layout');
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $subscriptionsLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe price field layout');

        $pluginSettings->setSubscriptionFieldLayout($subscriptionsLayout);

        if (!$settingsSuccess) {
            return $this->asModelFailure(
                $pluginSettings,
                Craft::t('stripe', 'Couldn’t save settings.'),
                'settings',
            );
        }

        // Resave all products if the URI format changed
        if (isset($originalUriFormat) && $originalUriFormat != $settings['productUriFormat']) {
            Craft::$app->getQueue()->push(new ResaveElements([
                'elementType' => Product::class,
                'criteria' => [
                    'siteId' => '*',
                    'unique' => true,
                    'status' => null,
                ],
            ]));
        }

        return $this->asModelSuccess(
            $pluginSettings,
            Craft::t('stripe', 'Settings saved.'),
            'settings',
        );
    }
}
