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
        // Build our attribute set from the Stripe subscription data:
        $attributes = [
            'stripeId' => $subscription->id,
            'title' => $subscription->id,
            'stripeStatus' => $subscription->status,
            'data' => Json::decode($subscription->toJSON()),
        ];

        // Find the subscription data or create one
        /** @var SubscriptionDataRecord $subscriptionDataRecord */
        $subscriptionDataRecord = SubscriptionDataRecord::find()->where(['stripeId' => $subscription->id])->one() ?: new SubscriptionDataRecord();

        // Set attributes and save:
        $subscriptionDataRecord->setAttributes($attributes, false);


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

        // if we're still processing, we can save the subscriptionDataRecord
        $subscriptionDataRecord->save();

        if (!Craft::$app->getElements()->saveElement($subscriptionElement)) {
            Craft::error("Failed to synchronize Stripe subscription ID #{$subscription->id}.", 'stripe');

            return false;
        }

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
     * Returns array of subscriptions ready to display in the Vue Admin Table.
     *
     * @param array $subscriptions
     * @return array
     * @throws InvalidConfigException
     */
    public function getTableData(array $subscriptions): array
    {
        $tableData = [];
        $formatter = Craft::$app->getFormatter();

        foreach ($subscriptions as $subscription) {
            $data = [
                'id' => $subscription->stripeId,
                'title' => $subscription->stripeId,
                'status' => $subscription->stripeStatus,
                'period' => $formatter->asDatetime($subscription->data['current_period_start'], 'php:d M') .
                    ' to ' .
                    $formatter->asDatetime($subscription->data['current_period_end'], 'php:d M'),
                'canceledAt' => $formatter->asDatetime($subscription->data['canceled_at'], $formatter::FORMAT_WIDTH_SHORT),
                'endedAt' => $formatter->asDatetime($subscription->data['ended_at'], $formatter::FORMAT_WIDTH_SHORT),
                'created' => $formatter->asDatetime($subscription->data['created'], $formatter::FORMAT_WIDTH_SHORT),
                'url' => $subscription->getStripeEditUrl(),
            ];

            $products = $subscription->getProducts();
            $html = '<ul class="elements chips">';
            foreach ($products as $product) {
                $html .= '<li>' . Cp::elementChipHtml($product, ['size' => Cp::CHIP_SIZE_SMALL]) . '</li>';
            }
            $html .= '</ul>';
            $data['products'] = $html;

            $tableData[] = $data;
        }

        return $tableData;
    }
}
