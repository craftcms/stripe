<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use Closure;
use CraftCms\Cms\Condition\Events\ConditionRulesResolving;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Events\ElementActionMenuItemsResolving;
use CraftCms\Cms\Element\Events\ElementMetadataResolving;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\LinkTypes\BaseLinkType;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Support\Facades\Twig;
use CraftCms\Cms\Support\Typecast;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Cms\Utility\Utility;
use CraftCms\Stripe\Console\RemoveDuplicatesCommand;
use CraftCms\Stripe\Console\Resave\ResavePricesCommand;
use CraftCms\Stripe\Console\Resave\ResaveProductsCommand;
use CraftCms\Stripe\Console\Resave\ResaveSubscriptionsCommand;
use CraftCms\Stripe\Console\ResetDataCommand;
use CraftCms\Stripe\Console\SyncCommand;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\FieldLayoutElements\PricesField;
use CraftCms\Stripe\Fields\Products as ProductsField;
use CraftCms\Stripe\Fields\Subscriptions as SubscriptionsField;
use CraftCms\Stripe\LinkTypes\Product as ProductLinkType;
use CraftCms\Stripe\Listeners\AddStripeUserScreen;
use CraftCms\Stripe\Listeners\AddUserConditionRules;
use CraftCms\Stripe\Listeners\AddUserCustomerMetadata;
use CraftCms\Stripe\Listeners\AddUserSyncAction;
use CraftCms\Stripe\Listeners\QueueNewUserCustomerSync;
use CraftCms\Stripe\Listeners\SyncChangedUserEmail;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Twig\Extension;
use CraftCms\Stripe\Utilities\Sync;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Stripe for Craft CMS.
 *
 * @method Settings getSettings()
 *
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '3.0.0';

    public string $minVersionRequired = '1.0.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    public bool $hasReadOnlyCpSettings = true;

    /** @var array<class-string<Element>> */
    protected array $elementTypes = [
        Product::class,
        Price::class,
        Subscription::class,
    ];

    /** @var array<class-string<FieldInterface>> */
    protected array $fieldTypes = [
        ProductsField::class,
        SubscriptionsField::class,
    ];

    /** @var array<class-string<BaseLinkType>> */
    protected array $linkTypes = [
        ProductLinkType::class,
    ];

    /** @var array<class-string<Utility>> */
    protected array $utilities = [
        Sync::class,
    ];

    /** @var list<class-string<Command>> */
    protected array $commands = [
        SyncCommand::class,
        ResetDataCommand::class,
        RemoveDuplicatesCommand::class,
        ResaveProductsCommand::class,
        ResavePricesCommand::class,
        ResaveSubscriptionsCommand::class,
    ];

    /** @var array<class-string, class-string|class-string[]> */
    protected array $events = [
        ElementSaving::class => SyncChangedUserEmail::class,
        ElementSaved::class => QueueNewUserCustomerSync::class,
        EditUserScreensResolving::class => AddStripeUserScreen::class,
        ElementActionMenuItemsResolving::class => AddUserSyncAction::class,
        ElementMetadataResolving::class => AddUserCustomerMetadata::class,
        ConditionRulesResolving::class => AddUserConditionRules::class,
    ];

    public function register(): void
    {
        // Stripe can’t send a CSRF token with webhook events
        $routes = $this->app->make(Routes::class);
        PreventRequestForgery::except([
            'stripe/webhooks/handle',
            $routes->actionTriggerUriPrefix().'/stripe/webhooks/handle',
        ]);
    }

    public function boot(): void
    {
        $this->app->singleton(Settings::class, fn () => $this->getSettings());

        Twig::registerExtension(new Extension);
        $this->registerVariableMacros();
        $this->registerUserMacros();

        if ($this->isInstalled) {
            $this->app->make(FieldLayouts::class)->registerProjectConfigListeners();
        }
    }

    #[Override]
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }

    #[Override]
    public function getSettingsResponse(): mixed
    {
        return redirect(Url::cpUrl('stripe/settings'));
    }

    #[Override]
    public function getReadOnlySettingsResponse(): mixed
    {
        return redirect(Url::cpUrl('stripe/settings'));
    }

    #[Override]
    protected function getNativeFields(): ?Closure
    {
        return function (FieldLayout $fieldLayout, array $fields): array {
            if ($fieldLayout->type === Product::class) {
                $fields[] = PricesField::class;
            }

            return $fields;
        };
    }

    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        $item = parent::getCpNavItem();

        if (! $item instanceof NavItem) {
            return $item;
        }

        $item->label(t('Stripe', category: 'stripe'));
        $item->add(new NavItem()->label(t('Products', category: 'stripe'))->url('stripe/products'));
        $item->add(new NavItem()->label(t('Subscriptions', category: 'stripe'))->url('stripe/subscriptions'));
        $item->add(new NavItem()->label(t('Invoices', category: 'stripe'))->url('stripe/invoices'));

        if (currentUser()?->isAdmin()) {
            $item->add(new NavItem()->label(t('Settings', category: 'stripe'))->url('stripe/settings'));
            $item->add(new NavItem()->label(t('Webhooks', category: 'stripe'))->url('stripe/webhooks'));
        }

        return $item;
    }

    /**
     * Returns the Stripe API service, for `craft.stripe.api` in Twig.
     */
    public function getApi(): Api
    {
        return $this->app->make(Api::class);
    }

    public function getBillingPortal(): BillingPortal
    {
        return $this->app->make(BillingPortal::class);
    }

    public function getCheckout(): Checkout
    {
        return $this->app->make(Checkout::class);
    }

    public function getCustomers(): Customers
    {
        return $this->app->make(Customers::class);
    }

    public function getInvoices(): Invoices
    {
        return $this->app->make(Invoices::class);
    }

    public function getPaymentMethods(): PaymentMethods
    {
        return $this->app->make(PaymentMethods::class);
    }

    public function getPrices(): Prices
    {
        return $this->app->make(Prices::class);
    }

    public function getProducts(): Products
    {
        return $this->app->make(Products::class);
    }

    public function getSubscriptions(): Subscriptions
    {
        return $this->app->make(Subscriptions::class);
    }

    public function getWebhooks(): Webhooks
    {
        return $this->app->make(Webhooks::class);
    }

    /**
     * `craft.stripe`, `craft.stripeProducts()`, `craft.stripePrices()` and `craft.stripeSubscriptions()`.
     */
    private function registerVariableMacros(): void
    {
        $plugin = $this;

        CraftVariable::macro('stripe', fn () => $plugin);

        foreach ([
            'stripeProducts' => Product::class,
            'stripePrices' => Price::class,
            'stripeSubscriptions' => Subscription::class,
        ] as $name => $elementType) {
            CraftVariable::macro($name, function (array $criteria = []) use ($elementType) {
                $query = $elementType::find();
                Typecast::configure($query, $criteria);

                return $query;
            });
        }
    }

    /**
     * The Stripe methods users had in Craft 5, such as `currentUser.getStripeCustomers()`.
     */
    private function registerUserMacros(): void
    {
        self::userMacro('getStripeCustomers', function (): Collection {
            return collect(app(Customers::class)->getCustomersByEmail($this->email));
        });

        self::userMacro('getStripeCustomer', function (): ?Customer {
            return app(Customers::class)->getFirstCustomerByEmail($this->email);
        });

        self::userMacro('getStripeSubscriptions', function (): Collection {
            return $this->email ? Subscription::find()->userEmail($this->email)->collect() : collect();
        });

        self::userMacro('getStripePaymentMethods', function (): Collection {
            return collect(app(PaymentMethods::class)->getPaymentMethodsByEmail($this->email));
        });

        self::userMacro('getStripeBillingPortalSessionUrl', function (?string $returnUrl = null, ?string $configurationId = null, array $params = []): ?string {
            $customer = app(Customers::class)->getFirstCustomerByEmail($this->email);

            return $customer
                ? app(BillingPortal::class)->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, $params)
                : null;
        });

        self::userMacro('getStripeBillingPortalSessionPaymentMethodUpdateUrl', function (?string $returnUrl = null, ?string $configurationId = null, array $params = []): ?string {
            $customer = app(Customers::class)->getFirstCustomerByEmail($this->email);

            return $customer
                ? app(BillingPortal::class)->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, array_replace_recursive([
                    'flow_data' => ['type' => 'payment_method_update'],
                ], $params))
                : null;
        });
    }

    /**
     * Registers a User macro; the closure runs bound to the user.
     *
     * @param-closure-this User $macro
     */
    private static function userMacro(string $name, Closure $macro): void
    {
        User::macro($name, $macro);
    }
}
