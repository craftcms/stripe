<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\stripe\db\Table;

/**
 * m250821_232955_add_subscription_logs migration.
 */
class m250821_232955_add_subscription_logs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable(Table::SUBSCRIPTIONLOGS, [
            'id' => $this->primaryKey(),
            'subscriptionId' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->addForeignKey(null, Table::SUBSCRIPTIONLOGS, ['subscriptionId'], Table::SUBSCRIPTIONS, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250821_232955_add_subscription_logs cannot be reverted.\n";
        return false;
    }
}
