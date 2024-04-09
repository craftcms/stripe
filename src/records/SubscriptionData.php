<?php

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Subscription Data record
 *
 * @property string $stripeId
 * @property string $data
 */
class SubscriptionData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::SUBSCRIPTIONDATA;
    }

    public function getSubscription(): ActiveQueryInterface
    {
        return $this->hasOne(Subscription::class, ['id' => 'stripeId']);
    }
}
