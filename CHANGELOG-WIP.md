# Stripe Changelog (WIP)

## 2.0.0

### Added
- Added `CraftCms\Stripe\Elements\Subscription::getCurrentPeriodStart()` and `getCurrentPeriodEnd()`.

### Changed
- Stripe now requires Craft CMS 6.0 or later and PHP 8.5 or later, and runs natively on Craft 6 without the `craftcms/yii2-adapter` package.
- The plugin’s classes moved to the `CraftCms\Stripe` namespace. A migration updates the class names stored in the database and project config. See [UPGRADE.md](UPGRADE.md).
- Plugin settings are now read from `config/craft/stripe.php`.
- Console commands are now Artisan commands: `stripe:sync`, `stripe:reset-data`, `stripe:remove-duplicates`, and `craft:resave:stripe-products`, `craft:resave:stripe-prices` and `craft:resave:stripe-subscriptions`.
- `stripe:sync` now asks what to sync when run interactively without a type. Non-interactive runs still sync everything.
- Plugin events are now Laravel events, such as `CraftCms\Stripe\Events\ProductSyncing` and `CraftCms\Stripe\Events\CheckoutSessionCreating`.
- Plugin services are now resolved from the container, such as `app(CraftCms\Stripe\Subscriptions::class)`.
- Updated Stripe API version from `2024-04-10` to `2026-01-28.clover`.
- The Subscription element’s current period and the `nextPaymentDate` query param now read the billing period from the subscription’s items, where Stripe API version `2025-03-31.basil` and later put it.

### Removed
- Removed the `stripe/commerce/migrate` command. Run it with Stripe 1.x on Craft 5 before upgrading.
- Removed the Feed Me field integrations.
