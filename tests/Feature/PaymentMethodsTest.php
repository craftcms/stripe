<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\Models\PaymentMethod;
use CraftCms\Stripe\PaymentMethods;
use Stripe\PaymentMethod as StripePaymentMethod;

it('creates a payment method record, then updates it instead of adding another', function () {
    $source = $this->stripe->state['payment_methods']['pm_alice_card'];

    expect(app(PaymentMethods::class)->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom($source)))->toBeTrue();

    app(PaymentMethods::class)->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom(['type' => 'link'] + $source));

    expect(PaymentMethod::where('stripeId', 'pm_alice_card')->count())->toBe(1)
        ->and(app(PaymentMethods::class)->getPaymentMethodById('pm_alice_card')->getData()['type'])->toBe('link');
});

it('finds payment methods by customer', function () {
    app(PaymentMethods::class)->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom($this->stripe->state['payment_methods']['pm_alice_card']));

    expect(app(PaymentMethods::class)->getPaymentMethodsByCustomerId('cus_alice'))->toHaveCount(1)
        ->and(app(PaymentMethods::class)->getPaymentMethodsByCustomerId('cus_other'))->toBe([]);
});

it('deletes payment methods by Stripe ID or customer', function (string $method, string $id) {
    app(PaymentMethods::class)->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom($this->stripe->state['payment_methods']['pm_alice_card']));
    app(PaymentMethods::class)->$method($id);

    expect(app(PaymentMethods::class)->getPaymentMethodById('pm_alice_card'))->toBeNull();
})->with([
    'by Stripe ID' => ['deletePaymentMethodByStripeId', 'pm_alice_card'],
    'by customer' => ['deletePaymentMethodsByCustomerId', 'cus_alice'],
]);

it('only syncs the payment methods of customers who are Craft users', function () {
    expect(app(PaymentMethods::class)->syncAllPaymentMethods())->toBe(0);

    Edition::set(Edition::Pro);
    UserModel::factory()->createElement(['email' => 'alice@example.com']);

    expect(app(PaymentMethods::class)->syncAllPaymentMethods())->toBe(1);
});
