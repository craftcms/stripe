# Release Notes for Stripe

## Unreleased

> [!NOTE]
> Stripe plugin now requires additional webhook events to be registered. Re-register the webhook handler in the plugin settings, or add the new events below to the webhook registration in Stripe.

- Webhook handler now listens for `customer.updated` events. ([#21](https://github.com/craftcms/stripe/pull/21))
- It’s now possible to manually sync Stripe customer data on Edit User pages. ([#21](https://github.com/craftcms/stripe/pull/21))
- Added support for selecting products in Link fields. ([#26](https://github.com/craftcms/stripe/pull/26))
- Added the “Stripe Subscriptions” field type. ([#32](https://github.com/craftcms/stripe/pull/32))
- It’s now possible to save custom field data against a new Subscription when using a checkout form. ([#25](https://github.com/craftcms/stripe/issues/25))
- Admin privileges (but not admin changes) are now required when creating, editing, updating and deleting webhook. ([#30](https://github.com/craftcms/stripe/pull/30))
- Webhook settings are now stored in a separate table and not in the plugin’s settings. ([#30](https://github.com/craftcms/stripe/pull/30))
- Added `craft\stripe\events\StripeEvent`. ([#17](https://github.com/craftcms/stripe/issues/17))
- Added `craft\stripe\services\Webhooks::EVENT_STRIPE_EVENT`. ([#17](https://github.com/craftcms/stripe/issues/17))
- Added `craft\stripe\linktypes\Product`. ([#26](https://github.com/craftcms/stripe/pull/26))
- Deprecated `craft\stripe\models\Settings->$webhookSigningSecret` ([#30](https://github.com/craftcms/stripe/pull/30))
- Deprecated `craft\stripe\models\Settings->$webhookId` ([#30](https://github.com/craftcms/stripe/pull/30))
- Added `craft\stripe\fields\Subscriptions`. ([#32](https://github.com/craftcms/stripe/pull/32))
- Fixed a SQL error that occurred when syncing a subscriptions that were missing a `latest_invoice` value. ([#21](https://github.com/craftcms/stripe/pull/21))
- Fixed links to stripe dashboard when in live mode. ([#21](https://github.com/craftcms/stripe/pull/21))
- Fixed an error that could occur when syncing Customer and Payment Method data. ([#29](https://github.com/craftcms/stripe/pull/29))
- Fixed an error that could occur when sorting Invoices table by certain columns. ([#31](https://github.com/craftcms/stripe/pull/31))
- Stripe now requires Craft CMS 5.3.0 or later. ([#26](https://github.com/craftcms/stripe/pull/26))

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
