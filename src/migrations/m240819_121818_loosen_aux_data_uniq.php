<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use craft\stripe\db\Table;

/**
 * m240819_121818_loosen_aux_data_uniq migration.
 */
class m240819_121818_loosen_aux_data_uniq extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $tableName = Table::SUBSCRIPTIONDATA;

        // Step 1: Drop the existing index
        Db::dropIndexIfExists($tableName, 'latestInvoiceId', true);

        // Step 2: Drop the existing virtual column
        $this->execute("ALTER TABLE " . $tableName . " DROP COLUMN [[latestInvoiceId]]");

        // Step 3: Recreate the virtual column as nullable: https://docs.stripe.com/api/subscriptions/object#subscription_object-latest_invoice
        $this->execute("ALTER TABLE " . $tableName . " ADD COLUMN " .
            $this->db->quoteColumnName('[[latestInvoiceId]]') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $this->db->getQueryBuilder()->jsonExtract('data', ['latest_invoice']) . ") STORED NULL");

        // Step 4: Create a new non-unique index
        $this->createIndex(null, $tableName, ['latestInvoiceId'], unique: false);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240819_121818_loosen_aux_data_uniq cannot be reverted.\n";
        return false;
    }
}
