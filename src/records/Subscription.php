<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Subscription record
 *
 * @property int $id
 * @property string $stripeId
 */
class Subscription extends ActiveRecord
{
    public static function tableName()
    {
        return Table::SUBSCRIPTIONS;
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getData(): ActiveQueryInterface
    {
        return $this->hasOne(SubscriptionData::class, ['stripeId' => 'id']);
    }
}
