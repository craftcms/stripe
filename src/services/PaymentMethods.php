<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\PaymentMethod;
use craft\stripe\Plugin;
use craft\stripe\records\PaymentMethodData as PaymentMethodDataRecord;
use Stripe\PaymentMethod as StripePaymentMethod;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Payment Methods service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PaymentMethods extends Component
{
    /**
     * Returns all payment methods.
     *
     * @return PaymentMethod[]
     */
    public function getAllPaymentMethods(): array
    {
        $paymentMethods = [];
        $results = $this->createPaymentMethodQuery()->all();

        if (!empty($results)) {
            $results = $this->populatePaymentMethods($results);
            foreach ($results as $result) {
                $paymentMethods[$result->stripeId] = $result;
            }
        }

        return $paymentMethods;
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

        $count = 0;
        foreach ($paymentMethods as $paymentMethod) {
            if ($this->createOrUpdatePaymentMethod($paymentMethod)) {
                $count++;
            }
        }

        return $count;
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
            //'customerId' => $paymentMethod->customer,
            'data' => Json::decode($paymentMethod->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var PaymentMethodDataRecord $paymentMethodDataRecord */
        $paymentMethodDataRecord = PaymentMethodDataRecord::find()->where(['stripeId' => $paymentMethod->id])->one() ?: new PaymentMethodDataRecord();
        $paymentMethodDataRecord->setAttributes($attributes, false);

        return $paymentMethodDataRecord->save();
    }

    /**
     * Returns payment methods that belong to a User
     *
     * @param User|null $user
     * @return array
     */
    public function getPaymentMethodsByUser(?User $user = null): array
    {
        if ($user === null) {
            return [];
        }

        return $this->getPaymentMethodsByEmail($user->email);
    }

    /**
     * Returns payment methods by email address
     *
     * @param string|null $email
     * @return array
     * @throws InvalidConfigException
     */
    public function getPaymentMethodsByEmail(?string $email = null): array
    {
        if ($email === null) {
            return [];
        }

        // get customers for a user
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($email);

        // get payment methods
        $paymentMethods = [];
        foreach (array_keys($customers) as $customerId) {
            $paymentMethods = array_merge($paymentMethods, $this->getPaymentMethodsByCustomerId($customerId));
        }

        array_multisort($paymentMethods, SORT_DESC, array_map(fn($paymentMethod) => $paymentMethod['data']['created'], $paymentMethods));

        return array_filter($paymentMethods);
    }

    /**
     * Returns payment method by its stripeId and returns the PaymentMethod model
     *
     * @param string|null $stripeId
     * @return PaymentMethod|null
     */
    public function getPaymentMethodById(?string $stripeId): PaymentMethod|null
    {
        if ($stripeId === null) {
            return null;
        }

        $paymentMethod = $this->createPaymentMethodQuery()->where(['stripeId' => $stripeId])->one();

        if (!empty($paymentMethod)) {
            $paymentMethod = $this->populatePaymentMethod($paymentMethod);
        }

        return $paymentMethod;
    }

    /**
     * Returns payment methods by customer's Stripe id
     *
     * @param string $customerId
     * @return PaymentMethod[]
     */
    public function getPaymentMethodsByCustomerId(string $customerId): array
    {
        $paymentMethods = $this->createPaymentMethodQuery()
            ->addSelect('customerId')
            ->where(['customerId' => $customerId])
            ->all();

        if (!empty($paymentMethods)) {
            $paymentMethods = $this->populatePaymentMethods($paymentMethods);
        }

        return $paymentMethods;
    }

    /**
     * Returns array of payment methods ready to display in the Vue Admin Table.
     *
     * @param array $paymentMethods
     * @return array
     * @throws InvalidConfigException
     */
    public function getTableData(array $paymentMethods): array
    {
        $tableData = [];
        $formatter = Craft::$app->getFormatter();

        foreach ($paymentMethods as $paymentMethod) {
            $tableData[] = [
                'title' => $paymentMethod->stripeId,
                'type' => $paymentMethod->data['type'],
                'last4' => $this->showLast4($paymentMethod->data),
                'created' => $formatter->asDatetime($paymentMethod->data['created'], $formatter::FORMAT_WIDTH_SHORT),
                'url' => $paymentMethod->getStripeEditUrl(),
            ];
        }

        return $tableData;
    }

    /**
     * Deletes payment method data by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deletePaymentMethodByStripeId(string $stripeId): void
    {
        if ($paymentMethodData = PaymentMethodDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
            $paymentMethodData->delete();
        }
    }

    /**
     * Deletes payment method data by customer's Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deletePaymentMethodsByCustomerId(string $stripeId): void
    {
        if ($paymentMethodData = PaymentMethodDataRecord::find()->where(['customerId' => $stripeId])->one()) {
            $paymentMethodData->delete();
        }
    }

    /**
     * Returns last 4 digits of the payment method if payment method has that property.
     *
     * @param array $data
     * @return string|null
     */
    private function showLast4(array $data): ?string
    {
        $last4 = null;

        if (isset($data[$data['type']]) && isset($data[$data['type']]['last4'])) {
            $last4 = $data[$data['type']]['last4'];
        }

        return $last4;
    }

    /**
     * Returns a Query object prepped for retrieving payment methods.
     *
     * @return Query The query object.
     */
    private function createPaymentMethodQuery(): Query
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
    private function populatePaymentMethods(array $results): array
    {
        $paymentMethods = [];

        foreach ($results as $result) {
            try {
                $paymentMethods[] = $this->populatePaymentMethod($result);
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
    private function populatePaymentMethod(array $result): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setAttributes($result, false);

        return $paymentMethod;
    }
}
