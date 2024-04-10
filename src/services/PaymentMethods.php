<?php

namespace craft\stripe\services;

use craft\db\Query;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\PaymentMethod;
use craft\stripe\records\PaymentMethodData as PaymentMethodDataRecord;
use craft\stripe\Plugin;
use Stripe\PaymentMethod as StripePaymentMethod;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Payment Methods service
 */
class PaymentMethods extends Component
{
    /**
     * Memoized array of payment methods.
     *
     * @var PaymentMethod[]|null
     */
    private ?array $_allPaymentMethods = null;

    /**
     * Returns all payment methods.
     *
     * @return PaymentMethod[]
     */
    public function getAllPaymentMethods(): array
    {
        return $this->_getAllPaymentMethods();
    }

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
        $paymentMethods = $api->fetchAllPaymentMethods();

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

    /**
     * Returns a Query object prepped for retrieving payment methods.
     *
     * @return Query The query object.
     */
    private function _createPaymentMethodQuery(): Query
    {
        return (new Query())
            ->select([
                'sspmd.stripeId',
                'sspmd.data',
            ])
            ->from(['sspmd' => Table::PAYMENTMETHODDATA]);
    }

    /**
     * Populate an array of payment methods from their database table rows
     *
     * @return PaymentMethod[]
     */
    private function _populatePaymentMethods(array $results): array
    {
        $paymentMethods = [];

        foreach ($results as $result) {
            try {
                $paymentMethods[] = $this->_populatePaymentMethod($result);
            } catch (InvalidConfigException) {
                continue; // Just skip this
            }
        }

        return $paymentMethods;
    }

    /**
     * Populate a payment methods model from database table row.
     *
     * @return PaymentMethod
     */
    private function _populatePaymentMethod(array $result): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setAttributes($result, false);

        return $paymentMethod;
    }

    /**
     * Get all payment methods memoized.
     *
     * @return array
     */
    private function _getAllPaymentMethods(): array
    {
        if ($this->_allPaymentMethods === null) {
            $paymentMethods = $this->_createPaymentMethodQuery()->all();

            if (!empty($paymentMethods)) {
                $this->_allPaymentMethods = [];
                $paymentMethods = $this->_populatePaymentMethods($paymentMethods);
                foreach ($paymentMethods as $paymentMethod) {
                    $this->_allPaymentMethods[$paymentMethod->stripeId] = $paymentMethod;
                }
            }
        }

        return $this->_allPaymentMethods ?? [];
    }
}
