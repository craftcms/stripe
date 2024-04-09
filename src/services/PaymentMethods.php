<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\Json;
use craft\stripe\records\PaymentMethodData as PaymentMethodDataRecord;
use craft\stripe\Plugin;
use Stripe\PaymentMethod as StripePaymentMethod;
use yii\base\Component;

/**
 * Payment Methods service
 */
class PaymentMethods extends Component
{
    /**
     * Syncs all payment methods from Stripe
     *
     * @return int
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllPaymentMethods(): int
    {
        $api = Plugin::getInstance()->getApi();
        $paymentMethods = $api->getAllPaymentMethods();

        foreach ($paymentMethods as $paymentMethod) {
            $this->createOrUpdatePaymentMethod($paymentMethod);
        }

        return count($paymentMethods);
    }

    /**
     * Creates or updates Payment Method Data based on what's returned from Stripe
     *
     * @param StripePaymentMethod $paymentMethod
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdatePaymentMethod(StripePaymentMethod $paymentMethod): bool
    {
        // Build our attribute set from the Stripe payment method data:
        $attributes = [
            'stripeId' => $paymentMethod->id,
            'title' => $paymentMethod->id,
            'data' => Json::decode($paymentMethod->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var PaymentMethodDataRecord $paymentMethodDataRecord */
        $paymentMethodDataRecord = PaymentMethodDataRecord::find()->where(['stripeId' => $paymentMethod->id])->one() ?: new PaymentMethodDataRecord();
        $paymentMethodDataRecord->setAttributes($attributes, false);
        $paymentMethodDataRecord->save();

        return true;
    }
}
