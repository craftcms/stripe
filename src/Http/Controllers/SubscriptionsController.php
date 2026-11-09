<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Subscriptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Cancels and resumes subscriptions from the control panel.
 *
 * @since 3.0.0
 */
class SubscriptionsController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Subscriptions $subscriptions,
    ) {}

    public function cancel(Request $request): Response
    {
        $stripeId = $this->requireSubscription($request);

        if (! $this->subscriptions->cancelSubscriptionByStripeId($stripeId, $request->boolean('immediately'))) {
            return $this->asFailure(t('Unable to cancel subscription.', category: 'stripe'));
        }

        return $this->asSuccess(t('Subscription cancelled.', category: 'stripe'));
    }

    public function resume(Request $request): Response
    {
        $stripeId = $this->requireSubscription($request);

        if (! $this->subscriptions->resumeSubscriptionByStripeId($stripeId)) {
            return $this->asFailure(t('Unable to resume subscription.', category: 'stripe'));
        }

        return $this->asSuccess(t('Subscription resumed.', category: 'stripe'));
    }

    /**
     * Returns the requested Stripe ID, once it’s known to belong to a synced subscription.
     */
    private function requireSubscription(Request $request): string
    {
        $stripeId = (string) $request->validate(['stripeId' => ['required', 'string']])['stripeId'];

        abort_unless(Subscription::find()->stripeId($stripeId)->status(null)->exists(), 404, "No subscription with the Stripe ID $stripeId exists.");

        return $stripeId;
    }
}
