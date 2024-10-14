# Release Notes for Stripe

## Unreleased

> [!NOTE]
> Stripe plugin now requires additional webhook events to be registered. Re-register the webhook handler in the plugin settings, or add the new events below to the webhook registration in Stripe.

- Webhook handler now listens for `customer.updated` events.
- It’s now possible to manually sync Stripe customer data on Edit User pages.
- It’s now possible to save custom field data against a new Subscription when using a checkout form.
- Added `craft\stripe\events\StripeEvent`.
- Added `craft\stripe\services\Webhooks::EVENT_STRIPE_EVENT`.
- Fixed a SQL error that occurred when syncing a subscriptions that were missing a `latest_invoice` value.
- Fixed links to stripe dashboard when in live mode.

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
