<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\stripe\models;

use craft\stripe\base\Model;
use craft\stripe\Plugin;

/**
 * Stripe payment method model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PaymentMethod extends Model
{
    /**
     * Return URL to edit the payment method in Stripe Dashboard
     *
     * @return string
     */
    public function getStripeEditUrl(): string
    {
        return Plugin::getInstance()->stripeBaseUrl . "/customers/{$this->getData()['customer']}";
    }

//    /**
//     * @inheritdoc
//     */
//    protected function defineRules(): array
//    {
//        $rules = parent::defineRules();
//        $rules[] = [['reference'], UniqueValidator::class, 'targetClass' => CustomerRecord::class];
//        $rules[] = [['gatewayId', 'userId', 'reference', 'data'], 'required'];
//
//        return $rules;
//    }
}
