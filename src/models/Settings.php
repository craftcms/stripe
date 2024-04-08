<?php

namespace craft\stripe\models;

use Craft;
use craft\base\Model;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

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
    public string $publicKey = '';

    /**
     * @var string
     */
    public string $productUriFormat = '';

    /**
     * @var string
     */
    public string $productTemplate = '';

    /**
     * @var mixed
     */
    private mixed $_productFieldLayout;

    /**
     * @var mixed
     */
    private mixed $_priceFieldLayout;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['secretKey', 'publicKey'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'secretKey' => Craft::t('stripe', 'Stripe Secret Key'),
            'publicKey' => Craft::t('stripe', 'Stripe Public Key'),
            'productUriFormat' => Craft::t('stripe', 'Product URI format'),
            'productTemplate' => Craft::t('stripe', 'Product Template'),
        ];
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getProductFieldLayout()
    {
        if (!isset($this->_productFieldLayout)) {
            $this->_productFieldLayout = Craft::$app->fields->getLayoutByType(Product::class);
        }

        return $this->_productFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setProductFieldLayout(mixed $fieldLayout): void
    {
        $this->_productFieldLayout = $fieldLayout;
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getPriceFieldLayout()
    {
        if (!isset($this->_priceFieldLayout)) {
            $this->_priceFieldLayout = Craft::$app->fields->getLayoutByType(Price::class);
        }

        return $this->_priceFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setPriceFieldLayout(mixed $fieldLayout): void
    {
        $this->_priceFieldLayout = $fieldLayout;
    }
}
