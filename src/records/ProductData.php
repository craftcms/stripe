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
 * Product Data record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property string $stripeId
 * @property string $data
 */
class ProductData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::PRODUCTDATA;
    }

    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['stripeId' => 'stripeId']);
    }
}
