<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\models;

use Craft;
use craft\base\Model;
use craft\models\FieldLayout;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;

/**
 * Stripe settings
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Settings extends Model
{
    /**
     * @var string
     */
    public string $secretKey = '';

    /**
     * @var string
     */
    public string $publishableKey = '';

    /**
     * @var string
     * @deprecated in 1.2; stored in its own table now
     */
    public string $webhookSigningSecret = '';

    /**
     * @var string
     * @deprecated in 1.2; stored in its own table now
     */
    public string $webhookId = '';

    /**
     * @var string
     */
    public string $productUriFormat = '';

    /**
     * @var string
     */
    public string $productTemplate = '';

    /**
     * @var bool Whether updating credentialed user's email address should update Stripe customer(s)
     */
    public bool $syncChangedUserEmailsToStripe = true;

    /**
     * @var string|null
     */
    public ?string $defaultSuccessUrl = null;

    /**
     * @var string|null
     */
    public ?string $defaultCancelUrl = null;

    /**
     * @var mixed
     */
    private mixed $_productFieldLayout;

    /**
     * @var mixed
     */
    private mixed $_priceFieldLayout;

    /**
     * @var mixed
     */
    private mixed $_subscriptionFieldLayout;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['secretKey', 'publishableKey'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'secretKey' => Craft::t('stripe', 'Stripe Secret Key'),
            'publishableKey' => Craft::t('stripe', 'Stripe Publishable Key'),
            'webhookSigningSecret' => Craft::t('stripe', 'Stripe Webhook Signing Secret'),
            'productUriFormat' => Craft::t('stripe', 'Product URI format'),
            'productTemplate' => Craft::t('stripe', 'Product Template'),
        ];
    }

    /**
     * @return FieldLayout
     */
    public function getProductFieldLayout(): FieldLayout
    {
        if (!isset($this->_productFieldLayout)) {
            $this->_productFieldLayout = Craft::$app->fields->getLayoutByType(Product::class);
        }

        return $this->_productFieldLayout;
    }

    /**
     * @param FieldLayout $fieldLayout
     * @return void
     */
    public function setProductFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->_productFieldLayout = $fieldLayout;
    }

    /**
     * @return FieldLayout
     */
    public function getPriceFieldLayout(): FieldLayout
    {
        if (!isset($this->_priceFieldLayout)) {
            $this->_priceFieldLayout = Craft::$app->fields->getLayoutByType(Price::class);
        }

        return $this->_priceFieldLayout;
    }

    /**
     * @param FieldLayout $fieldLayout
     * @return void
     */
    public function setPriceFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->_priceFieldLayout = $fieldLayout;
    }

    /**
     * @return FieldLayout
     */
    public function getSubscriptionFieldLayout(): FieldLayout
    {
        if (!isset($this->_subscriptionFieldLayout)) {
            $this->_subscriptionFieldLayout = Craft::$app->fields->getLayoutByType(Subscription::class);
        }

        return $this->_subscriptionFieldLayout;
    }

    /**
     * @param FieldLayout $fieldLayout
     * @return void
     */
    public function setSubscriptionFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->_subscriptionFieldLayout = $fieldLayout;
    }
}
