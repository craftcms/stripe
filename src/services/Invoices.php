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
use craft\stripe\models\Invoice;
use craft\stripe\Plugin;
use craft\stripe\records\InvoiceData as InvoiceDataRecord;
use Stripe\Invoice as StripeInvoice;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Invoices service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Invoices extends Component
{
    /**
     * Returns all invoices
     *
     * @return Invoice[]
     */
    public function getAllInvoices(): array
    {
        $invoices = [];
        $results = $this->_createInvoiceQuery()->all();

        if (!empty($results)) {
            $results = $this->populateInvoices($results);
            foreach ($results as $result) {
                $invoices[$result->stripeId] = $result;
            }
        }

        return $invoices;
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

        $count = 0;
        foreach ($invoices as $invoice) {
            if ($this->createOrUpdateInvoice($invoice)) {
                $count++;
            }
        }

        return $count;
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
            //'customerId' => $invoice->customer,
            'data' => Json::decode($invoice->toJSON()),
        ];

        // Find the payment method data or create one
        /** @var InvoiceDataRecord $invoiceDataRecord */
        $invoiceDataRecord = InvoiceDataRecord::find()->where(['stripeId' => $invoice->id])->one() ?: new InvoiceDataRecord();
        $invoiceDataRecord->setAttributes($attributes, false);

        return $invoiceDataRecord->save();
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
            $invoices = array_merge($invoices, $this->getInvoicesByCustomerId($customerId));
        }

        array_multisort($invoices, SORT_DESC, array_map(fn($invoice) => $invoice['data']['created'], $invoices));

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

        $invoice = $this->_createInvoiceQuery()->where(['stripeId' => $stripeId])->one();

        if (!empty($invoice)) {
            $invoice = $this->populateInvoice($invoice);
        }

        return $invoice;
    }

    /**
     * Gets invoices by customer's Stripe id
     *
     * @param string $customerId
     * @return Invoice[]
     */
    public function getInvoicesByCustomerId(string $customerId): array
    {
        $qb = Craft::$app->getDb()->getQueryBuilder();
        $invoices = $this->_createInvoiceQuery()
            ->addSelect(['customerId' => $qb->jsonExtract('ssid.data', ['customer'])])
            ->where([$qb->jsonExtract('ssid.data', ['customer']) => $customerId])
            ->all();

        if (!empty($invoices)) {
            $invoices = $this->populateInvoices($invoices);
        }

        return $invoices;
    }

    /**
     * Returns array of invoices ready to display in the Vue Admin Table.
     *
     * @param array $invoices
     * @return array
     * @throws InvalidConfigException
     */
    public function getTableData(array $invoices): array
    {
        $tableData = [];
        $formatter = Craft::$app->getFormatter();

        foreach ($invoices as $invoice) {
            $tableData[] = [
                'id' => $invoice->stripeId,
                'title' => $invoice->data['number'] ?? Craft::t('stripe', 'Draft'),
                'amount' => $formatter->asCurrency($invoice->data['total'] / 100, $invoice->data['currency']),
                'stripeStatus' => $invoice->data['status'],
                'frequency' => '',
                'customerEmail' => $invoice->data['customer_email'],
                'due' => $invoice->data['due_date'] ? $formatter->asDatetime($invoice->data['due_date'], 'php:Y-m-d') : '',
                'created' => $formatter->asDatetime($invoice->data['created'], $formatter::FORMAT_WIDTH_SHORT),
                'url' => $invoice->getStripeEditUrl(),
            ];
        }

        return $tableData;
    }

    /**
     * Deletes invoice data by Stripe id.
     *
     * @param string $stripeId
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteInvoiceByStripeId(string $stripeId): void
    {
        if ($invoiceData = InvoiceDataRecord::find()->where(['stripeId' => $stripeId])->one()) {
            $invoiceData->delete();
        }
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
    public function populateInvoices(array $results): array
    {
        $invoices = [];

        foreach ($results as $result) {
            try {
                $invoices[] = $this->populateInvoice($result);
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
    public function populateInvoice(array $result): Invoice
    {
        $invoice = new Invoice();
        $invoice->setAttributes($result, false);

        return $invoice;
    }
}
