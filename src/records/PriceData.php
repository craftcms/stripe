<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Price Data record
 *
 * @property string $stripeId
 * @property string $data
 */
class PriceData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::PRICEDATA;
    }

    public function getPrice(): ActiveQueryInterface
    {
        return $this->hasOne(Price::class, ['id' => 'stripeId']);
    }
}
