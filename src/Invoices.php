<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Models\Invoice;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;

/**
 * Syncs Stripe invoices, and finds them by customer.
 *
 * @since 3.0.0
 */
#[Singleton]
class Invoices
{
    public function __construct(
        private readonly Api $api,
        private readonly Customers $customers,
    ) {}

    /**
     * @return array<string, Invoice> Invoices by their Stripe ID, newest first
     */
    public function getAllInvoices(): array
    {
        return Invoice::orderByDesc('created')->get()->keyBy('stripeId')->all();
    }

    /**
     * Syncs every invoice from Stripe.
     *
     * @return int The number of invoices synced
     */
    public function syncAllInvoices(): int
    {
        $count = 0;

        foreach ($this->api->fetchAllInvoices() as $invoice) {
            $count += (int) $this->createOrUpdateInvoice($invoice);
        }

        return $count;
    }

    /**
     * Syncs the invoices of a Stripe customer.
     *
     * @return int The number of invoices synced
     */
    public function syncCustomerInvoices(StripeCustomer $customer): int
    {
        $count = 0;

        foreach ($this->api->fetchAllInvoices(['customer' => $customer->id]) as $invoice) {
            $count += (int) $this->createOrUpdateInvoice($invoice);
        }

        return $count;
    }

    public function createOrUpdateInvoice(StripeInvoice $invoice): bool
    {
        return Cache::lock("stripe-invoice:$invoice->id", 15)->block(15, fn () => Invoice::updateOrCreate(
            ['stripeId' => $invoice->id],
            ['data' => $invoice->toArray()],
        )->exists);
    }

    /**
     * @return Invoice[] The invoices of the user’s Stripe customers, newest first
     */
    public function getInvoicesByUser(?User $user): array
    {
        return $this->getInvoicesByEmail($user?->email);
    }

    /**
     * @return Invoice[] The invoices of the Stripe customers with an email address, newest first
     */
    public function getInvoicesByEmail(?string $email): array
    {
        $customerIds = array_keys($this->customers->getCustomersByEmail($email));

        if ($customerIds === []) {
            return [];
        }

        return Invoice::whereIn('data->customer', $customerIds)->orderByDesc('created')->get()->all();
    }

    /**
     * @return Invoice[]
     */
    public function getInvoicesByCustomerId(string $customerId): array
    {
        return Invoice::where('data->customer', $customerId)->orderByDesc('created')->get()->all();
    }

    public function getInvoiceById(?string $stripeId): ?Invoice
    {
        return $stripeId === null ? null : Invoice::firstWhere('stripeId', $stripeId);
    }

    public function deleteInvoiceByStripeId(string $stripeId): void
    {
        Invoice::where('stripeId', $stripeId)->delete();
    }
}
