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
 * Webhook record
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property int $id
 * @property string $webhookSigningSecret
 * @property string $webhookId
 * @since 1.2
 */
class Webhook extends ActiveRecord
{
    public static function tableName()
    {
        return Table::WEBHOOKS;
    }
}
