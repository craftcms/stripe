<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

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

    public function getCustomerData(): ActiveQueryInterface
    {
        return $this->hasOne(CustomerData::class, ['stripeId' => 'customerId']);
    }
}
