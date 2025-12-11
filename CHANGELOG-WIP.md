# Release Notes for Stripe 1.7

- Links to the Stripe dashboard now deep-link correctly for all account types. ([#100](https://github.com/craftcms/stripe/issues/100))
- Fixed a bug where duplicate subscription elements for the same subscription could be created. ([#101](https://github.com/craftcms/stripe/pull/101))
- Added the `stripe/data/cleanup-duplicates` command to find and remove duplicate subscription elements.
- Added the `stripe/sync/customer` command.
- Added `craft\stripe\jobs\SyncSingleCustomerData`.
- Added `\craft\stripe\services\Invoices::syncCustomerInvoices()`.
- Added `\craft\stripe\services\PaymentMethods::syncCustomerPaymentMethods()`.
- Added `\craft\stripe\services\Subscriptions::syncCustomerSubscriptions()`.