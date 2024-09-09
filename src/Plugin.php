<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\controllers\UsersController;
use craft\elements\conditions\users\UserCondition;
use craft\elements\User;
use craft\enums\MenuItemType;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DefineMenuItemsEvent;
use craft\events\DefineMetadataEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Html;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\records\User as UserRecord;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\elements\conditions\users\HasStripeCustomerConditionRule;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\fieldlayoutelements\PricesField;
use craft\stripe\fields\Products as ProductsField;
use craft\stripe\jobs\SyncData;
use craft\stripe\models\Settings;
use craft\stripe\services\Api;
use craft\stripe\services\BillingPortal;
use craft\stripe\services\Checkout;
use craft\stripe\services\Customers;
use craft\stripe\services\Invoices;
use craft\stripe\services\PaymentMethods;
use craft\stripe\services\Prices;
use craft\stripe\services\Products;
use craft\stripe\services\Subscriptions;
use craft\stripe\services\Webhooks;
use craft\stripe\utilities\Sync;
use craft\stripe\web\twig\CraftVariableBehavior;
use craft\stripe\web\twig\Extension;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;

/**
 * Stripe plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
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
    public string $schemaVersion = '1.0.1';

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
    public string $minVersionRequired = '1.0.0';

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
                'billingPortal' => ['class' => BillingPortal::class],
                'checkout' => ['class' => Checkout::class],
                'customers' => ['class' => Customers::class],
                'invoices' => ['class' => Invoices::class],
                'prices' => ['class' => Prices::class],
                'products' => ['class' => Products::class],
                'subscriptions' => ['class' => Subscriptions::class],
                'paymentMethods' => ['class' => PaymentMethods::class],
                'webhooks' => ['class' => Webhooks::class],
            ],
        ];
    }

    public function init()
    {
        parent::init();

        // we need to register the behavior as soon as possible
        $this->registerBehaviors();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $request = Craft::$app->getRequest();

            $this->registerElementTypes();
            $this->registerUtilityTypes();
            $this->registerUserEditScreens();
            $this->registerFieldTypes();
            $this->registerFieldLayoutElements();
            $this->registerVariables();
            $this->registerTwigExtension();
            $this->registerResaveCommands();
            $this->registerConditionRules();
            $this->registerUserActions();
            $this->handleUserElementChanges();

            if (!$request->getIsConsoleRequest()) {
                if ($request->getIsCpRequest()) {
                    $this->registerCpRoutes();
                } else {
                    $this->registerSiteRoutes();
                }
            }

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

            // get stripe environment from the secret key
            $this->stripeMode = $this->getStripeMode();
            $this->stripeBaseUrl = $this->dashboardUrl . ($this->stripeMode == 'test' ? '/test' : '');
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
     * Returns the billing portal service
     *
     * @return BillingPortal The billing portal service
     * @throws InvalidConfigException
     */
    public function getBillingPortal(): BillingPortal
    {
        return $this->get('billingPortal');
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
     * Returns the Webhooks service
     *
     * @return Webhooks The Webhooks service
     * @throws InvalidConfigException
     */
    public function getWebhooks(): Webhooks
    {
        return $this->get('webhooks');
    }

    /**
     * Returns the Checkout service
     *
     * @return Checkout The Checkout service
     * @throws InvalidConfigException
     */
    public function getCheckout(): Checkout
    {
        return $this->get('checkout');
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

    /**
     * Registers the utilities.
     */
    private function registerUtilityTypes(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Sync::class;
            }
        );
    }

    /**
     * Register the element types supplied by Stripe
     *
     * @return void
     */
    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Product::class;
            $event->types[] = Price::class;
            $event->types[] = Subscription::class;
        });
    }

    private function registerUserEditScreens(): void
    {
        Event::on(UsersController::class, UsersController::EVENT_DEFINE_EDIT_SCREENS, function(DefineEditUserScreensEvent $event) {
            $event->screens['stripe'] = [
                'label' => Craft::t('stripe', 'Stripe'),
            ];
        });

        Event::on(User::class, User::EVENT_DEFINE_METADATA, function(DefineMetadataEvent $event) {
            $event->metadata[Craft::t('stripe', 'Stripe Customer(s)')] = function() use ($event) {
                return Html::beginTag('div') .
                    $event->sender->getStripeCustomers()->reduce(function($carry, $item) {
                        $carry = is_string($carry) ? $carry : '';
                        $carry .=
                            Html::beginTag('div') .
                            Html::tag(
                                'a',
                                $item->data['name'] . ' (' . $item->stripeId . ')' . Html::tag('span', '', ['data-icon' => 'external']),
                                ['href' => $item->getStripeEditUrl(), 'target' => '_blank']
                            ) .
                            Html::endTag('div');

                        return $carry;
                    }) .
                    Html::endTag('div');
            };
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
    private function registerFieldLayoutElements(): void
    {
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

            $event->rules['stripe/products'] = 'stripe/products/index';
            $event->rules['stripe/products/<elementId:\\d+>'] = 'elements/edit';

            $event->rules['stripe/subscriptions'] = 'stripe/subscriptions/index';
            $event->rules['stripe/subscriptions/<elementId:\\d+>'] = 'elements/edit';

            $event->rules['stripe/invoices'] = 'stripe/invoices/index';

            $event->rules['myaccount/stripe'] = 'stripe/customers/index';
            $event->rules['users/<userId:\\d+>/stripe'] = 'stripe/customers/index';

            $event->rules['stripe/webhooks'] = 'stripe/webhooks/edit';
        });
    }

    /**
     * Registers the Site routes.
     */
    private function registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['stripe/webhooks/handle'] = 'stripe/webhooks/handle';
        });
    }

    /**
     * @return void
     */
    private function registerBehaviors(): void
    {
        Event::on(
            User::class,
            User::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['stripe:customer'] = StripeCustomerBehavior::class;
            }
        );
    }

    /**
     * @return void
     */
    private function registerConditionRules(): void
    {
        Event::on(
            UserCondition::class,
            UserCondition::EVENT_REGISTER_CONDITION_RULES,
            function(RegisterConditionRulesEvent $event) {
                $event->conditionRules[] = HasStripeCustomerConditionRule::class;
            }
        );
    }

    private function registerUserActions(): void
    {
        Event::on(
            User::class,
            Element::EVENT_DEFINE_ACTION_MENU_ITEMS,
            function(DefineMenuItemsEvent $event) {
                $sender = $event->sender;
                if ($email = $sender->email) {
                    $customers = Plugin::getInstance()->getApi()->fetchAllCustomers(['email' => $email]);
                    if ($customers) {
                        $stripeIds = collect($customers)->pluck('id');
                        $event->items[] = [
                            'action' => 'stripe/sync/customer',
                            'type' => MenuItemType::Button,
                            'params' => [
                                'stripeIds' => $stripeIds->toArray(),
                            ],
                            'label' => Craft::t('stripe', 'Sync Customer from Stripe'),
                        ];
                    }
                }
            }
        );
    }

    /**
     * Maybe sync changed user email from Craft to Stripe.
     *
     * @return void
     */
    private function handleUserElementChanges(): void
    {
        // if email address got changed - update stripe
        Event::on(UserRecord::class, UserRecord::EVENT_BEFORE_UPDATE, function(ModelEvent $event) {
            $userRecord = $event->sender;
            /** @var User|StripeCustomerBehavior $user */
            $user = Craft::$app->getUsers()->getUserById($userRecord->id);
            $settings = $this->getSettings();
            if ($user->isCredentialed && $settings['syncChangedUserEmailsToStripe']) {
                $oldEmail = $userRecord->getOldAttribute('email');
                $newEmail = $userRecord->getAttribute('email');
                if ($oldEmail != $newEmail) {
                    $customers = $user->getStripeCustomers();
                    if ($customers->isNotEmpty()) {
                        $client = $this->getApi()->getClient();
                        foreach ($customers->all() as $customer) {
                            $client->customers->update($customer->stripeId, ['email' => $newEmail]);
                        }
                    }
                }
            }
        });

        // if user is saved, and they have an email address and exist in stripe, but we don't have their stripe customer data
        // kick off queue job to sync customer-related data
        Event::on(User::class, User::EVENT_AFTER_SAVE, function(ModelEvent $event) {
            /** @var User|StripeCustomerBehavior $user */
            $user = $event->sender;

            // Do they have an email at all?
            if (empty($user->email)) {
                return;
            }

            // Do we have any existing Stripe customer records for them?
            if (!$user->getStripeCustomers()->isEmpty()) {
                return;
            }

            // Search for customer in Stripe by their email address:
            try {
                // If the plugin isn't configured yet, this may fail:
                $stripe = $this->getApi()->getClient();
                $stripeCustomers = $stripe->customers->search(['query' => "email:'{$user->email}'"]);

                // If we found Stripe customers with that email address, kick off the queue job to sync data:
                if (!$stripeCustomers->isEmpty()) {
                    Queue::push(new SyncData());
                }
            } catch (\Stripe\Exception\ExceptionInterface $e) {
                Craft::error("Tried to synchronize user data, but the plugin was not fully configured: {$e->getMessage()}", 'stripe');
            }
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

        if (Craft::$app->getUser()->getIsAdmin()) {
            $ret['subnav']['stripeWebhooks'] = [
                'label' => Craft::t('stripe', 'Webhooks'),
                'url' => 'stripe/webhooks',
            ];
        }


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
