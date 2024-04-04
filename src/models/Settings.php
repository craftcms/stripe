<?php

namespace craft\stripe\models;

use Craft;
use craft\base\Model;

/**
 * Stripe settings
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Settings extends Model
{
    public string $secretKey = '';
    public string $publicKey = '';

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
        ];
    }
}
