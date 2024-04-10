<?php

namespace craft\stripe\services;

use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\stripe\db\Table;
use craft\stripe\models\Invoice;
use craft\stripe\records\InvoiceData as InvoiceDataRecord;
use craft\stripe\Plugin;
use Stripe\Invoice as StripeInvoice;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Invoices service
 */
class Invoices extends Component
{
    /**
     * Memoized array of invoices.
     *
     * @var Invoice[]|null
     */
    private ?array $_allInvoices = null;

    /**
     * Returns all invoices
     *
     * @return Invoice[]
     */
    public function getAllInvoices(): array
    {
        return $this->_getAllInvoices();
    }

    /**
     * Syncs all invoices from Stripe
     *
     * @return int
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllInvoices(): int
    {
        $api = Plugin::getInstance()->getApi();
        $invoices = $api->fetchAllInvoices();

        foreach ($invoices as $invoice) {
            $this->createOrUpdateInvoice($invoice);
        }

        return count($invoices);
    }

    /**
     * Creates or updates Invoice Data based on what's returned from Stripe
     *
     * @param StripeInvoice $invoice
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateInvoice(StripeInvoice $invoice): bool
    {
        // Build our attribute set from the Stripe payment method data:
        $attributes = [
            'stripeId' => $invoice->id,
            'title' => $invoice->id,
            'data' => Json::decode($invoice->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var InvoiceDataRecord $invoiceDataRecord */
        $invoiceDataRecord = InvoiceDataRecord::find()->where(['stripeId' => $invoice->id])->one() ?: new InvoiceDataRecord();
        $invoiceDataRecord->setAttributes($attributes, false);
        $invoiceDataRecord->save();

        return true;
    }

    /**
     * Get invoices that belong to a User
     *
     * @param User|null $user
     * @return array
     */
    public function getInvoicesByUser(?User $user = null): array
    {
        if ($user === null) {
            return [];
        }

        return $this->getInvoicesByEmail($user->email);
    }

    /**
     * Get invoices by email address
     *
     * @param string|null $email
     * @return array
     * @throws InvalidConfigException
     */
    public function getInvoicesByEmail(?string $email = null): array
    {
        if ($email === null) {
            return [];
        }

        // get customers for a user
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($email);

        // get invoices
        $invoices = [];
        foreach (array_keys($customers) as $customerId) {
            $invoices = array_merge($invoices, ArrayHelper::where($this->_getAllInvoices(), 'data.customer', $customerId));
        }


        if (empty($invoices)) {
            return [];
        }

        return array_filter($invoices);
    }

    /**
     * Gets invoice by its stripeId and returns the Invoice model
     *
     * @param string|null $stripeId
     * @return Invoice|null
     */
    public function getInvoiceById(?string $stripeId): Invoice|null
    {
        if ($stripeId === null) {
            return null;
        }

        $invoice = ArrayHelper::where($this->_getAllInvoices(), 'stripeId', $stripeId);

        if (empty($invoice)) {
            return null;
        }

        return $invoice[0];
    }

    /**
     * Returns a Query object prepped for retrieving invoices.
     *
     * @return Query The query object.
     */
    private function _createInvoiceQuery(): Query
    {
        return (new Query())
            ->select([
                'ssid.stripeId',
                'ssid.data',
            ])
            ->from(['ssid' => Table::INVOICEDATA]);
    }

    /**
     * Populate an array of invoices from their database table rows
     *
     * @return Invoice[]
     */
    private function _populateInvoices(array $results): array
    {
        $invoices = [];

        foreach ($results as $result) {
            try {
                $invoices[] = $this->_populateInvoice($result);
            } catch (InvalidConfigException) {
                continue; // Just skip this
            }
        }

        return $invoices;
    }

    /**
     * Populate a invoice model from database table row.
     *
     * @return Invoice
     */
    private function _populateInvoice(array $result): Invoice
    {
        $invoice = new Invoice();
        $invoice->setAttributes($result, false);

        return $invoice;
    }

    /**
     * Get all invoices memoized.
     *
     * @return array
     */
    private function _getAllInvoices(): array
    {
        if ($this->_allInvoices === null) {
            $invoices = $this->_createInvoiceQuery()->all();

            if (!empty($invoices)) {
                $this->_allInvoices = [];
                $invoices = $this->_populateInvoices($invoices);
                foreach ($invoices as $invoice) {
                    $this->_allInvoices[$invoice->stripeId] = $invoice;
                }
            }
        }

        return $this->_allInvoices ?? [];
    }
}
