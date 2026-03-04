<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\stripe\db\Table as StripeTable;

/**
 * m250806_201102_add_price_user_groups_table migration.
 */
class m250806_201102_add_price_user_groups_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable(StripeTable::PRICES_USERGROUPS, [
            'id' => $this->primaryKey(),
            'priceId' => $this->integer()->notNull(),
            'groupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->addForeignKey(null, StripeTable::PRICES_USERGROUPS, ['groupId'], CraftTable::USERGROUPS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, StripeTable::PRICES_USERGROUPS, ['priceId'], StripeTable::PRICES, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250806_201102_AddPriceUserGroupAssignmentsTable cannot be reverted.\n";
        return false;
    }
}
