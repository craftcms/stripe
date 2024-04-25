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
 * Payment Method Data record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property string $stripeId
 * @property string $customerId
 * @property string $data
 */
class PaymentMethodData extends ActiveRecord
{
    public static function tableName()
    {
        return Table::PAYMENTMETHODDATA;
    }

    public function getCustomerData(): ActiveQueryInterface
    {
        return $this->hasOne(CustomerData::class, ['stripeId' => 'customerId']);
    }
}
