<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Sends a user’s changed email to their Stripe customers.
 *
 * @since 2.0.0
 */
readonly class SyncChangedUserEmail
{
    public function __construct(
        private Settings $settings,
        private Api $api,
        private Customers $customers,
    ) {}

    public function handle(ElementSaving $event): void
    {
        $user = $event->element;

        if (! $user instanceof User || $event->isNew || ! $user->id || ! $this->settings->syncChangedUserEmailsToStripe || ! $this->api->isConfigured()) {
            return;
        }

        $oldEmail = DB::table(CraftTable::USERS)->where('id', $user->id)->value('email');

        if (! $oldEmail || ! $user->email || $oldEmail === $user->email) {
            return;
        }

        foreach ($this->customers->getCustomersByEmail($oldEmail) as $customer) {
            try {
                $this->customers->createOrUpdateCustomer(
                    $this->api->getClient()->customers->update($customer->stripeId, ['email' => $user->email]),
                );
            } catch (ApiErrorException $e) {
                Log::error("Unable to update the email of Stripe customer $customer->stripeId: {$e->getMessage()}");
            }
        }
    }
}
