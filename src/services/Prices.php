<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craft\stripe\elements\Price as PriceElement;
use craft\stripe\elements\Product as ProductElement;
use craft\stripe\events\StripePriceSyncEvent;
use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\Plugin;
use craft\stripe\records\PriceData as PriceDataRecord;
use Stripe\Price as StripePrice;
use yii\base\Component;

/**
 * Prices service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Prices extends Component
{
    /**
     * @event StripePriceSyncEvent Event triggered just before Stripe price] data is saved to a price element.
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripePriceSyncEvent;
     * use craft\stripe\services\Prices;
     * use yii\base\Event;
     *
     * Event::on(
     *     Prices::class,
     *     Prices::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
     *     function(StripePriceSyncEvent $event) {
     *         // Cancel the sync if a flag is set via a Stripe metadata:
     *         if ($event->element->data['metadata']['do_not_sync'] ?? false) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SYNCHRONIZE_PRICE = 'beforeSynchronizePrice';

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllPrices(): void
    {
        $api = Plugin::getInstance()->getApi();
        $prices = $api->fetchAllPrices();

        foreach ($prices as $price) {
            $this->createOrUpdatePrice($price);
        }

        // Remove any prices that are no longer in Stripe just in case.
        $stripeIds = ArrayHelper::getColumn($prices, 'id');
        $deletablePriceElements = PriceElement::find()->stripeId(['not', $stripeIds])->all();

        foreach ($deletablePriceElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * This takes the stripe Price data from the API and creates or updates a price element.
     *
     * @param StripePrice $price
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdatePrice(StripePrice $price): bool
    {
        // Find the price element or create one
        /** @var PriceElement|null $priceElement */
        $priceElement = PriceElement::find()
            ->stripeId($price->id)
            ->status(null)
            ->one();

        if ($priceElement === null) {
            /** @var PriceElement $priceElement */
            $priceElement = new PriceElement();
        }

        // Build our attribute set from the Stripe price data:
        $attributes = [
            //'productId' => $price->product,
            'stripeId' => $price->id,
            'title' => PriceHelper::asUnitPrice($price),
            'stripeStatus' => $price->active ? PriceElement::STRIPE_STATUS_ACTIVE : PriceElement::STRIPE_STATUS_ARCHIVED,
            'data' => Json::decode($price->toJSON()),
            'unitAmount' => PriceHelper::asUnitAmountNumber($price),
        ];

        if (!empty($price->currency_options)) {
            $attributes['currencies'] = array_keys($price->currency_options->toArray());
        }

        // get the product for this price
        $productElement = ProductElement::find()
            ->stripeId($price->product)
            ->status(null)
            //->with('data')
            ->one();

        if ($productElement) {
            //$attributes['productDataId'] = $productElement->data->id;
            $attributes['ownerId'] = $productElement->id;
            $attributes['primaryOwnerId'] = $productElement->id;
        }

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $priceElement->setAttributes($attributes, false);

        $event = new StripePriceSyncEvent([
            'element' => $priceElement,
            'source' => $price,
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_PRICE, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Stripe price ID #{$price->id} was stopped by a plugin.", 'stripe');

            return false;
        }

        if (!Craft::$app->getElements()->saveElement($priceElement)) {
            Craft::error("Failed to synchronize Stripe price ID #{$price->id}.", 'stripe');

            return false;
        }

        $attributes['priceId'] = $priceElement->id;

        // Find the price data or create one
        /** @var PriceDataRecord $priceDataRecord */
        $priceDataRecord = PriceDataRecord::find()->where(['stripeId' => $price->id])->one() ?: new PriceDataRecord();
        $priceDataRecord->setAttributes($attributes, false);

        return $priceDataRecord->save();
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
            $fieldsService->deleteLayoutsByType(PriceElement::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(PriceElement::class)->id;
        $layout->type = PriceElement::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate price caches
        Craft::$app->getElements()->invalidateCachesForElementType(PriceElement::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(PriceElement::class);
    }

    /**
     * Returns price by Stripe id.
     *
     * @param string $stripeId
     * @return PriceElement|null
     */
    public function getPriceByStripeId(string $stripeId): ?PriceElement
    {
        return PriceElement::find()->stripeId($stripeId)->one();
    }

    /**
     * Deletes price by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deletePriceByStripeId(string $stripeId): void
    {
        if ($stripeId) {
            if ($price = PriceElement::find()->stripeId($stripeId)->one()) {
                Craft::$app->getElements()->deleteElement($price, false);
            }
            if ($priceData = PriceDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
                $priceData->delete();
            }
        }
    }
}
