# Release Notes for Stripe

- Links to the Stripe dashboard now deep-link correctly for all account types. ([#100](https://github.com/craftcms/stripe/issues/100))
- Added the `stripe/data/cleanup-duplicates` command to find and remove duplicate subscription elements.
- Fixed a bug where duplicate subscriptions could be created. ([#101](https://github.com/craftcms/stripe/pull/101))
- Added the `stripe/sync/customer` command.
- Added `craft\stripe\jobs\SyncSingleCustomerData`.
- Added `\craft\stripe\services\Invoices::syncCustomerInvoices()`.
- Added `\craft\stripe\services\PaymentMethods::syncCustomerPaymentMethods()`.
- Added `\craft\stripe\services\Subscriptions::syncCustomerSubscriptions()`.