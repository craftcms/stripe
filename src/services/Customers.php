<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use craft\stripe\records\CustomerData as CustomerDataRecord;
use Stripe\Customer as StripeCustomer;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Customers service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Customers extends Component
{
    /**
     * Returns all customers
     *
     * @return Customer[]
     */
    public function getAllCustomers(): array
    {
        $customers = [];
        $results = $this->_createCustomerQuery()->all();

        if (!empty($results)) {
            $results = $this->_populateCustomers($results);
            foreach ($results as $result) {
                $customers[$result->stripeId] = $result;
            }
        }

        return $customers;
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

        $count = 0;
        foreach ($customers as $customer) {
            if ($this->createOrUpdateCustomer($customer)) {
                $count++;
            }
        }

        return $count;
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
            'stripeCreated' => Db::prepareDateForDb($customer->created),
            'data' => Json::decode($customer->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var CustomerDataRecord $customerDataRecord */
        $customerDataRecord = CustomerDataRecord::find()->where(['stripeId' => $customer->id])->one() ?: new CustomerDataRecord();
        $customerDataRecord->setAttributes($attributes, false);

        return $customerDataRecord->save();
    }

    /**
     * Returns Customer(s) by email address
     *
     * @param string|null $email
     * @return Customer[]
     */
    public function getCustomersByEmail(?string $email = null): array
    {
        $customers = [];
        $results = $this->_createCustomerQuery()->where(['sscd.email' => $email])->all();

        if (!empty($results)) {
            $results = $this->_populateCustomers($results);
            foreach ($results as $result) {
                $customers[$result->stripeId] = $result;
            }
        }

        return $customers;
    }

    /**
     * Returns first Stripe customer by email address
     *
     * @param string|null $email
     * @return ?Customer
     */
    public function getFirstCustomerByEmail(?string $email = null): ?Customer
    {
        $customers = $this->getCustomersByEmail($email);

        return reset($customers) ?: null;
    }

    /**
     * Returns a Customer by their Stripe id
     *
     * @param string $stripeId
     * @return Customer|null
     */
    public function getCustomerByStripeId(string $stripeId): Customer|null
    {
        $customer = $this->_createCustomerQuery()->where(['sscd.stripeId' => $stripeId])->one();

        if (!empty($customer)) {
            $customer = $this->_populateCustomer($customer);
        }

        return $customer;
    }

    /**
     * Deletes customer data by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteCustomerDataByStripeId(string $stripeId): void
    {
        if ($customerData = CustomerDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
            $customerData->delete();
        }
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
                'sscd.stripeCreated',
                'sscd.data',
            ])
            ->orderBy(['sscd.stripeCreated' => SORT_DESC])
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
        $customer = new Customer();
        $customer->setAttributes($result, false);

        return $customer;
    }
}
