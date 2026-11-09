<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Url;
use CraftCms\Stripe\Events\BillingPortalSessionCreating;
use CraftCms\Stripe\Models\Customer;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;
use Throwable;

use function CraftCms\Cms\currentUserElement;

/**
 * Starts Stripe customer portal sessions.
 *
 * @since 3.0.0
 */
#[Singleton]
class BillingPortal
{
    public function __construct(
        private readonly Api $api,
        private readonly Customers $customers,
    ) {}

    /**
     * Returns a portal session URL for one of the current user’s Stripe customers, or `null` when the customer isn’t theirs.
     *
     * @param  array<string, mixed>  $params  More params for the Stripe API
     */
    public function getSessionUrl(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $user = currentUserElement();
        $stripeId = is_string($customer) ? $customer : $customer->stripeId;

        if ($user === null || ! array_key_exists($stripeId, $this->customers->getCustomersByEmail($user->email))) {
            return null;
        }

        return $this->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, $params);
    }

    /**
     * Returns a portal session URL for any Stripe customer. Only call this for a customer the visitor may manage.
     *
     * @param  array<string, mixed>  $params  More params for the Stripe API
     */
    public function getCustomerBillingPortalSessionUrl(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        if (is_string($customer)) {
            $stripeId = $customer;
            $customer = $this->customers->getCustomerByStripeId($stripeId);

            if ($customer === null) {
                Log::error("No Stripe customer found for the ID $stripeId.");

                return null;
            }
        }

        $params['customer'] = $customer->stripeId;
        $params['return_url'] = $returnUrl ? Url::siteUrl($returnUrl) : Url::siteUrl();

        if ($configurationId !== null) {
            $params['configuration'] = $configurationId;
        }

        event($event = new BillingPortalSessionCreating($params));

        try {
            return $this->api->getClient()->billingPortal->sessions->create($event->params)->url;
        } catch (Throwable $e) {
            Log::error("Unable to start Stripe billing portal session: {$e->getMessage()}");

            return '';
        }
    }
}
