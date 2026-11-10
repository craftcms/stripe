<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Events\CheckoutSessionCreating;
use CraftCms\Stripe\Models\Customer;
use Illuminate\Container\Attributes\Singleton;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Price as StripePrice;

use function CraftCms\Cms\currentUserElement;

/**
 * Starts Stripe Checkout sessions.
 *
 * @since 2.0.0
 */
#[Singleton]
class Checkout
{
    public function __construct(
        private readonly Api $api,
        private readonly Customers $customers,
    ) {}

    /**
     * Starts a Checkout session and returns its URL.
     *
     * `$user` can be a user, an email, `null` for the current user, or `false` to leave the customer to Stripe.
     * The session uses the user’s first Stripe customer, or their email when they don’t have one.
     *
     * @param  array<int, array{price: string, quantity: int}>  $lineItems
     * @param  array<string, mixed>|null  $params  More params for the Stripe API
     */
    public function getCheckoutUrl(
        array $lineItems = [],
        string|User|false|null $user = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?array $params = null,
    ): string {
        $user ??= currentUserElement();
        $email = $user instanceof User ? $user->email : $user;
        $customer = is_string($email) ? ($this->customers->getFirstCustomerByEmail($email) ?? $email) : null;

        $data = [
            'line_items' => array_values($lineItems),
            'success_url' => $this->getUrl('success', $successUrl),
            'cancel_url' => $this->getUrl('cancel', $cancelUrl),
        ];

        if ($customer instanceof Customer) {
            $data['customer'] = $customer->stripeId;
        } elseif (is_string($customer) && $customer !== '') {
            $data['customer_email'] = $customer;
        }

        event($event = new CheckoutSessionCreating($data + ($params ?? [])));

        // Listeners may change the line items, so the mode follows them
        $params = $event->params;
        $params['mode'] = $this->getCheckoutMode($params['line_items']);

        return (string) $this->api->getClient()->checkout->sessions->create($params)->url;
    }

    /**
     * Returns `subscription` when any line item has a recurring price, otherwise `payment`.
     *
     * @param  array<int, array{price: string}>  $lineItems
     */
    public function getCheckoutMode(array $lineItems): string
    {
        $stripeIds = array_column($lineItems, 'price');

        $hasRecurring = $stripeIds !== [] && Price::find()
            ->stripeId($stripeIds)
            ->status(null)
            ->collect()
            ->contains(fn (Price $price) => ($price->getData()['type'] ?? null) === StripePrice::TYPE_RECURRING);

        return $hasRecurring ? StripeCheckoutSession::MODE_SUBSCRIPTION : StripeCheckoutSession::MODE_PAYMENT;
    }

    private function getUrl(string $type, ?string $url): string
    {
        $url ??= match ($type) {
            'success' => Plugin::getInstance()->getSettings()->defaultSuccessUrl,
            default => Plugin::getInstance()->getSettings()->defaultCancelUrl,
        };

        return $url ? Url::siteUrl($url) : request()->fullUrl();
    }
}
