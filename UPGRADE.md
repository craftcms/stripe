# Upgrading to Stripe 2.0 (Craft 6)

Stripe 2.0 is a native Craft 6 plugin. This guide lists what changed from 1.x and what to update in your project.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- The `craftcms/yii2-adapter` package is not needed.

## Upgrade steps

1. If you still need to move subscriptions over from Craft Commerce, run `craft stripe/commerce/migrate` with Stripe 1.x on Craft 5 first. Stripe 2.0 doesn’t include that command.
2. Upgrade your project to Craft 6.
3. Update the plugin: `composer require craftcms/stripe:^2.0`.
4. Run `php artisan craft:up`. It runs the plugin’s migrations, which replace the `craft\stripe\*` class names stored in the database and project config, and read the subscription billing period from its new place in the Stripe data.
5. Move `config/stripe.php`, if you have one, to `config/craft/stripe.php`.
6. Update your PHP code as described below. Twig templates keep working, except where they read stored Stripe data that changed shape in the newer Stripe API version (see below).

Your existing `stripe_*` tables, elements, field layouts, settings and webhook record are kept. The webhook URL (`stripe/webhooks/handle`) is the same, so the endpoint registered in Stripe keeps working.

## Breaking changes

### Stripe API version

Stripe 2.0 uses Stripe API version `2026-01-28.clover` instead of `2024-04-10`. Stripe data that the plugin syncs from now on has the newer shape. For example, a subscription’s `current_period_start` and `current_period_end` are now on its items (`data.items.data[0].current_period_end`). Use `subscription.getCurrentPeriodStart()` and `subscription.getCurrentPeriodEnd()` instead of reading them from `subscription.data`. If your code reads other stored Stripe data, or handles Stripe events, review the [Stripe API changelog](https://docs.stripe.com/changelog) for the changes since `2024-04-10`.

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\stripe\Plugin` | `CraftCms\Stripe\Plugin` |
| `craft\stripe\models\Settings` | `CraftCms\Stripe\Settings` |
| `craft\stripe\services\Api` | `CraftCms\Stripe\Api` |
| `craft\stripe\services\BillingPortal` | `CraftCms\Stripe\BillingPortal` |
| `craft\stripe\services\Checkout` | `CraftCms\Stripe\Checkout` |
| `craft\stripe\services\Customers` | `CraftCms\Stripe\Customers` |
| `craft\stripe\services\Invoices` | `CraftCms\Stripe\Invoices` |
| `craft\stripe\services\PaymentMethods` | `CraftCms\Stripe\PaymentMethods` |
| `craft\stripe\services\Prices` | `CraftCms\Stripe\Prices` |
| `craft\stripe\services\Products` | `CraftCms\Stripe\Products` |
| `craft\stripe\services\Subscriptions` | `CraftCms\Stripe\Subscriptions` |
| `craft\stripe\services\Webhooks` | `CraftCms\Stripe\Webhooks` |
| `craft\stripe\elements\Product` | `CraftCms\Stripe\Elements\Product` |
| `craft\stripe\elements\Price` | `CraftCms\Stripe\Elements\Price` |
| `craft\stripe\elements\Subscription` | `CraftCms\Stripe\Elements\Subscription` |
| `craft\stripe\elements\db\ProductQuery` | `CraftCms\Stripe\Elements\Queries\ProductQuery` |
| `craft\stripe\elements\db\PriceQuery` | `CraftCms\Stripe\Elements\Queries\PriceQuery` |
| `craft\stripe\elements\db\SubscriptionQuery` | `CraftCms\Stripe\Elements\Queries\SubscriptionQuery` |
| `craft\stripe\elements\conditions\*\*` | `CraftCms\Stripe\Elements\Conditions\*` |
| `craft\stripe\fields\Products` | `CraftCms\Stripe\Fields\Products` |
| `craft\stripe\fields\Subscriptions` | `CraftCms\Stripe\Fields\Subscriptions` |
| `craft\stripe\fieldlayoutelements\PricesField` | `CraftCms\Stripe\FieldLayoutElements\PricesField` |
| `craft\stripe\linktypes\Product` | `CraftCms\Stripe\LinkTypes\Product` |
| `craft\stripe\models\Customer` | `CraftCms\Stripe\Models\Customer` (Eloquent) |
| `craft\stripe\models\Invoice` | `CraftCms\Stripe\Models\Invoice` (Eloquent) |
| `craft\stripe\models\PaymentMethod` | `CraftCms\Stripe\Models\PaymentMethod` (Eloquent) |
| `craft\stripe\records\*` | `CraftCms\Stripe\Models\*` (Eloquent) |
| `craft\stripe\db\Table` | `CraftCms\Stripe\Database\Table` |
| `craft\stripe\enums\PriceType` | `CraftCms\Stripe\Enums\PriceType` |
| `craft\stripe\helpers\Price` | `CraftCms\Stripe\Support\PriceFormatter` |
| `craft\stripe\jobs\SyncSingleCustomerData` | `CraftCms\Stripe\Jobs\SyncCustomerData` |
| `craft\stripe\utilities\Sync` | `CraftCms\Stripe\Utilities\Sync` |

The migration updates class names stored in the database and project config, including element sources and stored conditions, so you only need to update your own code.

These were removed:

- `craft\stripe\behaviors\StripeCustomerBehavior`. The `User` methods it added are now macros with the same names (see below).
- `craft\stripe\jobs\SyncData`. The **Stripe Sync All** utility syncs in the request; use `php artisan stripe:sync` for large accounts.
- `craft\stripe\helpers\Customer`, `Product`, `Subscription` and `Db`. Use `Api::dashboardUrl()` for Stripe Dashboard links.
- The Feed Me field classes (`craft\stripe\feedme\fields\*`), until Feed Me supports Craft 6.
- The `stripe/commerce/migrate` command (see the upgrade steps).
- The `stripecp` asset bundle.

### Services

The plugin no longer has `api`, `products`, `subscriptions`, etc. components. Resolve the services from the container, or use the plugin’s getters, which are unchanged:

```php
// Before
Plugin::getInstance()->getSubscriptions()->cancelSubscriptionByStripeId($id);

// After
app(\CraftCms\Stripe\Subscriptions::class)->cancelSubscriptionByStripeId($id);
// or
\CraftCms\Stripe\Plugin::getInstance()->getSubscriptions()->cancelSubscriptionByStripeId($id);
```

### Events

Events are Laravel events now. Listen for the event class instead of a class and event name:

| Before | After |
| --- | --- |
| `Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT` | `CraftCms\Stripe\Events\ProductSyncing` |
| `Products::EVENT_AFTER_SYNCHRONIZE_PRODUCT` | `CraftCms\Stripe\Events\ProductSynced` |
| `Prices::EVENT_BEFORE_SYNCHRONIZE_PRICE` | `CraftCms\Stripe\Events\PriceSyncing` |
| `Prices::EVENT_AFTER_SYNCHRONIZE_PRICE` | `CraftCms\Stripe\Events\PriceSynced` |
| `Subscriptions::EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION` | `CraftCms\Stripe\Events\SubscriptionSyncing` |
| `Subscriptions::EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION` | `CraftCms\Stripe\Events\SubscriptionSynced` |
| `Checkout::EVENT_BEFORE_START_CHECKOUT_SESSION` | `CraftCms\Stripe\Events\CheckoutSessionCreating` |
| `BillingPortal::EVENT_BEFORE_START_BILLING_PORTAL_SESSION` | `CraftCms\Stripe\Events\BillingPortalSessionCreating` |
| `Webhooks::EVENT_STRIPE_EVENT` | `CraftCms\Stripe\Events\StripeEventReceived` |

The events have the same properties as before (`$element` and `$source`, `$params`, or `$stripeEvent`). Set `$event->isValid = false` on a `*Syncing` event to skip saving the element.

```php
// Before
Event::on(Products::class, Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT, function (StripeProductSyncEvent $event) {
    // ...
});

// After
use CraftCms\Stripe\Events\ProductSyncing;
use Illuminate\Support\Facades\Event;

Event::listen(function (ProductSyncing $event) {
    // ...
});
```

### Console commands

| Before | After |
| --- | --- |
| `craft stripe/sync/all` | `php artisan stripe:sync` |
| `craft stripe/sync/products-and-prices` | `php artisan stripe:sync products-and-prices` |
| `craft stripe/sync/subscriptions` | `php artisan stripe:sync subscriptions` |
| `craft stripe/sync/customers` | `php artisan stripe:sync customers` |
| `craft stripe/sync/customer <id>` | `php artisan stripe:sync --customer=<id>` |
| `craft stripe/sync/payment-methods` | `php artisan stripe:sync payment-methods` |
| `craft stripe/sync/invoices` | `php artisan stripe:sync invoices` |
| `craft stripe/data/reset` | `php artisan stripe:reset-data` |
| `craft stripe/data/remove-duplicates --dryRun` | `php artisan stripe:remove-duplicates --dry-run` |
| `craft resave/stripe-products` | `php artisan craft:resave:stripe-products` |
| `craft resave/stripe-prices` | `php artisan craft:resave:stripe-prices` |
| `craft resave/stripe-subscriptions` | `php artisan craft:resave:stripe-subscriptions` |

### Templates

Twig templates work as before: `craft.stripe`, `craft.stripeProducts()`, `craft.stripePrices()`, `craft.stripeSubscriptions()`, the `unitPrice`, `pricePerUnit`, `unitAmount` and `interval` filters, the `stripeCheckoutUrl()` function, and the `currentUser.getStripeCustomers()`, `getStripeCustomer()`, `getStripeSubscriptions()`, `getStripePaymentMethods()`, `getStripeBillingPortalSessionUrl()` and `getStripeBillingPortalSessionPaymentMethodUpdateUrl()` methods.

### Routes and action URLs

The `stripe/checkout` and `stripe/webhooks/handle` action URLs keep working, as do the control panel URLs. The control panel actions (`stripe/subscriptions/cancel`, `stripe/sync/*`, `stripe/settings/*`, `stripe/webhooks/*`) require a logged-in user with access to the plugin, and the settings and webhook ones require an admin.

### Database and migrations

The 1.x migrations were folded into the install migration, so update to the latest Stripe 1.x release before upgrading.
