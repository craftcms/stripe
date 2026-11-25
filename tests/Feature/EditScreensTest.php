<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Edition::set(Edition::Pro);
    $this->artisan('stripe:sync', ['type' => 'all'])->assertSuccessful();
});

it('links the edit screen to the Stripe Dashboard', function (string $elementType, string $stripeId) {
    actingAs(UserModel::factory()->admin()->createElement());
    $element = $elementType::find()->stripeId($stripeId)->one();

    $props = $this->get($element->getCpEditUrl(), ['X-Inertia' => 'true'])->assertOk()->json('props');

    expect(json_encode($props, JSON_UNESCAPED_SLASHES))->toContain($element->getStripeEditUrl());
})->with([
    'product' => [Product::class, 'prod_basic'],
    'subscription' => [Subscription::class, 'sub_alice'],
]);

it('requires access to Stripe', function (string $elementType, string $stripeId) {
    actingAs(UserModel::factory()->createElement());

    $this->get($elementType::find()->stripeId($stripeId)->one()->getCpEditUrl())->assertForbidden();
})->with([
    'product' => [Product::class, 'prod_basic'],
    'subscription' => [Subscription::class, 'sub_alice'],
]);
