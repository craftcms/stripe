<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Price record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
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
        return $this->hasOne(PriceData::class, ['stripeId' => 'stripeId']);
    }

    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id' => 'primaryOwnerId']);
    }
}
