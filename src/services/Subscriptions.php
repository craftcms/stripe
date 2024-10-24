<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\events\ConfigEvent;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craft\stripe\elements\Subscription;
use craft\stripe\elements\Subscription as SubscriptionElement;
use craft\stripe\events\StripeSubscriptionSyncEvent;
use craft\stripe\Plugin;
use craft\stripe\records\SubscriptionData as SubscriptionDataRecord;
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
    public const EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION = 'beforeSynchronizeSusbscription';

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
        $deletableSubscriptionElements = SubscriptionElement::find()->stripeId(['not', $stripeIds])->all();

        foreach ($deletableSubscriptionElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * This takes the Stripe subscription data from the API and creates or updates a Subscription element.
     *
     * @param StripeSubscription $subscription
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateSubscription(StripeSubscription $subscription): bool
    {
        // Find the subscription element or create one
        /** @var SubscriptionElement|null $subscriptionElement */
        $subscriptionElement = SubscriptionElement::find()
            ->stripeId($subscription->id)
            ->status(null)
            ->one() ?? new SubscriptionElement();

        return $this->createOrUpdateSubscriptionElement($subscription, $subscriptionElement);
    }

    /**
     * Takes the Stripe subscription data from the API a Subscription element and updates the element with the data.
     *
     * @param StripeSubscription $subscription
     * @param SubscriptionElement $subscriptionElement
     * @return bool Whether the synchronization succeeded.
     * @since 1.2
     */
    public function createOrUpdateSubscriptionElement(StripeSubscription $subscription, SubscriptionElement $subscriptionElement): bool
    {
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
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Stripe subscription ID #{$subscription->id} was stopped by a plugin.", 'stripe');

            return false;
        }

        if ($subscriptionElement->getIsUnpublishedDraft()) {
            try {
                $subscriptionElement = Craft::$app->getDrafts()->applyDraft($subscriptionElement);
            } catch (\Exception $e) {
                Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}. {$e->getMessage()}", 'stripe');

                return false;
            }
        } else {
            if (!Craft::$app->getElements()->saveElement($subscriptionElement)) {
                Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}.", 'stripe');

                return false;
            }
        }

        $attributes['subscriptionId'] = $subscriptionElement->id;

        // Find the subscription data or create one
        /** @var SubscriptionDataRecord $subscriptionDataRecord */
        $subscriptionDataRecord = SubscriptionDataRecord::find()->where(['stripeId' => $subscription->id])->one() ?: new SubscriptionDataRecord();
        $subscriptionDataRecord->setAttributes($attributes, false);

        return $subscriptionDataRecord->save();
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
            $fieldsService->deleteLayoutsByType(SubscriptionElement::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(SubscriptionElement::class)->id;
        $layout->type = SubscriptionElement::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate subscription caches
        Craft::$app->getElements()->invalidateCachesForElementType(SubscriptionElement::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(SubscriptionElement::class);
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
            if ($subscription = SubscriptionElement::find()->stripeId($stripeId)->one()) {
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
                $stripe->subscriptions->cancel($stripeId);
            } else {
                $stripe->subscriptions->update($stripeId, [
                    'cancel_at_period_end' => true,
                ]);
            }
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
     * @return SubscriptionElement
     * @since 1.2
     */
    public function getUnsavedDraftByUid(StripeSubscription $subscription): SubscriptionElement
    {
        // get checkout session by subscription id
        $stripe = Plugin::getInstance()->getApi()->getClient();
        $sessionsList = $stripe->checkout->sessions->all(['subscription' => $subscription->id]);

        if ($sessionsList->isEmpty()) {
            return new SubscriptionElement();
        }

        // if we found one, get the metadata from the session
        $checkoutSession = $sessionsList->first();
        $uid = $checkoutSession->metadata['craftSubscriptionUid'] ?? null;

        if ($uid === null) {
            return new SubscriptionElement();
        }

        // try to find an unsaved Subscription element by the uid from the session's metadata
        return SubscriptionElement::find()
            ->uid($uid)
            ->status(null)
            ->drafts()
            ->one() ?? new SubscriptionElement();
    }
}
