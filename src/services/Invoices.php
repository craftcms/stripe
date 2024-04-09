<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\Json;
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
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllInvoices(): void
    {
        $api = Plugin::getInstance()->getApi();
        $invoices = $api->getAllInvoices();

        foreach ($invoices as $invoice) {
            $this->createOrUpdateInvoice($invoice);
        }
    }

    /**
     * This takes the stripe invoice data from the API.
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
}
