<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\queue\jobs\ResaveElements;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
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
        $tabs = $this->getTabs();
        $selectedTab = 'apiConnection';

        return $this->renderTemplate('stripe/settings/index', compact('settings', 'tabs', 'selectedTab'));
    }

    /**
     * Display a form to allow an administrator to update plugin's Product settings.
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionProducts(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }
        $tabs = $this->getTabs();
        $selectedTab = 'products';

        return $this->renderTemplate('stripe/settings/products', compact('settings', 'tabs', 'selectedTab'));
    }

    /**
     * Display a form to allow an administrator to update plugin's Price settings.
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionPrices(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }
        $tabs = $this->getTabs();
        $selectedTab = 'prices';

        return $this->renderTemplate('stripe/settings/prices', compact('settings', 'tabs', 'selectedTab'));
    }

    /**
     * Display a form to allow an administrator to update plugin's Subscription settings.
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionSubscriptions(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }
        $tabs = $this->getTabs();
        $selectedTab = 'subscriptions';

        return $this->renderTemplate('stripe/settings/subscriptions', compact('settings', 'tabs', 'selectedTab'));
    }

    private function getTabs()
    {
        return [
            'apiConnection' => [
                'label' => Craft::t('stripe', 'API Connection'),
                'url' => UrlHelper::cpUrl('stripe/settings'),
            ],
            'products' => [
                'label' => Craft::t('stripe', 'Products'),
                'url' => UrlHelper::cpUrl('stripe/settings/products'),
            ],
            'prices' => [
                'label' => Craft::t('stripe', 'Prices'),
                'url' => UrlHelper::cpUrl('stripe/settings/prices'),
            ],
            'subscriptions' => [
                'label' => Craft::t('stripe', 'Subscriptions'),
                'url' => UrlHelper::cpUrl('stripe/settings/subscriptions'),
            ],
        ];
    }

    /**
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Plugin::getInstance();

        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();

        if (isset($settings['routing'])) {
            $originalUriFormat = $pluginSettings->productUriFormat;

            // Remove from editable table namespace
            $settings['productUriFormat'] = $settings['routing']['productUriFormat'];
            // Could be blank if in headless mode
            if (isset($settings['routing']['productTemplate'])) {
                $settings['productTemplate'] = $settings['routing']['productTemplate'];
            }
            unset($settings['routing']);
        }

        $settingsSuccess = true;
        if ($settings !== null) {
            $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);
        }

        if (Craft::$app->getRequest()->getBodyParam('fieldLayout')) {
            $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
            //$fieldLayout->type = Product::class;

            $projectConfig = Craft::$app->getProjectConfig();
            $uid = StringHelper::UUID();
            $fieldLayoutConfig = $fieldLayout->getConfig();

            if ($fieldLayout->type === Product::class) {
                $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe product field layout');
                $pluginSettings->setProductFieldLayout($fieldLayout);
            }

            if ($fieldLayout->type === Price::class) {
                $projectConfig->set(Plugin::PC_PATH_PRICE_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe price field layout');
                $pluginSettings->setPriceFieldLayout($fieldLayout);
            }

            if ($fieldLayout->type === Subscription::class) {
                $projectConfig->set(Plugin::PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Stripe subscription field layout');
                $pluginSettings->setSubscriptionFieldLayout($fieldLayout);
            }
        }

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
