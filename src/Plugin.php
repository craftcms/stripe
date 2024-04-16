<?php

namespace craft\stripe;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\controllers\UsersController;
use craft\elements\User;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\Fields;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\fieldlayoutelements\PricesField;
use craft\stripe\fields\Products as ProductsField;
use craft\stripe\models\Settings;
use craft\stripe\services\Api;
use craft\stripe\services\Customers;
use craft\stripe\services\Invoices;
use craft\stripe\services\PaymentMethods;
use craft\stripe\services\Prices;
use craft\stripe\services\Products;
use craft\stripe\services\Subscriptions;
use craft\stripe\services\Webhook;
use craft\stripe\web\twig\CraftVariableBehavior;
use craft\stripe\web\twig\Extension;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Stripe plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Pixel & Tonic <support@craftcms.com>
 * @copyright Pixel & Tonic
 * @license MIT
 * @property-read Products $products
 * @property-read Api $api
 */
class Plugin extends BasePlugin
{
    public const PC_PATH_PRODUCT_FIELD_LAYOUTS = 'stripe.productFieldLayout';
    public const PC_PATH_PRICE_FIELD_LAYOUTS = 'stripe.priceFieldLayout';
    public const PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS = 'stripe.subscriptionFieldLayout';

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '5.0.0';

    /**
     * @var string stripe environment determined from the key
     * fall back to test environment to be safe
     */
    public string $stripeMode = 'test';

    /**
     * @var string Stripe Dashboard URL
     */
    public string $dashboardUrl = 'https://dashboard.stripe.com';

    /**
     * @var string Stripe Base URL for all external links to Stripe
     */
    public string $stripeBaseUrl;

    public static function config(): array
    {
        return [
            'components' => [
                'api' => ['class' => Api::class],
                'customers' => ['class' => Customers::class],
                'invoices' => ['class' => Invoices::class],
                'prices' => ['class' => Prices::class],
                'products' => ['class' => Products::class],
                'subscriptions' => ['class' => Subscriptions::class],
                'paymentMethods' => ['class' => PaymentMethods::class],
                'webhook' => ['class' => Webhook::class],
            ],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $request = Craft::$app->getRequest();
            
            $this->registerElementTypes();
//            $this->registerUtilityTypes();
            $this->registerUserEditScreens();
            $this->registerFieldTypes();
            $this->registerFieldLayoutElements();
            $this->registerVariables();
            $this->registerTwigExtension();
            $this->registerResaveCommands();

            if (!$request->getIsConsoleRequest()) {
                if ($request->getIsCpRequest()) {
                    $this->registerCpRoutes();
                } else {
                    $this->registerSiteRoutes();
                }
            }
//
            $projectConfigService = Craft::$app->getProjectConfig();
            $productsService = $this->getProducts();
            $pricesService = $this->getPrices();
            $subscriptionService = $this->getSubscriptions();

            $projectConfigService->onAdd(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleChangedFieldLayout'])
                ->onUpdate(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleChangedFieldLayout'])
                ->onRemove(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleDeletedFieldLayout']);

            $projectConfigService->onAdd(self::PC_PATH_PRICE_FIELD_LAYOUTS, [$pricesService, 'handleChangedFieldLayout'])
                ->onUpdate(self::PC_PATH_PRICE_FIELD_LAYOUTS, [$pricesService, 'handleChangedFieldLayout'])
                ->onRemove(self::PC_PATH_PRICE_FIELD_LAYOUTS, [$pricesService, 'handleDeletedFieldLayout']);

            $projectConfigService->onAdd(self::PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS, [$subscriptionService, 'handleChangedFieldLayout'])
                ->onUpdate(self::PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS, [$subscriptionService, 'handleChangedFieldLayout'])
                ->onRemove(self::PC_PATH_SUBSCRIPTION_FIELD_LAYOUTS, [$subscriptionService, 'handleDeletedFieldLayout']);

//            // Globally register stripe webhooks registry event handlers
//            Registry::addHandler(Topics::PRODUCTS_CREATE, new ProductHandler());
//            Registry::addHandler(Topics::PRODUCTS_DELETE, new ProductHandler());
//            Registry::addHandler(Topics::PRODUCTS_UPDATE, new ProductHandler());
//            Registry::addHandler(Topics::INVENTORY_LEVELS_UPDATE, new ProductHandler());

            // get stripe environment from the secret key
            $this->stripeMode = $this->getStripeMode();
            $this->stripeBaseUrl = "$this->dashboardUrl/$this->stripeMode";
        });
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('stripe/settings'));
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('stripe/settings/index.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Returns the API service
     *
     * @return Api The API service
     * @throws InvalidConfigException
     */
    public function getApi(): Api
    {
        return $this->get('api');
    }

    /**
     * Returns the Prices service
     *
     * @return Prices The Prices service
     * @throws InvalidConfigException
     */
    public function getPrices(): Prices
    {
        return $this->get('prices');
    }

    /**
     * Returns the Products service
     *
     * @return Products The Products service
     * @throws InvalidConfigException
     */
    public function getProducts(): Products
    {
        return $this->get('products');
    }

    /**
     * Returns the Subscriptions service
     *
     * @return Subscriptions The Subscriptions service
     * @throws InvalidConfigException
     */
    public function getSubscriptions(): Subscriptions
    {
        return $this->get('subscriptions');
    }

    /**
     * Returns the Payment Methods service
     *
     * @return PaymentMethods The Payment Methods service
     * @throws InvalidConfigException
     */
    public function getPaymentMethods(): PaymentMethods
    {
        return $this->get('paymentMethods');
    }

    /**
     * Returns the Webhook service
     *
     * @return Webhook The Webhook service
     * @throws InvalidConfigException
     */
    public function getWebhook(): Webhook
    {
        return $this->get('webhook');
    }

    /**
     * Returns the Customers service
     *
     * @return Customers The Customers service
     * @throws InvalidConfigException
     */
    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    /**
     * Returns the Invoices service
     *
     * @return Invoices The Invoices service
     * @throws InvalidConfigException
     */
    public function getInvoices(): Invoices
    {
        return $this->get('invoices');
    }

//    /**
//     * Registers the utilities.
//     *
//     * @since 3.0
//     */
//    private function registerUtilityTypes(): void
//    {
//        Event::on(
//            Utilities::class,
//            Utilities::EVENT_REGISTER_UTILITIES,
//            function(RegisterComponentTypesEvent $event) {
//                $event->types[] = Sync::class;
//            }
//        );
//    }

    /**
     * Register the element types supplied by Stripe
     *
     * @return void
     */
    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = Product::class;
            $event->types[] = Price::class;
            $event->types[] = Subscription::class;
        });
    }

