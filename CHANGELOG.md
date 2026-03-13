# Release Notes for Stripe

## 1.7.1 - 2026-01-19

- Fixed typo in the Readme file.
- Fixed XSS vulnerabilities.

## 1.7.0 - 2025-12-11

- Added the `stripe/data/remove-duplicates` command.
- Added the `stripe/sync/customer` command.
- Added `craft\stripe\jobs\SyncSingleCustomerData`.
- Added `craft\stripe\services\Invoices::syncCustomerInvoices()`.
- Added `craft\stripe\services\PaymentMethods::syncCustomerPaymentMethods()`.
- Added `craft\stripe\services\Subscriptions::syncCustomerSubscriptions()`.
- Fixed a bug where links to the Stripe dashboard weren’t always linking to the right URL. ([#100](https://github.com/craftcms/stripe/issues/100))
- Fixed a bug where it was possible to create multiple subscription elements for the same Stripe subscription. ([#101](https://github.com/craftcms/stripe/pull/101))

## 1.6.1 - 2025-12-01

- Fixed a bug where the invoice amount wasn’t formatted correctly for zero-decimal currencies. ([#96](https://github.com/craftcms/stripe/issues/96))
- `craft\stripe\elements\Product::getPrices()` now has an optional `$criteria` argument. ([#97](https://github.com/craftcms/stripe/issues/97))
- Fixed a bug where Stripe dashboard links didn't deep-link correctly for some accounts. ([#100](https://github.com/craftcms/stripe/issues/100))

## 1.6.0 - 2025-06-25

- The “Sync from Stripe” user action now shows a confirmation dialog before syncing customer data from Stripe. ([#87](https://github.com/craftcms/stripe/pull/87))
- Fixed a bug where duplicate subscriptions could be created. ([#44](https://github.com/craftcms/stripe/issues/44))
- Fixed a bug where products created via Stripe webhooks could be missing their price data. ([#92](https://github.com/craftcms/stripe/pull/92))

## 1.5.0 - 2025-04-08

- It’s now possible to resume subscriptions that are set to cancel at period end. ([#84](https://github.com/craftcms/stripe/pull/84))
- Fixed an error that could occur when editing a subscription if its corresponding product hadn’t been synced yet. ([#86](https://github.com/craftcms/stripe/pull/86))
- Fixed an XSS vulnerability.

## 1.4.0 - 2025-02-18

- Stripe now requires Craft CMS 5.6+.
- It’s now possible to view (but not edit) plugin settings on environments where `allowAdminChanges` is disabled. ([#78](https://github.com/craftcms/stripe/pull/78))
- It’s now possible to associate Stripe products and subscriptions with elements imported via Feed Me. ([#81](https://github.com/craftcms/stripe/pull/81))
- Added `craft\stripe\feedme\fields\Products`. ([#81](https://github.com/craftcms/stripe/pull/81))
- Added `craft\stripe\feedme\fields\Subscriptions`. ([#81](https://github.com/craftcms/stripe/pull/81))
- Added `craft\stripe\services\Prices::EVENT_AFTER_SYNCHRONIZE_PRICE`. ([#82](https://github.com/craftcms/stripe/pull/82))
- Added `craft\stripe\services\Products::EVENT_AFTER_SYNCHRONIZE_PRODUCT`. ([#82](https://github.com/craftcms/stripe/pull/82))
- Added `craft\stripe\services\Subscriptions::EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION`. ([#82](https://github.com/craftcms/stripe/pull/82))
- Fixed an error that occurred when searching through Stripe invoices via the control panel. ([#79](https://github.com/craftcms/stripe/issues/79))

## 1.3.3 - 2025-01-15

- Fixed a bug where user email addresses weren’t getting synced when `syncChangedUserEmailsToStripe` was set to `true`. ([#69](https://github.com/craftcms/stripe/issues/69))
- Fixed a bug where the plugin could cause an element query to be executed before Craft was fully initialized. ([#71](https://github.com/craftcms/stripe/issues/71))
- Fixed a bug where the plugin was attempting to create missing users for Craft Solo and Team editions. ([#72](https://github.com/craftcms/stripe/pull/72))

## 1.3.2 - 2024-12-11

- Fixed an error that occurred on Edit Entry screens if Stripe wasn’t configured with an API key. ([#66](https://github.com/craftcms/stripe/pull/66))
- Fixed a bug where “Stripe Sync All” utility was showing if Stripe wasn’t configured with an API key. ([#66](https://github.com/craftcms/stripe/pull/66))
- Fixed an information disclosure vulnerability. ([#67](https://github.com/craftcms/stripe/pull/67))

## 1.3.1 - 2024-11-28

- Fixed a bug where the Products index page listed “Link” as a sort option.. ([#59](https://github.com/craftcms/stripe/issues/59))
- Fixed a bug where the “Sync from Stripe” user action item was shown for users who didn’t have access to the Stripe plugin. ([#61](https://github.com/craftcms/stripe/pull/61))
- Fixed a bug where the Webhook Signing Secret and ID were showing as parsed on the Webhooks page. ([#62](https://github.com/craftcms/stripe/issues/62))
- Fixed an information disclosure vulnerability. ([#61](https://github.com/craftcms/stripe/pull/61))

## 1.3.0 - 2024-11-19

- Stripe now requires Craft CMS 5.5.0 or later.
- Added support for customizing card attributes for the Product, Price and Subscription field layouts. ([#56](https://github.com/craftcms/stripe/pull/56))
- Added the `stripe/data/reset` command. ([#42](https://github.com/craftcms/stripe/issues/42))
- The `resave/stripe-products`, `resave/stripe-prices`, and `resave/stripe-subscriptions` commands now support the `--with-fields` option. ([#55](https://github.com/craftcms/stripe/pull/55))
- Added `craft\stripe\models\Settings::$createUserIfMissing`. ([#57](https://github.com/craftcms/stripe/pull/57))
- Fixed a bug where `craft\stripe\elements\Product::getDefaultPrice()` was returning `null` for products with tiered pricing. ([#40](https://github.com/craftcms/stripe/pull/40))
- Fixed a SQL error that occurred on MariaDB. ([#51](https://github.com/craftcms/stripe/issues/51))
- Fixed a styling issue. ([#49](https://github.com/craftcms/stripe/pull/49))

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
