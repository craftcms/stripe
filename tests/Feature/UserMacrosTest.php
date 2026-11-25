<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;

beforeEach(function () {
    Edition::set(Edition::Pro);
});

it('finds a user’s Stripe customer, subscriptions and payment methods by their email', function () {
    $user = UserModel::factory()->createElement(['email' => 'alice@example.com']);
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();

    expect($user->getStripeCustomer()->stripeId)->toBe('cus_alice')
        ->and($user->getStripeCustomers()->keys()->all())->toBe(['cus_alice'])
        ->and($user->getStripeSubscriptions()->pluck('stripeId')->all())->toBe(['sub_alice'])
        ->and($user->getStripePaymentMethods()->pluck('stripeId')->all())->toBe(['pm_alice_card']);
});

it('finds nothing for a user who isn’t a Stripe customer', function () {
    $user = UserModel::factory()->createElement(['email' => 'bob@example.com']);
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();

    expect($user->getStripeCustomer())->toBeNull()
        ->and($user->getStripeSubscriptions())->toBeEmpty()
        ->and($user->getStripePaymentMethods())->toBeEmpty();
});
