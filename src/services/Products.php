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
use craft\stripe\elements\Product;
use craft\stripe\elements\Product as ProductElement;
use craft\stripe\elements\Subscription;
use craft\stripe\events\StripeProductSyncEvent;
use craft\stripe\Plugin;
use craft\stripe\records\ProductData as ProductDataRecord;
use Stripe\Product as StripeProduct;
use yii\base\Component;

/**
 * Products service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Products extends Component
{
    /**
     * @event StripeProductSyncEvent Event triggered just before Stripe product data is saved to a product element.
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripeProductSyncEvent;
     * use craft\stripe\services\Products;
     * use yii\base\Event;
     *
     * Event::on(
     *     Products::class,
     *     Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
     *     function(StripeProductSyncEvent $event) {
     *         // Cancel the sync if a flag is set via a Stripe metadata:
     *         if ($event->element->data['metadata']['do_not_sync'] ?? false) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SYNCHRONIZE_PRODUCT = 'beforeSynchronizeProduct';

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllProducts(): void
    {
        $api = Plugin::getInstance()->getApi();
        $products = $api->fetchAllProducts();

        foreach ($products as $product) {
            $this->createOrUpdateProduct($product);
        }

        // Remove any products that are no longer in Stripe just in case.
        $stripeIds = ArrayHelper::getColumn($products, 'id');
        $deletableProductElements = ProductElement::find()->stripeId(['not', $stripeIds])->all();

        foreach ($deletableProductElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * This takes the stripe Product data from the API and creates or updates a product element.
     *
     * @param StripeProduct $product
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateProduct(StripeProduct $product): bool
    {
        // Find the product element or create one
        /** @var ProductElement|null $productElement */
        $productElement = ProductElement::find()
            ->stripeId($product->id)
            ->status(null)
            ->one();

        if ($productElement === null) {
            /** @var ProductElement $productElement */
            $productElement = new ProductElement();
        }

        // Build our attribute set from the Stripe product data:
        $attributes = [
            'stripeId' => $product->id,
            'title' => $product->name,
            'stripeStatus' => $product->active ? ProductElement::STRIPE_STATUS_ACTIVE : ProductElement::STRIPE_STATUS_ARCHIVED,
            'data' => Json::decode($product->toJSON()),
        ];

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $productElement->setAttributes($attributes, false);

        $event = new StripeProductSyncEvent([
            'element' => $productElement,
            'source' => $product,
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_PRODUCT, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Stripe product ID #{$product->id} was stopped by a plugin.", 'stripe');

            return false;
        }

        if (!Craft::$app->getElements()->saveElement($productElement)) {
            Craft::error("Failed to synchronize Stripe product ID #{$product->id}.", 'stripe');

            return false;
        }

        $attributes['productId'] = $productElement->id;

        // Find the product data or create one
        /** @var ProductDataRecord $productDataRecord */
        $productDataRecord = ProductDataRecord::find()->where(['stripeId' => $product->id])->one() ?: new ProductDataRecord();
        $productDataRecord->setAttributes($attributes, false);

        return $productDataRecord->save();
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
            $fieldsService->deleteLayoutsByType(ProductElement::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(ProductElement::class)->id;
        $layout->type = ProductElement::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate product caches
        Craft::$app->getElements()->invalidateCachesForElementType(ProductElement::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(ProductElement::class);
    }

    /**
     * Get all the products that belong to given subscription stripe id
     *
     * @param string|null $subscriptionId
     * @return array|null
     */
    public function getProductsBySubscriptionId(?string $subscriptionId): array|null
    {
        if ($subscriptionId === null) {
            return null;
        }

        // get subscription
        $subscription = Subscription::find()->stripeId($subscriptionId)->one();

        if ($subscription === null) {
            return null;
        }

        // get product ids from the list of items
        $productIds = array_map(fn($item) => $item['price']['product'], $subscription->getData()['items']['data']);

        $products = [];
        // get each product element by the stripeId
        foreach ($productIds as $productId) {
            $products[] = Product::find()->stripeId($productId)->one();
        }

        return $products;
    }

    /**
     * Deletes product by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteProductByStripeId(string $stripeId): void
    {
        if ($stripeId) {
            if ($product = ProductElement::find()->stripeId($stripeId)->one()) {
                Craft::$app->getElements()->deleteElement($product, true);
            }
            if ($productData = ProductDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
                $productData->delete();
            }
        }
    }
}
