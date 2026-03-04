<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\stripe\db\Table;

/**
 * m250820_185807_add_user_id_column_to_subscriptions migration.
 */
class m250820_185807_add_user_id_column_to_subscriptions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SUBSCRIPTIONS, 'userId', $this->integer());
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['userId'], CraftTable::USERS, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250820_185807_add_user_id_column_to_subscriptions cannot be reverted.\n";
        return false;
    }
}
