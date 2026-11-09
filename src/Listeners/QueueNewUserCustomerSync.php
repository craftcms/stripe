<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Jobs\SyncCustomerData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queues a sync of the Stripe customers that have a saved user’s email, when none are synced yet.
 *
 * @since 3.0.0
 */
readonly class QueueNewUserCustomerSync
{
    public function __construct(
        private Api $api,
        private Customers $customers,
    ) {}

    public function handle(ElementSaved $event): void
    {
        $user = $event->element;

        if (! $user instanceof User || ! $user->email || ! $this->api->isConfigured() || $this->customers->getCustomersByEmail($user->email) !== []) {
            return;
        }

        try {
            foreach ($this->api->fetchAllCustomers(['email' => $user->email]) as $customer) {
                dispatch(new SyncCustomerData($customer->id));
            }
        } catch (Throwable $e) {
            Log::error("Unable to fetch Stripe customers for $user->email: {$e->getMessage()}");
        }
    }
}
