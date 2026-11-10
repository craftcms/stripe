<?php

declare(strict_types=1);

use CraftCms\Cms\Http\Controllers\Elements\EditElementController;
use CraftCms\Cms\Http\Controllers\Elements\ElementTypeIndexController;
use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Cms\Support\Url;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Http\Controllers\CustomersController;
use CraftCms\Stripe\Http\Controllers\InvoicesController;
use CraftCms\Stripe\Http\Controllers\SettingsController;
use CraftCms\Stripe\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

$idSlugParams = [
    'id' => '\d+',
    'slug' => '(?:-[^\/]*)',
];

Route::middleware('can:accessPlugin-stripe')->group(function () use ($idSlugParams) {
    Route::get('stripe', fn () => redirect(Url::cpUrl('stripe/products')));

    Route::get('stripe/products', ElementTypeIndexController::class)->defaults('elementType', Product::class);
    Route::get('stripe/products/{id}{slug?}', EditElementController::class)->where($idSlugParams);

    Route::get('stripe/subscriptions', ElementTypeIndexController::class)->defaults('elementType', Subscription::class);
    Route::get('stripe/subscriptions/{id}{slug?}', EditElementController::class)->where($idSlugParams);

    Route::get('stripe/invoices', [InvoicesController::class, 'index']);

    Route::get('myaccount/stripe', [CustomersController::class, 'index']);
    Route::get('users/{userId}/stripe', [CustomersController::class, 'index'])->whereNumber('userId');

    // Admins can view these even when admin changes are disallowed
    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('stripe/settings', [SettingsController::class, 'index']);
        Route::get('stripe/webhooks', [WebhooksController::class, 'index']);
    });
});
