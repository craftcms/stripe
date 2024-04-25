<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;

/**
 * Invoice Data record
 *
 * @property string $stripeId
 * @property string $customerId
 * @property string $data
 */
class InvoiceData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::INVOICEDATA;
    }

//    public function getCustomerData(): ActiveQueryInterface
//    {
//        return $this->hasOne(CustomerData::class, ['stripeId' => 'customerId']);
//    }
}
