<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Helpers;

use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Subscription as StripeSubscription;

/**
 * Builds fake Stripe SDK objects (via `StripeObject::constructFrom()`, no HTTP calls) with
 * complete-enough default fields to feed directly into the plugin's `createOrUpdateX()` sync
 * methods and CP-card renderers without missing-array-key errors.
 */
class StripeApiObjectFactory
{
    public static function product(string $id, array $overrides = []): StripeProduct
    {
        return StripeProduct::constructFrom(array_merge([
            'id' => $id,
            'name' => 'Test Product',
            'active' => true,
            'created' => 1700000000,
            'updated' => 1700000000,
        ], $overrides));
    }

    public static function price(string $id, string $productId, array $overrides = []): StripePrice
    {
        return StripePrice::constructFrom(array_merge([
            'id' => $id,
            'active' => true,
            'currency' => 'usd',
            'unit_amount' => 1000,
            'product' => $productId,
            'type' => 'one_time',
            'recurring' => null,
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'metadata' => [],
            'currency_options' => [],
            'created' => 1700000000,
        ], $overrides));
    }

    public static function subscription(string $id, array $overrides = []): StripeSubscription
    {
        return StripeSubscription::constructFrom(array_merge([
            'id' => $id,
            'status' => 'active',
            'items' => ['data' => []],
            'customer' => null,
            'cancel_at_period_end' => false,
            'cancel_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'current_period_start' => 1700000000,
            'current_period_end' => 1702592000,
            'discounts' => [],
            'metadata' => [],
            'created' => 1700000000,
        ], $overrides));
    }

    public static function customer(string $id, string $email, array $overrides = []): StripeCustomer
    {
        return StripeCustomer::constructFrom(array_merge([
            'id' => $id,
            'email' => $email,
            'created' => 1700000000,
        ], $overrides));
    }

    public static function paymentMethod(string $id, string $customerId, string $type = 'card', array $overrides = []): StripePaymentMethod
    {
        return StripePaymentMethod::constructFrom(array_merge([
            'id' => $id,
            'customer' => $customerId,
            'type' => $type,
            'created' => 1700000000,
            $type => [],
        ], $overrides));
    }

    public static function invoice(string $id, array $overrides = []): StripeInvoice
    {
        return StripeInvoice::constructFrom(array_merge([
            'id' => $id,
            'number' => 'INV-' . $id,
            'status' => 'open',
            'currency' => 'usd',
            'total' => 1000,
            'customer_email' => 'customer@example.com',
            'due_date' => null,
            'created' => 1700000000,
        ], $overrides));
    }
}
