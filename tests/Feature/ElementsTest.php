<?php

declare(strict_types=1);

use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\Invoice;
use CraftCms\Stripe\Models\PaymentMethod;
use CraftCms\Stripe\Plugin;

it('derives product and price statuses from Stripe', function (string $elementType) {
    $element = new $elementType;
    expect($element->getStatus())->toBe('live');

    $element->stripeStatus = 'archived';
    expect($element->getStatus())->toBe('stripeArchived');

    $element->enabled = false;
    expect($element->getStatus())->toBe('disabled');
})->with([Product::class, Price::class]);

it('derives subscription statuses from Stripe', function (string $stripeStatus, string $status) {
    $subscription = new Subscription;
    $subscription->stripeStatus = $stripeStatus;
    expect($subscription->getStatus())->toBe($status);

    $subscription->enabled = false;
    expect($subscription->getStatus())->toBe('disabled');
})->with([
    ['active', 'live'],
    ['trialing', 'live'],
    ['scheduled', 'stripeScheduled'],
    ['canceled', 'stripeCanceled'],
    ['past_due', 'stripeSuspended'],
]);

it('shows the Stripe status as a badge', function (string $elementType, string $stripeStatus, string $html) {
    $element = new $elementType;
    $element->stripeStatus = $stripeStatus;

    expect($element->getStripeStatusHtml())->toBe($html);
})->with([
    [Product::class, 'active', '<craft-badge fill="green">Active</craft-badge>'],
    [Product::class, 'archived', '<craft-badge fill="red">Archived</craft-badge>'],
    [Product::class, 'unknown', '<craft-badge fill="orange">Unknown</craft-badge>'],
    [Price::class, 'active', '<craft-badge fill="green">Active</craft-badge>'],
    [Price::class, 'archived', '<craft-badge fill="red">Archived</craft-badge>'],
    [Subscription::class, 'active', '<craft-badge fill="green">Active</craft-badge>'],
    [Subscription::class, 'trialing', '<craft-badge fill="yellow">Trialing</craft-badge>'],
    [Subscription::class, 'scheduled', '<craft-badge fill="orange">Scheduled</craft-badge>'],
    [Subscription::class, 'canceled', '<craft-badge fill="red">Canceled</craft-badge>'],
    [Subscription::class, 'past_due', '<craft-badge fill="blue">Past Due</craft-badge>'],
]);

it('links elements and records to the Stripe Dashboard', function (string $class, array $attributes, string $path) {
    $model = new $class;

    foreach ($attributes as $name => $value) {
        $model->$name = $value;
    }

    expect($model->getStripeEditUrl())->toBe("https://dashboard.stripe.com/acct_fake123/test/$path");
})->with([
    'product' => [Product::class, ['stripeId' => 'prod_1'], 'products/prod_1'],
    'price' => [Price::class, ['stripeId' => 'price_1'], 'prices/price_1'],
    'subscription' => [Subscription::class, ['stripeId' => 'sub_1'], 'subscriptions/sub_1'],
    'customer' => [Customer::class, ['stripeId' => 'cus_1'], 'customers/cus_1'],
    'invoice' => [Invoice::class, ['stripeId' => 'in_1'], 'invoices/in_1'],
    'payment method, by its customer' => [PaymentMethod::class, ['data' => ['customer' => 'cus_1']], 'customers/cus_1'],
]);

it('looks up the Stripe account only once when the lookup fails', function () {
    // The fake Stripe API rejects live keys
    Plugin::getInstance()->getSettings()->secretKey = 'sk_live_rejected';

    expect((new Product(['stripeId' => 'prod_1']))->getStripeEditUrl())->toBe('https://dashboard.stripe.com/products/prod_1')
        ->and((new Product(['stripeId' => 'prod_2']))->getStripeEditUrl())->toBe('https://dashboard.stripe.com/products/prod_2')
        ->and($this->stripe->requestsTo('GET', '/v1/account'))->toHaveCount(1);
});

it('holds the Stripe data as an array', function (string $class) {
    $model = new $class;
    expect($model->getData())->toBe([]);

    $model->setData(['id' => 'x_1']);
    expect($model->getData())->toBe(['id' => 'x_1']);

    $model->setData('{"id":"x_2"}');
    expect($model->getData())->toBe(['id' => 'x_2']);

    $model->setData(null);
    expect($model->getData())->toBe([]);
})->with([Product::class, Price::class, Subscription::class]);

it('formats a price with its data', function () {
    $price = new Price;
    $price->setData($this->stripe->state['prices']['price_basic_monthly']);

    expect($price->unitAmount())->toBe('$10.00')
        ->and($price->unitPrice())->toBe('$10.00/month')
        ->and($price->pricePerUnit())->toBe('$10.00')
        ->and($price->interval())->toBe('Every 1 month');
});
