<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Json;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Events\SubscriptionSynced;
use CraftCms\Stripe\Events\SubscriptionSyncing;
use CraftCms\Stripe\Models\SubscriptionData;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\Subscription as StripeSubscription;
use Throwable;

/**
 * Syncs Stripe subscriptions to subscription elements, and cancels or resumes them.
 *
 * @since 3.0.0
 */
#[Singleton]
class Subscriptions
{
    public function __construct(
        private readonly Api $api,
    ) {}

    /**
     * Syncs every subscription from Stripe, and deletes the elements of subscriptions no longer there.
     */
    public function syncAllSubscriptions(): void
    {
        $stripeIds = [];

        foreach ($this->api->fetchAllIterator('subscriptions', [
            'status' => 'all',
            'expand' => $this->api->prepExpandForFetchAll(Subscription::$expandParams),
        ]) as $batch) {
            foreach ($batch as $subscription) {
                $stripeIds[] = $subscription->id;
                $this->createOrUpdateSubscription($subscription);
            }
        }

        foreach (Subscription::find()->stripeId(['not', ...$stripeIds])->status(null)->all() as $element) {
            Elements::deleteElement($element);
        }
    }

    /**
     * Syncs the subscriptions of a Stripe customer.
     *
     * @return int The number of subscriptions synced
     */
    public function syncCustomerSubscriptions(StripeCustomer $customer): int
    {
        $count = 0;

        foreach ($this->api->fetchAllIterator('subscriptions', [
            'customer' => $customer->id,
            'status' => 'all',
            'expand' => $this->api->prepExpandForFetchAll(Subscription::$expandParams),
        ]) as $batch) {
            foreach ($batch as $subscription) {
                $count += (int) $this->createOrUpdateSubscription($subscription);
            }
        }

        return $count;
    }

    /**
     * Creates or updates the element for a Stripe subscription.
     *
     * Pass the unsaved draft a checkout created as `$element` to publish it with the subscription’s data.
     */
    public function createOrUpdateSubscription(StripeSubscription $subscription, ?Subscription $element = null): bool
    {
        return Cache::lock("stripe-subscription:$subscription->id", 15)->block(15, function () use ($subscription, $element) {
            // Another process may have created the element while this one waited for the lock
            $element = Subscription::find()->stripeId($subscription->id)->status(null)->one() ?? $element ?? new Subscription;

            $attributes = [
                'stripeId' => $subscription->id,
                'stripeStatus' => $subscription->status,
                'data' => $subscription->toArray(),
                'prices' => array_map(fn ($item) => $item->price->id, $subscription->items->data ?? []),
            ];
            Subscription::configure($element, [
                ...$attributes,
                'title' => $subscription->description ?? $subscription->id,
                'customerId' => is_string($subscription->customer) ? $subscription->customer : $subscription->customer->id,
            ]);

            event($event = new SubscriptionSyncing($element, $subscription));

            if (! $event->isValid) {
                Log::warning("Synchronization of Stripe subscription ID #$subscription->id was stopped by a plugin.");

                return false;
            }

            try {
                if ($element->getIsUnpublishedDraft()) {
                    $element = Drafts::applyDraft($element);
                } elseif (! Elements::saveElement($element)) {
                    Log::error("Failed to synchronize Stripe subscription ID #$subscription->id.", ['errors' => $element->errors()->toArray()]);

                    return false;
                }
            } catch (Throwable $e) {
                Log::error("Failed to synchronize Stripe subscription ID #$subscription->id: {$e->getMessage()}");

                return false;
            }

            /** @var Subscription $element */
            SubscriptionData::updateOrCreate(['stripeId' => $subscription->id], [
                'subscriptionId' => $element->id,
                'stripeStatus' => $attributes['stripeStatus'],
                'data' => $attributes['data'],
                'prices' => Json::encode($attributes['prices']),
            ]);

            if (Plugin::getInstance()->getSettings()->createUserIfMissing && Edition::isAtLeast(Edition::Pro)) {
                $this->ensureUser($subscription);
            }

            event(new SubscriptionSynced($element, $subscription));

            return true;
        });
    }

    public function deleteSubscriptionByStripeId(string $stripeId): void
    {
        if ($subscription = Subscription::find()->stripeId($stripeId)->status(null)->one()) {
            Elements::deleteElement($subscription);
        }

        SubscriptionData::where('stripeId', $stripeId)->delete();
    }

    /**
     * Cancels a subscription in Stripe, at the end of its period unless `$immediately`, and syncs it.
     *
     * @param  array<string, mixed>|null  $params  More params for the Stripe API
     */
    public function cancelSubscriptionByStripeId(string $stripeId, bool $immediately = false, ?array $params = null): bool
    {
        $client = $this->api->getClient();

        try {
            $subscription = $immediately
                ? $client->subscriptions->cancel($stripeId, $params)
                : $client->subscriptions->update($stripeId, [...($params ?? []), 'cancel_at_period_end' => true]);
        } catch (Throwable $e) {
            Log::error("Failed to cancel Stripe subscription ID #$stripeId: {$e->getMessage()}");

            return false;
        }

        return $this->createOrUpdateSubscription($subscription);
    }

    /**
     * Undoes a cancellation at period end in Stripe, and syncs the subscription.
     *
     * @param  array<string, mixed>|null  $params  More params for the Stripe API
     */
    public function resumeSubscriptionByStripeId(string $stripeId, ?array $params = null): bool
    {
        try {
            $subscription = $this->api->getClient()->subscriptions->update($stripeId, [
                ...($params ?? []),
                'cancel_at_period_end' => false,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to resume Stripe subscription ID #$stripeId: {$e->getMessage()}");

            return false;
        }

        return $this->createOrUpdateSubscription($subscription);
    }

    /**
     * Returns the unsaved subscription draft a checkout session created, by the UID in the session’s metadata.
     */
    public function getUnsavedDraftByUid(StripeSubscription $subscription): ?Subscription
    {
        $sessions = $this->api->getClient()->checkout->sessions->all(['subscription' => $subscription->id]);
        $uid = $sessions->first()?->metadata['craftSubscriptionUid'] ?? null;

        if ($uid === null) {
            return null;
        }

        $draft = Subscription::find()->uid($uid)->drafts()->status(null)->one();

        return $draft instanceof Subscription ? $draft : null;
    }

    /**
     * Creates a user for the subscription’s customer email, when none exists yet.
     */
    private function ensureUser(StripeSubscription $subscription): void
    {
        $customerId = is_string($subscription->customer) ? $subscription->customer : $subscription->customer->id;

        $customers = app(Customers::class);
        $customer = $customers->getCustomerByStripeId($customerId);

        if ($customer === null) {
            $stripeCustomer = $this->api->fetchCustomerById($customerId);
            $customers->createOrUpdateCustomer($stripeCustomer);
            $email = $stripeCustomer->email;
        } else {
            $email = $customer->email;
        }

        if ($email) {
            Users::ensureUserByEmail($email);
        }
    }
}
