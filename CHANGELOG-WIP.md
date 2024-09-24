# Release Notes for Stripe 1.2 (WIP)

## Unreleased

> [!NOTE]
> Stripe plugin now requires additional webhook events to be registered. Re-register the webhook handler in the plugin settings, or add the new events below to the webhook registration in Stripe.

- Webhook handler now listens for `customer.updated` events.
- It’s now possible to manually sync Stripe customer data on Edit User pages.
- Added support for selecting products in Link fields.
- Added `craft\stripe\events\StripeEvent`.
- Added `craft\stripe\services\Webhooks::EVENT_STRIPE_EVENT`.
- Added `craft\stripe\linktypes\Product`.
- Fixed a SQL error that occurred when syncing subscriptions that were missing a `latest_invoice` value.
- Fixed links to Stripe dashboard when in live mode.
- Stripe now requires Craft CMS 5.3.0 or later.
