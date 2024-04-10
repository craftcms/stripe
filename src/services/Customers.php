<?php

namespace craft\stripe\services;

use craft\db\Query;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\Customer;
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
     * Syncs all customers from Stripe
     *
     * @return int
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllCustomers(): int
    {
        $api = Plugin::getInstance()->getApi();
        $customers = $api->getAllCustomers();

        foreach ($customers as $customer) {
            $this->createOrUpdateCustomer($customer);
        }

        return count($customers);
    }

    /**
     * Creates or updates Customer Data based on what's returned from Stripe
     *
     * @param StripeCustomer $customer
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateCustomer(StripeCustomer $customer): bool
    {
        // Build our attribute set from the Stripe payment method data:
        $attributes = [
            'stripeId' => $customer->id,
            'email' => $customer->email,
            'data' => Json::decode($customer->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var CustomerDataRecord $customerDataRecord */
        $customerDataRecord = CustomerDataRecord::find()->where(['stripeId' => $customer->id])->one() ?: new CustomerDataRecord();
        $customerDataRecord->setAttributes($attributes, false);
        $customerDataRecord->save();

        return true;
    }

    /**
     * Returns a Customer by email address
     *
     * @param string|null $email
     * @return array|null
     */
    public function getCustomersByEmail(?string $email = null): ?array
    {
        $customers = [];

        if ($email === null) {
            return null;
        }
        $records = $this->createCustomerQuery()->where(['email' => $email])->all();

        foreach ($records as $record) {
            $customer = new Customer();
            $customer->setAttributes($record, false);

            $customers[] = $customer;
        }

        return $customers;
    }

    /**
     * Returns a Query object prepped for retrieving customers.
     *
     * @return Query The query object.
     */
    private function createCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'stripeId',
                'email',
                'data',
            ])
            ->from([Table::CUSTOMERDATA]);
    }
}
