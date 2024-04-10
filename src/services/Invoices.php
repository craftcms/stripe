<?php

namespace craft\stripe\services;

use craft\helpers\Json;
use craft\stripe\models\Invoice;
use craft\stripe\records\InvoiceData as InvoiceDataRecord;
use craft\stripe\Plugin;
use Stripe\Invoice as StripeInvoice;
use yii\base\Component;

/**
 * Invoices service
 */
class Invoices extends Component
{
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

        $result = InvoiceDataRecord::find()->where(['stripeId' => $stripeId])->one();
        if ($result === null) {
            return null;
        }

        $invoice = new Invoice();
        $invoice->setAttributes($result->getAttributes(), false);

        return $invoice;
    }
}