    private function registerUserEditScreens(): void
    {
        Event::on(UsersController::class, UsersController::EVENT_DEFINE_EDIT_SCREENS, function (DefineEditUserScreensEvent $event) {
            $event->screens['stripe'] = [
                'label' => Craft::t('stripe', 'Stripe'),
            ];
        });
    }

    /**
     * Register Field Types
     *
     * @return void
     */
    private function registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, static function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductsField::class;
        });
    }

    /**
     * Register field layout elements
     *
     * @return void
     */
    private function registerFieldLayoutElements(): void {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, function(DefineFieldLayoutFieldsEvent $event) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $event->sender;

            switch ($fieldLayout->type) {
                case Product::class:
                    $event->fields[] = PricesField::class;
                    break;
            }
        });
    }

    /**
     * Register Stripe twig variables to the main craft variable
     */
    private function registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
            $variable = $event->sender;
            $variable->attachBehavior('stripe', CraftVariableBehavior::class);
        });
    }

    /**
     * Register Stripe twig extension
     */
    private function registerTwigExtension(): void
    {
        if (!Craft::$app->getRequest()->getIsCpRequest()) {
            // Register the Twig extension
            Craft::$app->getView()->registerTwigExtension(new Extension());
        }
    }

    public function registerResaveCommands(): void
    {
        Event::on(ResaveController::class, Controller::EVENT_DEFINE_ACTIONS, static function(DefineConsoleActionsEvent $e) {
            $e->actions['stripe-products'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    return $controller->resaveElements(Product::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Stripe products.',
            ];

            $e->actions['stripe-prices'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    return $controller->resaveElements(Price::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Stripe prices.',
            ];

            $e->actions['stripe-subscriptions'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    return $controller->resaveElements(Subscription::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Stripe subscriptions.',
            ];
        });
    }

    /**
     * Register the CP routes
     *
     * @return void
     */
    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['stripe'] = ['template' => 'stripe/_index'];

            $event->rules['stripe/settings'] = 'stripe/settings';
            $event->rules['stripe/settings/products'] = 'stripe/settings/products';
            $event->rules['stripe/settings/prices'] = 'stripe/settings/prices';
            $event->rules['stripe/settings/subscriptions'] = 'stripe/settings/subscriptions';

            $event->rules['stripe/products'] = 'stripe/products/index';
            $event->rules['stripe/products/<elementId:\\d+>'] = 'elements/edit';

            $event->rules['stripe/subscriptions'] = 'stripe/subscriptions/index';
            $event->rules['stripe/subscriptions/<elementId:\\d+>'] = 'elements/edit';

            $event->rules['stripe/invoices'] = 'stripe/invoices/index';

            $event->rules['myaccount/stripe'] = 'stripe/customers/index';
            $event->rules['users/<userId:\\d+>/stripe'] = 'stripe/customers/index';

//            $event->rules['stripe/sync-products'] = 'stripe/products/sync';
//            $event->rules['stripe/webhooks'] = 'stripe/webhooks/edit';
        });
    }

    /**
     * Registers the Site routes.
     */
    private function registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['stripe/webhook/handle'] = 'stripe/webhook/handle';
        });
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('stripe', 'Stripe');


        $ret['subnav']['stripeProducts'] = [
            'label' => Craft::t('stripe', 'Products'),
            'url' => 'stripe/products',
        ];

        $ret['subnav']['stripeSubscriptions'] = [
            'label' => Craft::t('stripe', 'Subscriptions'),
            'url' => 'stripe/subscriptions',
        ];

        $ret['subnav']['stripeInvoices'] = [
            'label' => Craft::t('stripe', 'Invoices'),
            'url' => 'stripe/invoices',
        ];


        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $ret['subnav']['stripeSettings'] = [
                'label' => Craft::t('stripe', 'Settings'),
                'url' => 'stripe/settings',
            ];
        }

//        if (Craft::$app->getUser()->getIsAdmin()) {
//            $ret['subnav']['webhooks'] = [
//                'label' => Craft::t('stripe', 'Webhooks'),
//                'url' => 'stripe/webhooks',
//            ];
//        }


        return $ret;
    }

    /**
     * Get Stripe mode from the used secret key prefix.
     *
     * @return string
     */
    private function getStripeMode(): string
    {
        $secretKey = $this->getApi()->getApiKey();

        if (!str_starts_with($secretKey, 'sk_test_')) {
            return 'live';
        }

        return 'test';
    }
}
