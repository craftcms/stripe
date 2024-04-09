<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;

/**
 * Customer Data record
 *
 * @property string $stripeId
 * @property string $data
 */
class CustomerData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::CUSTOMERDATA;
    }
}
