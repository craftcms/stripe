<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Stripe\Checkout;
use CraftCms\Stripe\Elements\Subscription;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Starts a Stripe Checkout session from a front-end form, and redirects to it.
 *
 * @since 2.0.0
 */
class CheckoutController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Checkout $checkout,
    ) {}

    public function __invoke(Request $request): Response
    {
        $request->validate([
            'lineItems' => ['required', 'array'],
            'lineItems.*.price' => ['required', 'string'],
            'lineItems.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        $lineItems = collect($request->input('lineItems'))
            ->filter(fn (array $item) => (int) $item['quantity'] > 0)
            ->map(fn (array $item) => ['price' => (string) $item['price'], 'quantity' => (int) $item['quantity']])
            ->values()
            ->all();

        if ($lineItems === []) {
            return $this->asFailure(t('Please specify the quantity', category: 'stripe'));
        }

        // Falsy values ask for an anonymous checkout, rather than the current user’s
        $customer = $request->input('customer');
        $customer = in_array($customer, ['false', '0', 0, false], true) ? false : $customer;

        // Params from the form are deliberately not passed to Stripe, so customers can’t change what they check out.
        // Use the CheckoutSessionCreating event to add some.
        $params = [];

        if ($request->filled('fields') && $this->checkout->getCheckoutMode($lineItems) === StripeCheckoutSession::MODE_SUBSCRIPTION) {
            // An unpublished draft holds the custom field values until Stripe confirms the subscription
            $subscription = new Subscription(['title' => now()->format('Y-m-d H:i:s')]);
            $subscription->setFieldValuesFromRequest('fields');

            if (Drafts::saveElementAsDraft($subscription, markAsSaved: false)) {
                $params['metadata']['craftSubscriptionUid'] = $subscription->uid;
            }
        }

        return redirect()->away($this->checkout->getCheckoutUrl(
            $lineItems,
            is_string($customer) && $customer !== '' ? $customer : ($customer === false ? false : null),
            $request->getSigned('successUrl'),
            $request->getSigned('cancelUrl'),
            $params,
        ));
    }
}
