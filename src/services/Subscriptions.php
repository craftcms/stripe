<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\errors\MutexException;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\i18n\Translation;
use craft\models\FieldLayout;
use craft\stripe\db\Table;
use craft\stripe\elements\Price;
use craft\stripe\elements\Subscription;
use craft\stripe\events\GrantGroupAssignmentEvent;
use craft\stripe\events\RevokeGroupAssignmentEvent;
use craft\stripe\events\StripeSubscriptionStatusChangeEvent;
use craft\stripe\events\StripeSubscriptionSyncEvent;
use craft\stripe\models\Customer;
use craft\stripe\models\Message;
use craft\stripe\Plugin;
use craft\stripe\records\SubscriptionData as SubscriptionDataRecord;
use Stripe\Customer as StripeCustomer;
use Stripe\Subscription as StripeSubscription;
use yii\base\Component;

/**
 * Subscriptions service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Subscriptions extends Component
{
    /**
     * @event StripeSubscriptionSyncEvent Event triggered just before Stripe subscription data is saved to a subscription element.
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripeSubscriptionSyncEvent;
     * use craft\stripe\services\Subscriptions;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION,
     *     function(StripeSubscriptionSyncEvent $event) {
     *         // Cancel the sync if a flag is set via a Stripe metadata:
     *         if ($event->element->data['metadata']['do_not_sync'] ?? false) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION = 'beforeSynchronizeSubscription';

    /**
     * @event StripeSubscriptionSyncEvent Event triggered after Stripe subscription data is saved to a subscription element.
     * @since 1.4.0
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripeSubscriptionSyncEvent;
     * use craft\stripe\services\Subscriptions;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION,
     *     function(StripeSubscriptionSyncEvent $event) {
     *         // Cancel the sync if a flag is set via a Stripe metadata:
     *         if ($event->element->data['metadata']['do_not_sync'] ?? false) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION = 'afterSynchronizeSubscription';

    /**
     * @event StripeSubscriptionStatusChangeEvent Event triggered when a subscription changes status.
     * @since 1.7.0
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripeSubscriptionStatusChangeEvent;
     * use craft\stripe\services\Subscriptions;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_SUBSCRIPTION_STATUS_CHANGE,
     *     function(StripeSubscriptionStatusChangeEvent $event) {
     *         if ($event->newStatus !== StripeSubscription::STATUS_CANCELED) {
     *             // We only want to act on cancellations!
     *             return;
     *         }
     *
     *         $perpetualVipProductIds = Product::find()
     *             ->grantsPerpetualVipStatus(true)
     *             ->ids();
     *         $subscriptionProductIds = ArrayHelper::getColumn($subscription->getProducts(), 'id');
     *
     *         // Suppress default cancellation logic for those products:
     *         $hasVipProducts = count(array_intersect($perpetualVipProductIds, $subscriptionProductIds)) > 0;
     *
     *         if ($hasVipProducts) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_SUBSCRIPTION_STATUS_CHANGE = 'subscriptionStatusChange';

    /**
     * @event craft\stripe\events\GrantGroupAssignmentEvent
     */
    public const EVENT_BEFORE_GRANT_GROUP = 'beforeGrantGroup';

    /**
     * @event craft\stripe\events\RevokeGroupAssignmentEvent
     */
    public const EVENT_BEFORE_REVOKE_GROUP = 'beforeRevokeGroup';

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllSubscriptions(): void
    {
        $api = Plugin::getInstance()->getApi();

        $iterator = $api->fetchAllIterator('subscriptions', [
            'status' => 'all',
            'expand' => $api->prepExpandForFetchAll(Subscription::$expandParams),
        ]);

        $stripeIds = [];

        foreach ($iterator as $batch) {
            /** @var \Stripe\Subscription[] $batch */
            foreach ($batch as $subscription) {
                $stripeIds[] = $subscription->id;
                $this->createOrUpdateSubscription($subscription);
            }
        }

        // Remove any subscriptions that are no longer in Stripe just in case.
        $deletableSubscriptionElements = Subscription::find()->stripeId(['not', $stripeIds])->all();

        foreach ($deletableSubscriptionElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * Sync all subscriptions for a specific customer from Stripe
     *
     * @param StripeCustomer $stripeCustomer
     * @return int
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncCustomerSubscriptions(StripeCustomer $stripeCustomer): int
    {
        $api = Plugin::getInstance()->getApi();

        $iterator = $api->fetchAllIterator('subscriptions', [
            'customer' => $stripeCustomer->id,
            'status' => 'all',
            'expand' => $api->prepExpandForFetchAll(Subscription::$expandParams),
        ]);

        $count = 0;
        foreach ($iterator as $batch) {
            /** @var \Stripe\Subscription[] $batch */
            foreach ($batch as $subscription) {
                if ($this->createOrUpdateSubscription($subscription)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * This takes the Stripe subscription data from the API and creates or updates a Subscription element.
     *
     * @param StripeSubscription $subscription
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateSubscription(StripeSubscription $subscription): bool
    {
        // Acquire lock before lookup to prevent race conditions where two webhooks
        // both find no existing element and create duplicates
        $lockKey = "stripe-subscription:$subscription->id";
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($lockKey, 15)) {
            throw new MutexException($lockKey, 'Could not acquire a lock to create or update subscription.');
        }

        try {
            // Find the subscription element or create one (now safely inside the lock)
            /** @var SubscriptionElement|null $subscriptionElement */
            $subscriptionElement = SubscriptionElement::find()
                ->stripeId($subscription->id)
                ->status(null)
                ->one() ?? new SubscriptionElement();

            return $this->createOrUpdateSubscriptionElement($subscription, $subscriptionElement, false);
        } finally {
            $mutex->release($lockKey);
        }
    }

    /**
     * Takes the Stripe subscription data from the API a Subscription element and updates the element with the data.
     *
     * @param StripeSubscription $subscription
     * @param SubscriptionElement $subscriptionElement
     * @param bool $acquireLock Whether to acquire a mutex lock (set to false if caller already holds the lock)
     * @return bool Whether the synchronization succeeded.
     * @since 1.2
     */
    public function createOrUpdateSubscriptionElement(StripeSubscription $subscription, SubscriptionElement $subscriptionElement, bool $acquireLock = true): bool
    {
        // Duplicates seem to be possible: https://github.com/craftcms/stripe/issues/44
        $lockKey = "stripe-subscription:$subscription->id";
        $mutex = Craft::$app->getMutex();
        if ($acquireLock && !$mutex->acquire($lockKey, 15)) {
            throw new MutexException($lockKey, 'Could not acquire a lock to create or update subscription.');
        }

        // Record states before the update:
        $isNew = !isset($subscriptionElement->id) || $subscriptionElement->getIsDraft();
        // Subscriptions default to `active` in our system, which is somewhat misleading:
        $originalStatus = $isNew ? null : $subscriptionElement->stripeStatus;

        // Build our attribute set from the Stripe subscription data:
        $attributes = [
            'stripeId' => $subscription->id,
            'title' => $subscription->description ?? $subscription->id,
            'stripeStatus' => $subscription->status,
            'data' => Json::decode($subscription->toJSON()),
            'prices' => array_map(fn($item) => $item['price']['id'], $subscription->items->data),
        ];

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $subscriptionElement->setAttributes($attributes, false);

        $event = new StripeSubscriptionSyncEvent([
            'element' => $subscriptionElement,
            'source' => $subscription,
            'isNew' => $isNew,
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Stripe subscription ID #{$subscription->id} was stopped by a plugin.", 'stripe');
            if ($acquireLock) {
                $mutex->release($lockKey);
            }

            return false;
        }

        $settings = Plugin::getInstance()->getSettings();
        if ($settings->createUserIfMissing && Craft::$app->edition->value >= CmsEdition::Pro->value) {
            $user = $this->ensureUser($subscription, $subscriptionElement);
            $subscriptionElement->setUser($user);
        }

        if ($subscriptionElement->getIsUnpublishedDraft()) {
            try {
                $subscriptionElement = Craft::$app->getDrafts()->applyDraft($subscriptionElement);
            } catch (\Exception $e) {
                Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}. {$e->getMessage()}", 'stripe');
                if ($acquireLock) {
                    $mutex->release($lockKey);
                }

                return false;
            }
        } else {
            if (!Craft::$app->getElements()->saveElement($subscriptionElement)) {
                Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}.", 'stripe');
                if ($acquireLock) {
                    $mutex->release($lockKey);
                }

                return false;
            }
        }

        $attributes['subscriptionId'] = $subscriptionElement->id;

        // Find the subscription data or create one
        /** @var SubscriptionDataRecord $subscriptionDataRecord */
        $subscriptionDataRecord = SubscriptionDataRecord::find()->where(['stripeId' => $subscription->id])->one() ?: new SubscriptionDataRecord();
        $subscriptionDataRecord->setAttributes($attributes, false);

        $result = $subscriptionDataRecord->save();

        if ($acquireLock) {
            $mutex->release($lockKey);
        }

        // Handle potential status changes:
        $this->handleStatusChange($subscriptionElement, $originalStatus);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION)) {
            $event = new StripeSubscriptionSyncEvent([
                'element' => $subscriptionElement,
                'source' => $subscription,
                'isNew' => $isNew,
            ]);
            $this->trigger(self::EVENT_AFTER_SYNCHRONIZE_SUBSCRIPTION, $event);
        }

        return $result;
    }

    /**
     * Handle field layout change
     *
     * @throws \Throwable
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfig::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Subscription::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Subscription::class)->id;
        $layout->type = Subscription::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate subscription caches
        Craft::$app->getElements()->invalidateCachesForElementType(Subscription::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Subscription::class);
    }

    /**
     * Deletes subscription by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteSubscriptionByStripeId(string $stripeId): void
    {
        if ($stripeId) {
            if ($subscription = Subscription::find()->stripeId($stripeId)->one()) {
                Craft::$app->getElements()->deleteElement($subscription, false);
            }
            if ($subscriptionData = SubscriptionDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
                $subscriptionData->delete();
            }
        }
    }

    /**
     * Cancels subscription by Stripe id.
     *
     * @param string $stripeId
     * @param bool $immediately
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function cancelSubscriptionByStripeId(string $stripeId, bool $immediately = false): bool
    {
        $stripe = Plugin::getInstance()->getApi()->getClient();

        try {
            if ($immediately) {
                $subscription = $stripe->subscriptions->cancel($stripeId);
            } else {
                $subscription = $stripe->subscriptions->update($stripeId, [
                    'cancel_at_period_end' => true,
                ]);
            }
            Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription($subscription);
        } catch (\Exception $exception) {
            Craft::error($exception->getMessage(), 'stripe');
            return false;
        }

        return true;
    }

    /**
     * Resumes subscription by Stripe id.
     *
     * @param string $stripeId
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @since 1.5.0
     */
    public function resumeSubscriptionByStripeId(string $stripeId): bool
    {
        $stripe = Plugin::getInstance()->getApi()->getClient();

        try {
            $subscription = $stripe->subscriptions->update($stripeId, [
                'cancel_at_period_end' => false,
            ]);
            Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription($subscription);
        } catch (\Exception $exception) {
            Craft::error($exception->getMessage(), 'stripe');
            return false;
        }

        return true;
    }

    /**
     * Return Subscription element draft by its uid stored in the Stripe's checkout session's metadata.
     *
     * @param StripeSubscription $subscription
     * @return Subscription
     * @since 1.2
     */
    public function getUnsavedDraftByUid(StripeSubscription $subscription): Subscription
    {
        // get checkout session by subscription id
        $stripe = Plugin::getInstance()->getApi()->getClient();
        $sessionsList = $stripe->checkout->sessions->all(['subscription' => $subscription->id]);

        if ($sessionsList->isEmpty()) {
            return new Subscription();
        }

        // if we found one, get the metadata from the session
        $checkoutSession = $sessionsList->first();
        $uid = $checkoutSession->metadata['craftSubscriptionUid'] ?? null;

        if ($uid === null) {
            return new Subscription();
        }

        // try to find an unsaved Subscription element by the uid from the session's metadata
        return Subscription::find()
            ->uid($uid)
            ->status(null)
            ->drafts()
            ->one() ?? new Subscription();
    }

    /**
     * Grants permissions for the passed Subscription.
     *
     * The `$price` argument is used to override the default behavior, which uses the currently-associated Price(s) to determine which groups are granted. In situations where we need to swap out permissions (i.e. switching “plans”), the Subscription will only have access to the new {@see Price}.
     *
     * @param Subscription $subscription
     * @param Price[]|null $prices
     * @return bool
     */
    public function grantGroupsForSubscription(Subscription $subscription, ?array $prices = null): bool
    {
        $prices = $prices ?? $subscription->getPrices();
        $user = $subscription->getUser();

        if (!$user) {
            $this->logActivity($subscription->id, Translation::prep('stripe', 'No user exists for this subscription.'));

            return false;
        }

        foreach ($prices as $price) {
            $groups = $price->getUserGroupAssignments();

            foreach ($groups as $group) {
                // They may already be in it:
                if ($user->isInGroup($group->id)) {
                    $this->logActivity($subscription->id, Translation::prep('stripe', 'The user already belonged to group ID #{groupId} ({groupName}) when they signed up for {priceName}.', [
                        'groupId' => $group->id,
                        'groupName' => $group->name,
                        'priceName' => $price->title,
                    ]));

                    continue;
                }

                // Fire an event to give the system an opportunity to alter the behavior:
                $event = new GrantGroupAssignmentEvent([
                    'subscription' => $subscription,
                    'price' => $price,
                    'user' => $user,
                    'group' => $group,
                ]);

                $this->trigger(self::EVENT_BEFORE_GRANT_GROUP, $event);

                // If it was prevented, log a message and continue:
                if (!$event->isValid) {
                    $this->logActivity(
                        $subscription->id,
                        Translation::prep('stripe', 'A plugin prevented the user from being added to group ID #{groupId} ({groupName}).', [
                            'groupId' => $group->id,
                            'groupName' => $group->name,
                        ])
                    );

                    continue;
                }

                // Get current groups, and append the granted one:
                $subscriberGroups = $user->getGroups();
                $subscriberGroups[] = $group;

                // Assign by plucking their IDs:
                Craft::$app->getUsers()->assignUserToGroups($user->id, ArrayHelper::getColumn($subscriberGroups, 'id'));

                // Set them back on the User, clearing the cached values:
                $user->setGroups($subscriberGroups);

                $this->logActivity(
                    $subscription->id,
                    Translation::prep('stripe', 'Added the user to group ID #{groupId} ({groupName}) when they subscribed to {priceName}.', [
                        'groupId' => $group->id,
                        'groupName' => $group->name,
                        'priceName' => $price->title,
                    ])
                );
            }
        }

        return true;
    }

    /**
     * Removes subscribers from user groups based on its Price’s configuration.
     *
     * As with the sister `grant` method, this one accepts an explicit list of {@see Price}s so that we can appropriately handle moving *away from* or *to* a given “plan.”
     *
     * @param Subscription $subscription
     * @param Price[]|null $prices
     * @return bool
     */
    public function revokeGroupsForSubscription(Subscription $subscription, ?array $prices = null): bool
    {
        $prices = $prices ?? $subscription->getPrices();
        $user = $subscription->getUser();

        // Get the User's *other*, *live* Subscriptions, if any:
        $subscriptions = Subscription::find()
            ->user($user)
            ->id(['not', $subscription->id])
            ->status('live')
            ->all();

        // Fetch the Prices for those Subscriptions, and gather the granted user groups...
        $protectedGroups = array_reduce($subscriptions, function($groups, $sub) {
            $subGroups = [];

            foreach ($sub->getPrices() as $price) {
                /** @var Price $price */
                array_merge($subGroups, $price->getUserGroupAssignments());
            }

            return array_merge($groups, $subGroups);
        }, []);

        $protectedGroupIds = array_unique(ArrayHelper::getColumn($protectedGroups, 'id'));

        // Loop over the prices in the Subscription and pull the user out of the designated groups:
        foreach ($prices as $price) {
            $groups = $price->getUserGroupAssignments();

            foreach ($groups as $group) {
                // We also don't want to revoke a permission granted by a different (active) Subscription:
                if (in_array($group->id, $protectedGroupIds)) {
                    $this->logActivity(
                        $subscription->id,
                        Translation::prep('stripe', 'Another active subscription prevented the user from being removed from group ID #{groupId} ({groupName}).', [
                            'groupId' => $group->id,
                            'groupName' => $group->name,
                        ])
                    );

                    continue;
                }

                // They may just not be in it:
                if (!$user->isInGroup($group->id)) {
                    $this->logActivity(
                        $subscription->id,
                        Translation::prep('stripe', 'The user wasn’t in group ID #{groupId} ({groupName}), so no action was taken.', [
                            'groupId' => $group->id,
                            'groupName' => $group->name,
                        ])
                    );

                    continue;
                }

                // Fire an event to give the system an opportunity to alter the behavior:
                $event = new RevokeGroupAssignmentEvent([
                    'subscription' => $subscription,
                    'user' => $user,
                    'group' => $group,
                    'price' => $price,
                ]);

                $this->trigger(self::EVENT_BEFORE_REVOKE_GROUP, $event);

                // If it was prevented, log a message and continue:
                if (!$event->isValid) {
                    $this->logActivity(
                        $subscription->id,
                        Translation::prep('stripe', 'A plugin prevented the user from being removed from group ID #{groupId} ({groupName}).', [
                            'groupId' => $group->id,
                            'groupName' => $group->name
                        ])
                    );

                    continue;
                }

                // Get current groups, and filter out this one:
                $newGroups = array_filter($user->getGroups(), function ($g) use ($group) {
                    if ($g->id === $group->id) {
                        return false;
                    }

                    return true;
                });

                // Assign the new groups:
                Craft::$app->getUsers()->assignUserToGroups($user->id, ArrayHelper::getColumn($newGroups, 'id'));

                // Set them back on the User, clearing the cached values:
                $user->setGroups($newGroups);

                $this->logActivity(
                    $subscription->id,
                    Translation::prep('stripe', 'The user was removed from group ID #{groupId} ({groupName}).', [
                        'groupId' => $group->id,
                        'groupName' => $group->name
                    ])
                );
            }
        }

        return true;
    }
    /**
     * Gets user group assignment logs for the provided subscription.
     *
     * @param Subscription $subscription
     * @return Message[]
     */
    public function getLogs(Subscription $subscription): array
    {
        $rows = (new Query)
            ->from([Table::SUBSCRIPTIONLOGS])
            ->select([
                'id',
                'message',
                'subscriptionId',
                'dateCreated',
            ])
            ->where([
                'subscriptionId' => $subscription->id,
            ])
            ->orderBy('dateCreated DESC')
            ->all();

        return array_map(function($row) {
            return new Message($row);
        }, $rows);
    }

    /**
     * Ensures that a user with given email address is created if one doesn't already exist.
     *
     * @param StripeSubscription $subscription
     * @param Subscription $subscriptionElement
     * @return User|null
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function ensureUser(StripeSubscription $subscription, Subscription $subscriptionElement): ?User
    {
        $plugin = Plugin::getInstance();
        $customer = $subscriptionElement->getCustomer();
        $syncCustomerData = false;

        // if we don't have a customer in our DB (customerdata table), then get the customer from Stripe
        if (!$customer) {
            $customer = $plugin->getApi()->fetchCustomerById($subscription->customer);
            // in this case, we want to ensure the customer data is stored in our database
            $syncCustomerData = true;
        }
        /** @var Customer|StripeCustomer $customer */
        if ($customer->email) {
            $user = Craft::$app->getUsers()->ensureUserByEmail($customer->email);

            if ($syncCustomerData) {
                $plugin->getCustomers()->createOrUpdateCustomer($customer);
            }

            return $user;
        }

        return null;
    }

    /**
     * Handles a Subscription changing status.
     *
     * @param Subscription $subscription
     * @param string|null $oldStatus
     */
    private function handleStatusChange(Subscription $subscription, ?string $oldStatus = null): void
    {
        $newStatus = $subscription->stripeStatus;

        // Did the status actually change?
        if ($newStatus === $oldStatus) {
            return;
        }

        // Emit an event to allow plugins to suppress default handlers:
        if ($this->hasEventHandlers(self::EVENT_SUBSCRIPTION_STATUS_CHANGE)) {
            $event = new StripeSubscriptionStatusChangeEvent([
                'subscription' => $subscription,
                'newStatus' => $newStatus,
                'oldStatus' => $oldStatus,
            ]);
            $this->trigger(self::EVENT_SUBSCRIPTION_STATUS_CHANGE, $event);

            // Bail now if a plugin indicates it has handled the status change:
            if ($event->isValid) {
                Craft::warning('A plugin prevented the normal subscription status change handlers from running.', 'stripe');
                $this->logActivity($subscription->id, Translation::prep('stripe', 'The subscription changed statuses, but handlers were skipped.'));

                return;
            }
        }

        // We only care about transitions into some statuses.
        if ($newStatus === StripeSubscription::STATUS_ACTIVE) {
            if (in_array($oldStatus, [null, StripeSubscription::STATUS_INCOMPLETE])) {
                // It just began, possibly after some billing trouble.
                $this->grantGroupsForSubscription($subscription);
            } else if ($oldStatus === StripeSubscription::STATUS_TRIALING) {
                // The trial period is over. Nothing should change!
            } else if (in_array($oldStatus, [StripeSubscription::STATUS_PAST_DUE, StripeSubscription::STATUS_UNPAID])) {
                // Billing issues were resolved. This should undo anything
            }
        } else if ($newStatus === StripeSubscription::STATUS_TRIALING) {
            if ($oldStatus === null) {
                // The customer just started a trial. This is treated the same way as a new, active subscription:
                $this->grantGroupsForSubscription($subscription);
            }
        } else if ($newStatus === StripeSubscription::STATUS_CANCELED) {
            // Always revoke permissions:
            $this->revokeGroupsForSubscription($subscription);

            if (in_array($oldStatus, [StripeSubscription::STATUS_ACTIVE, StripeSubscription::STATUS_TRIALING])) {
                // The subscription ended while in good standing (but potentially before actually starting).
            } else if (in_array($oldStatus, [StripeSubscription::STATUS_PAST_DUE, StripeSubscription::STATUS_UNPAID])) {
                // The subscription ended with outstanding invoices.
            }
        } else if (in_array($newStatus, [StripeSubscription::STATUS_PAST_DUE, StripeSubscription::STATUS_UNPAID])) {
            // We’re in a delinquent payment situation!
            if ($oldStatus === StripeSubscription::STATUS_ACTIVE) {
                // Should we revoke permissions while they sort out payment?
            }
        }
    }

    /**
     * Logs a message against the specified subscription.
     *
     * Messages should be prepared using {@see Translation::prep()} instead of {@see Craft::t()}, so that they can be displayed in the current user’s language.
     * @param int $subscriptionId
     * @param string $message
     * @return bool
     */
    private function logActivity(int $subscriptionId, string $message): bool
    {
        $rows = Db::insert(Table::SUBSCRIPTIONLOGS, [
            'subscriptionId' => $subscriptionId,
            'message' => $message,
        ]);

        return $rows > 0;
    }
}
