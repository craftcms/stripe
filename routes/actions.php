<?php

declare(strict_types=1);

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Stripe\Http\Controllers\CheckoutController;
use CraftCms\Stripe\Http\Controllers\SettingsController;
use CraftCms\Stripe\Http\Controllers\SubscriptionsController;
use CraftCms\Stripe\Http\Controllers\SyncController;
use CraftCms\Stripe\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

// Keeps the Craft 5 action paths (`stripe/checkout`, `stripe/webhooks/handle`, etc.) working.
// These are reachable at site action URLs too, so the control panel ones require a login.

Route::post('checkout', CheckoutController::class);
Route::post('checkout/checkout', CheckoutController::class);
Route::post('webhooks/handle', [WebhooksController::class, 'handle']);

Route::middleware(['auth', 'can:accessPlugin-stripe'])->group(function () {
    Route::post('subscriptions/cancel', [SubscriptionsController::class, 'cancel']);
    Route::post('subscriptions/resume', [SubscriptionsController::class, 'resume']);
    Route::post('sync/user', [SyncController::class, 'user']);
    Route::post('sync/all', [SyncController::class, 'all'])->middleware('can:utility:stripe-sync-all');

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::post('settings/save-settings', [SettingsController::class, 'store']);
        Route::post('webhooks/save-settings', [WebhooksController::class, 'store']);
        Route::post('webhooks/create', [WebhooksController::class, 'create']);
        Route::post('webhooks/delete', [WebhooksController::class, 'destroy']);
    });
});
