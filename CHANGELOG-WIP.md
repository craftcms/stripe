# Release Notes for WIP Stripe 1.7

- Added the `stripe/data/remove-duplicates` command.
- Added the `stripe/sync/customer` command.
- Added `craft\stripe\jobs\SyncSingleCustomerData`.
- Added `craft\stripe\services\Invoices::syncCustomerInvoices()`.
- Added `craft\stripe\services\PaymentMethods::syncCustomerPaymentMethods()`.
- Added `craft\stripe\services\Subscriptions::syncCustomerSubscriptions()`.
- Fixed a bug where links to the Stripe dashboard weren’t always linking to the right URL. ([#100](https://github.com/craftcms/stripe/issues/100))
- Fixed a bug where it was possible to create multiple subscription elements for the same Stripe subscription. ([#101](https://github.com/craftcms/stripe/pull/101))
