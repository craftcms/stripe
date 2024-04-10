<?php

namespace craft\stripe\services;

use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\Customer;
use craft\stripe\records\CustomerData as CustomerDataRecord;
use craft\stripe\Plugin;
use Stripe\Customer as StripeCustomer;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Customers service
 */
class Customers extends Component
{
    /**
     * Memoized array of customers.
     *
     * @var Customer[]|null
     */
    private ?array $_allCustomers = null;

    /**
     * Returns all customers
     *
     * @return Customer[]
     */
    public function getAllCustomers(): array
    {
        return $this->_getAllCustomers();
    }

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
        $customers = $api->fetchAllCustomers();

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
     * @return array
     */
    public function getCustomersByEmail(?string $email = null): array
    {
        return ArrayHelper::whereMultiple($this->_getAllCustomers(), ['email' => $email]);
    }

    /**
     * Returns a Query object prepped for retrieving customers.
     *
     * @return Query The query object.
     */
    private function _createCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'sscd.stripeId',
                'sscd.email',
                'sscd.data',
            ])
            ->from(['sscd' => Table::CUSTOMERDATA]);
    }

    /**
     * Populate an array of customers from their database table rows
     *
     * @return Customer[]
     */
    private function _populateCustomers(array $results): array
    {
        $customers = [];

        foreach ($results as $result) {
            try {
                $customers[] = $this->_populateCustomer($result);
            } catch (InvalidConfigException) {
                continue; // Just skip this
            }
        }

        return $customers;
    }

    /**
     * Populate a customer model from database table row.
     *
     * @return Customer
     */
    private function _populateCustomer(array $result): Customer
    {
        $invoice = new Customer();
        $invoice->setAttributes($result, false);

        return $invoice;
    }

    /**
     * Get all customers memoized.
     *
     * @return array
     */
    private function _getAllCustomers(): array
    {
        if ($this->_allCustomers === null) {
            $customers = $this->_createCustomerQuery()->all();

            if (!empty($customers)) {
                $this->_allCustomers = [];
                $customers = $this->_populateCustomers($customers);
                foreach ($customers as $customer) {
                    $this->_allCustomers[$customer->stripeId] = $customer;
                }
            }
        }

        return $this->_allCustomers ?? [];
    }
}
