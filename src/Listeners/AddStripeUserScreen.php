<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Http\Controllers\CustomersController;

use function CraftCms\Cms\t;

/**
 * Adds a Stripe screen to the Edit User page.
 *
 * @since 3.0.0
 */
readonly class AddStripeUserScreen
{
    public function __construct(
        private Api $api,
    ) {}

    public function handle(EditUserScreensResolving $event): void
    {
        if ($this->api->isConfigured() && $event->currentUser?->can('accessPlugin-stripe')) {
            $event->screens[CustomersController::SCREEN] = ['label' => t('Stripe', category: 'stripe')];
        }
    }
}
