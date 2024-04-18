<?php

namespace craft\stripe\services;

use Craft;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craft\stripe\elements\Subscription as SubscriptionElement;
use craft\stripe\events\StripeSubscriptionSyncEvent;
use craft\stripe\records\SubscriptionData as SubscriptionDataRecord;
use craft\stripe\Plugin;
use Stripe\Subscription as StripeSubscription;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Subscriptions service
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
        $subscriptions = $api->fetchAllSubscriptions();

        foreach ($subscriptions as $subscription) {
            $this->createOrUpdateSubscription($subscription);
        }

        // Remove any subscriptions that are no longer in Stripe just in case.
        $stripeIds = ArrayHelper::getColumn($subscriptions, 'id');
        $deletableSubscriptionElements = SubscriptionElement::find()->stripeId(['not', $stripeIds])->all();

        foreach ($deletableSubscriptionElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * This takes the stripe subscription data from the API and creates or updates a Subscription element.
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
            ->one();

        if ($subscriptionElement === null) {
            /** @var SubscriptionElement $subscriptionElement */
            $subscriptionElement = new SubscriptionElement();
        }

        // Build our attribute set from the Stripe subscription data:
        $attributes = [
            'stripeId' => $subscription->id,
            'title' => $subscription->description ?? $subscription->id,
            'stripeStatus' => $subscription->status,
            //'customerId' => $subscription->customer,
            'data' => Json::decode($subscription->toJSON()),
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

        if (!Craft::$app->getElements()->saveElement($subscriptionElement)) {
            Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}.", 'stripe');

            return false;
        }

        $attributes['subscriptionId'] = $subscriptionElement->id;

        // Find the subscription data or create one
        /** @var SubscriptionDataRecord $subscriptionDataRecord */
        $subscriptionDataRecord = SubscriptionDataRecord::find()->where(['stripeId' => $subscription->id])->one() ?: new SubscriptionDataRecord();
        $subscriptionDataRecord->setAttributes($attributes, false);
        $subscriptionDataRecord->save();

        return true;
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
}
