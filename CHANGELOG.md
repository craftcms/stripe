# Release Notes for Stripe

## 1.2.0.2 - 2024-11-08

- Fixed a PHP error that could occur when saving a new user.

## 1.2.0.1 - 2024-11-07

- Fixed an infinite loop that could occur when querying for a user.

## 1.2.0 - 2024-11-06

> [!NOTE]
> The plugin now requires the `customer.updated` webhook event to be registered. Update the webhook registration in Stripe, or re-register the webhook handler in the plugin settings.

- Stripe now requires Craft CMS 5.3.0 or later. ([#26](https://github.com/craftcms/stripe/pull/26))
- It’s now possible to manually sync Stripe customer data from Edit User pages (requires the `customer.updated` Stripe webhook event to be registered). ([#21](https://github.com/craftcms/stripe/pull/21))
- Added support for selecting Stripe products in Link fields. ([#26](https://github.com/craftcms/stripe/pull/26))
- Added the “Stripe Subscriptions” field type. ([#32](https://github.com/craftcms/stripe/pull/32))
- It’s now possible to set custom field values on subscriptions during checkout. ([#25](https://github.com/craftcms/stripe/issues/25))
- Webhook administration now requires an admin account. ([#30](https://github.com/craftcms/stripe/pull/30))
- Webhook settings are now stored in a dedicated database table rather than within the plugin’s settings. ([#30](https://github.com/craftcms/stripe/pull/30))
- Added `craft\stripe\events\StripeEvent`. ([#17](https://github.com/craftcms/stripe/issues/17))
- Added `craft\stripe\fields\Subscriptions`. ([#32](https://github.com/craftcms/stripe/pull/32))
- Added `craft\stripe\linktypes\Product`. ([#26](https://github.com/craftcms/stripe/pull/26))
- Added `craft\stripe\services\Webhooks::EVENT_STRIPE_EVENT`. ([#17](https://github.com/craftcms/stripe/issues/17))
- Deprecated `craft\stripe\models\Settings->$webhookId` ([#30](https://github.com/craftcms/stripe/pull/30))
- Deprecated `craft\stripe\models\Settings->$webhookSigningSecret` ([#30](https://github.com/craftcms/stripe/pull/30))
- Fixed a SQL error that occurred when syncing a subscription that didn’t have a `latest_invoice` value. ([#21](https://github.com/craftcms/stripe/pull/21))
- Fixed links to the Stripe dashboard when in live mode. ([#21](https://github.com/craftcms/stripe/pull/21))
- Fixed an error that could occur when syncing customer and payment method data. ([#29](https://github.com/craftcms/stripe/pull/29))
- Fixed an error that could occur when sorting invoices by certain columns. ([#31](https://github.com/craftcms/stripe/pull/31))
- Fixed an error that could occur when trying to view elements as cards. ([#15](https://github.com/craftcms/stripe/pull/15))

## 1.1.0 - 2024-06-14

- Improved the Webhooks settings screen messaging and error handling. ([#10](https://github.com/craftcms/stripe/pull/10))
- `craft\stripe\services\Checkout::getCheckoutUrl()` now accepts `false` passed to the `$user` argument, which will result in an anonymous checkout URL. ([#9](https://github.com/craftcms/stripe/pull/9))
- `craft\stripe\elements\Price::getCheckoutUrl()` now has `$customer`, `$successUrl`, `$cancelUrl`, and `$params` arguments. ([#9](https://github.com/craftcms/stripe/pull/9))
- Fixed a bug where the `stripe/checkout/checkout` action required an active session. ([#9](https://github.com/craftcms/stripe/pull/9))
- Fixed a Stripe API error that could occur. ([#9](https://github.com/craftcms/stripe/pull/9))
- Fixed a bug where CSRF validation wasn’t being enfonced for webhook CRUD actions. ([#10](https://github.com/craftcms/stripe/pull/10))
- Fixed a bug where the plugin wasn’t updatable. ([#11](https://github.com/craftcms/stripe/pull/11))

## 1.0.1 - 2024-05-07

- Fixed an error that could occur on the My Account page, due to a plugin conflict. ([#4](https://github.com/craftcms/stripe/issues/4))
- Fixed a SQL error that could occur on MariaDB. ([#5](https://github.com/craftcms/stripe/pull/5))

## 1.0.0 - 2024-04-30

- Initial release
