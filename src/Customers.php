<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use Carbon\Carbon;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Stripe\Models\Customer;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stripe\Customer as StripeCustomer;

/**
 * Syncs Stripe customers, and finds them by email.
 *
 * @since 2.0.0
 */
#[Singleton]
class Customers
{
    public function __construct(
        private readonly Api $api,
    ) {}

    /**
     * @return array<string, Customer> Customers by their Stripe ID, newest first
     */
    public function getAllCustomers(): array
    {
        return Customer::orderByDesc('stripeCreated')->get()->keyBy('stripeId')->all();
    }

    /**
     * Syncs every customer from Stripe.
     *
     * @return int The number of customers synced
     */
    public function syncAllCustomers(): int
    {
        $count = 0;

        foreach ($this->api->fetchAllCustomers() as $customer) {
            $count += (int) $this->createOrUpdateCustomer($customer);
        }

        return $count;
    }

    public function createOrUpdateCustomer(StripeCustomer $customer): bool
    {
        return Cache::lock("stripe-customer:$customer->id", 15)->block(15, fn () => Customer::updateOrCreate(
            ['stripeId' => $customer->id],
            [
                'email' => (string) $customer->email,
                'stripeCreated' => Carbon::createFromTimestamp($customer->created),
                'data' => $customer->toArray(),
            ],
        )->exists);
    }

    /**
     * @return array<string, Customer> The customers with an email address, by their Stripe ID, newest first
     */
    public function getCustomersByEmail(?string $email): array
    {
        if ($email === null || $email === '') {
            return [];
        }

        return Customer::where('email', $email)->orderByDesc('stripeCreated')->get()->keyBy('stripeId')->all();
    }

    public function getFirstCustomerByEmail(?string $email): ?Customer
    {
        $customers = $this->getCustomersByEmail($email);

        return reset($customers) ?: null;
    }

    public function getCustomerByStripeId(string $stripeId): ?Customer
    {
        return Customer::firstWhere('stripeId', $stripeId);
    }

    /**
     * Whether a Craft user has the email address.
     */
    public function userExistsForEmail(string $email): bool
    {
        return DB::table(CraftTable::USERS)->where('email', $email)->exists();
    }

    public function deleteCustomerDataByStripeId(string $stripeId): void
    {
        Customer::where('stripeId', $stripeId)->delete();
    }
}
