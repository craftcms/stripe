<?php

namespace craft\stripe\records\db;

use Craft;
use craft\db\ActiveQuery;
use craft\db\mysql\QueryBuilder as MySqlQueryBuilder;
use craft\db\pgsql\QueryBuilder as PgSqlQueryBuilder;
use Stripe\Invoice;

/**
 * Invoice Data record
 *
 * @property string $stripeId
 * @property string $customerId
 * @property string $test
 * @property string $data
 */
class InvoiceQuery extends ActiveQuery
{
    protected MySqlQueryBuilder|PgSqlQueryBuilder $qb;

    public function init(): void
    {
        $this->qb = Craft::$app->getDb()->getQueryBuilder();
    }

    /**
     * Narrows the query results based on the Stripe Id
     */
    public function stripeId(string $value): InvoiceQuery
    {
        return $this->where(['stripeId' => $value]);
    }

    /**
     * Narrows the query results based on the Stripe Customer Id
     */
    public function customer(string $value): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["customer"]) => $value]);
    }

    /**
     * Narrows the query results based on the Stripe Invoice Number
     */
    public function number(string $value): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["number"]) => $value]);
    }

    /**
     * Narrows the query results to the invoices with status "draft"
     */
    public function draft(): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["status"]) => Invoice::STATUS_DRAFT]);
    }

    /**
     * Narrows the query results to the invoices with status "open"
     */
    public function open(): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["status"]) => Invoice::STATUS_OPEN]);
    }

    /**
     * Narrows the query results to the invoices with status "paid"
     */
    public function paid(): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["status"]) => Invoice::STATUS_PAID]);
    }

    /**
     * Narrows the query results to the invoices with status "uncollectible"
     */
    public function uncollectible(): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["status"]) => Invoice::STATUS_UNCOLLECTIBLE]);
    }

    /**
     * Narrows the query results to the invoices with status "void"
     */
    public function void(): InvoiceQuery
    {
        return $this->where([$this->qb->jsonExtract("[[stripestore_invoicedata.data]]", ["status"]) => Invoice::STATUS_VOID]);
    }
}
