<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Price record
 *
 * @property int $id
 * @property string $stripeId
 * @property int|null $primaryOwnerId Owner ID
 */
class Price extends ActiveRecord
{
    public static function tableName()
    {
        return Table::PRICES;
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getData(): ActiveQueryInterface
    {
        return $this->hasOne(PriceData::class, ['stripeId' => 'id']);
    }
}
