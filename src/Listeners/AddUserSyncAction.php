<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\Element\Events\ElementActionMenuItemsResolving;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Adds “Sync from Stripe” to a user’s action menu.
 *
 * @since 2.0.0
 */
readonly class AddUserSyncAction
{
    public function __construct(
        private Api $api,
    ) {}

    public function handle(ElementActionMenuItemsResolving $event): void
    {
        $user = $event->element;

        if (! $user instanceof User || ! $user->id || ! $user->email || ! $this->api->isConfigured() || ! currentUser()?->can('accessPlugin-stripe')) {
            return;
        }

        $event->items[] = [
            'action' => 'stripe/sync/user',
            'type' => 'button',
            'icon' => 'arrows-rotate',
            'label' => t('Sync from Stripe', category: 'stripe'),
            'confirm' => t('Are you sure you want to sync this user’s Stripe customer data?', category: 'stripe'),
            'params' => ['userId' => $user->id],
        ];
    }
}
