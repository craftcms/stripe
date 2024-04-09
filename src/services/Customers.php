<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\Json;
use craft\stripe\records\CustomerData as CustomerDataRecord;
use craft\stripe\Plugin;
use Stripe\Customer as StripeCustomer;
use yii\base\Component;

/**
 * Customers service
 */
class Customers extends Component
{
    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllCustomers(): void
    {
        $api = Plugin::getInstance()->getApi();
        $customers = $api->getAllCustomers();

        foreach ($customers as $customer) {
            $this->createOrUpdateCustomer($customer);
        }
    }

    /**
     * This takes the stripe customer data from the API.
     *
     * @param StripeCustomer $customer
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateCustomer(StripeCustomer $customer): bool
    {
        // Build our attribute set from the Stripe payment method data:
        $attributes = [
            'stripeId' => $customer->id,
            'title' => $customer->id,
            'data' => Json::decode($customer->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var CustomerDataRecord $customerDataRecord */
        $customerDataRecord = CustomerDataRecord::find()->where(['stripeId' => $customer->id])->one() ?: new CustomerDataRecord();
        $customerDataRecord->setAttributes($attributes, false);
        $customerDataRecord->save();

        return true;
    }
}
