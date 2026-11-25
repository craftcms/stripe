<?php

declare(strict_types=1);

use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Models\Customer;
use Stripe\Customer as StripeCustomer;

it('creates a customer record, then updates it instead of adding another', function () {
    $source = $this->stripe->state['customers']['cus_alice'];

    expect(app(Customers::class)->createOrUpdateCustomer(StripeCustomer::constructFrom($source)))->toBeTrue();

    app(Customers::class)->createOrUpdateCustomer(StripeCustomer::constructFrom(['email' => 'alice@example.org'] + $source));

    expect(Customer::where('stripeId', 'cus_alice')->count())->toBe(1)
        ->and(app(Customers::class)->getCustomerByStripeId('cus_alice')->email)->toBe('alice@example.org');
});

it('syncs every customer from Stripe, and finds them by email or Stripe ID', function () {
    $customers = app(Customers::class);

    expect($customers->syncAllCustomers())->toBe(1)
        ->and(array_keys($customers->getCustomersByEmail('alice@example.com')))->toBe(['cus_alice'])
        ->and($customers->getCustomersByEmail('bob@example.com'))->toBe([])
        ->and($customers->getCustomerByStripeId('cus_alice')->email)->toBe('alice@example.com')
        ->and($customers->getCustomerByStripeId('cus_missing'))->toBeNull();
});

it('deletes a customer by its Stripe ID', function () {
    app(Customers::class)->syncAllCustomers();
    app(Customers::class)->deleteCustomerDataByStripeId('cus_alice');

    expect(app(Customers::class)->getCustomerByStripeId('cus_alice'))->toBeNull();
});
