<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Price Data record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property string $stripeId
 * @property string $productId
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
        return $this->hasOne(Price::class, ['stripeId' => 'stripeId']);
    }

    public function getProductData(): ActiveQueryInterface
    {
        return $this->hasOne(ProductData::class, ['stripeId' => 'productId']);
    }
}
