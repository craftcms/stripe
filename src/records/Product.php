<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Product record
 *
 * @property int $id
 * @property string $stripeId
 */
class Product extends ActiveRecord
{
    public static function tableName()
    {
        return Table::PRODUCTS;
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getData(): ActiveQueryInterface
    {
        return $this->hasOne(ProductData::class, ['stripeId' => 'stripeId']);
    }

    public function getPrices(): ActiveQueryInterface
    {
        return $this->hasMany(Price::class, ['primaryOwnerId' => 'id']);
    }
}
