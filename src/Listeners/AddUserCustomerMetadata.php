<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Listeners;

use CraftCms\Cms\Element\Events\ElementMetadataResolving;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Support\StripeLink;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Lists a user’s Stripe customers in their metadata, linked to the Stripe Dashboard.
 *
 * @since 3.0.0
 */
readonly class AddUserCustomerMetadata
{
    public function __construct(
        private Api $api,
        private Customers $customers,
    ) {}

    public function handle(ElementMetadataResolving $event): void
    {
        $user = $event->element;

        if (! $user instanceof User || ! $this->api->isConfigured() || ! currentUser()?->can('accessPlugin-stripe')) {
            return;
        }

        $customers = $this->customers->getCustomersByEmail($user->email);

        if ($customers === []) {
            return;
        }

        $event->metadata[t('Stripe Customer(s)', category: 'stripe')] = implode('', array_map(
            fn (Customer $customer) => Html::tag('div', StripeLink::html(
                $customer->getStripeEditUrl(),
                (($customer->getData()['name'] ?? '') ?: $customer->email)." ($customer->stripeId)",
            )),
            $customers,
        ));
    }
}
