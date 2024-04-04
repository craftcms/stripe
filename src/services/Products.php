<?php

namespace craft\stripe\services;

use Craft;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craft\stripe\elements\Product;
use craft\stripe\elements\Product as ProductElement;
use craft\stripe\records\ProductData as ProductDataRecord;
use craft\stripe\Plugin;
use Stripe\Product as StripeProduct;
use yii\base\Component;

/**
 * Products service
 */
class Products extends Component
{
    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllProducts(): void
    {
        $api = Plugin::getInstance()->getApi();
        $products = $api->getAllProducts();

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
        // Expand any JSON-like properties:
        //$metaFields = MetafieldsHelper::unpack($metafields);

        // Build our attribute set from the Stripe product data:
        $attributes = [
            'stripeId' => $product->id,
            'title' => $product->name,
            'stripeStatus' => $product->active ? Product::STRIPE_STATUS_ACTIVE : Product::STRIPE_STATUS_ARCHIVED,
            'data' => $product->toJSON(),
        ];

        // Find the product data or create one
        /** @var ProductDataRecord $productDataRecord */
        $productDataRecord = ProductDataRecord::find()->where(['stripeId' => $product->id])->one() ?: new ProductDataRecord();

        // Set attributes and save:
        $productDataRecord->setAttributes($attributes, false);
        $productDataRecord->save();

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

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $productElement->setAttributes($attributes, false);

//        $event = new ShopifyProductSyncEvent([
//            'element' => $productElement,
//            'source' => $product,
//        ]);
//        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_PRODUCT, $event);
//
//        if (!$event->isValid) {
//            Craft::warning("Synchronization of Shopify product ID #{$product->id} was stopped by a plugin.", 'shopify');
//
//            return false;
//        }

        if (!Craft::$app->getElements()->saveElement($productElement)) {
            Craft::error("Failed to synchronize Stripe product ID #{$product->id}.", 'stripe');

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
            $fieldsService->deleteLayoutsByType(Product::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Product::class)->id;
        $layout->type = Product::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate product caches
        Craft::$app->getElements()->invalidateCachesForElementType(Product::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Product::class);
    }
}
