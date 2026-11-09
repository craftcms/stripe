<?php

declare(strict_types=1);

use CraftCms\Stripe\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

// The URL the plugin registers with Stripe, and Craft 5 installs registered
Route::post('stripe/webhooks/handle', [WebhooksController::class, 'handle']);
