<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Models\PaymentMethod;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;

/**
 * Syncs the payment methods of Stripe customers that belong to Craft users.
 *
 * @since 2.0.0
 */
#[Singleton]
class PaymentMethods
{
    public function __construct(
        private readonly Api $api,
        private readonly Customers $customers,
    ) {}

    /**
     * @return array<string, PaymentMethod> Payment methods by their Stripe ID
     */
    public function getAllPaymentMethods(): array
    {
        return PaymentMethod::all()->keyBy('stripeId')->all();
    }

    /**
     * Syncs the payment methods of every customer whose email belongs to a Craft user.
     *
     * @return int The number of payment methods synced
     */
    public function syncAllPaymentMethods(): int
    {
        $count = 0;

        foreach ($this->api->fetchAllPaymentMethods() as $paymentMethod) {
            $count += (int) $this->createOrUpdatePaymentMethod($paymentMethod);
        }

        return $count;
    }

    /**
     * Syncs the payment methods of a Stripe customer, if their email belongs to a Craft user.
     *
     * @return int The number of payment methods synced
     */
    public function syncCustomerPaymentMethods(StripeCustomer $customer): int
    {
        if (! $customer->email || ! $this->customers->userExistsForEmail($customer->email)) {
            return 0;
        }

        $count = 0;

        foreach ($this->api->fetchPaymentMethodsForCustomer($customer->id) as $paymentMethod) {
            $count += (int) $this->createOrUpdatePaymentMethod($paymentMethod);
        }

        return $count;
    }

    public function createOrUpdatePaymentMethod(StripePaymentMethod $paymentMethod): bool
    {
        return Cache::lock("stripe-payment-method:$paymentMethod->id", 15)->block(15, fn () => PaymentMethod::updateOrCreate(
            ['stripeId' => $paymentMethod->id],
            ['data' => $paymentMethod->toArray()],
        )->exists);
    }

    /**
     * @return PaymentMethod[] The payment methods of the user’s Stripe customers, newest first
     */
    public function getPaymentMethodsByUser(?User $user): array
    {
        return $this->getPaymentMethodsByEmail($user?->email);
    }

    /**
     * @return PaymentMethod[] The payment methods of the Stripe customers with an email address, newest first
     */
    public function getPaymentMethodsByEmail(?string $email): array
    {
        $customerIds = array_keys($this->customers->getCustomersByEmail($email));

        if ($customerIds === []) {
            return [];
        }

        return PaymentMethod::whereIn('customerId', $customerIds)->orderByDesc('data->created')->get()->all();
    }

    /**
     * @return PaymentMethod[]
     */
    public function getPaymentMethodsByCustomerId(string $customerId): array
    {
        return PaymentMethod::where('customerId', $customerId)->get()->all();
    }

    public function getPaymentMethodById(?string $stripeId): ?PaymentMethod
    {
        return $stripeId === null ? null : PaymentMethod::firstWhere('stripeId', $stripeId);
    }

    public function deletePaymentMethodByStripeId(string $stripeId): void
    {
        PaymentMethod::where('stripeId', $stripeId)->delete();
    }

    public function deletePaymentMethodsByCustomerId(string $customerId): void
    {
        PaymentMethod::where('customerId', $customerId)->delete();
    }
}
