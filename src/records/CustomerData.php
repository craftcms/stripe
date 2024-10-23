<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\records;

use craft\db\ActiveRecord;
use craft\stripe\db\Table;

/**
 * Customer Data record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
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

    public function rules()
    {
        return [
            [['email'], 'required'],
        ];
    }
}
