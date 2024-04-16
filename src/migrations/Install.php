<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\stripe\db\Table;
use craft\stripe\elements\Product as ProductElement;
use craft\stripe\elements\Price as PriceElement;
use craft\stripe\elements\Subscription as SubscriptionElement;
use ReflectionClass;
use yii\base\NotSupportedException;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * Creates the tables for Craft Commerce
     */
    public function createTables(): void
    {
        $this->archiveTableIfExists(Table::PRODUCTS);
        $this->createTable(Table::PRODUCTS, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRODUCTDATA);
        $this->createTable(Table::PRODUCTDATA, [
            'id' => $this->primaryKey(),
            'productId' => $this->integer()->notNull(),
            'stripeId' => $this->string()->notNull(),
            'stripeStatus' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::PRICES);
        $this->createTable(Table::PRICES, [
            'id' => $this->primaryKey(),
            'primaryOwnerId' => $this->integer()->defaultValue(NULL),
            'stripeId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::PRICEDATA);
        $this->createTable(Table::PRICEDATA, [
            'id' => $this->primaryKey(),
            'priceId' => $this->integer()->notNull(),
            'stripeId' => $this->string(),
            'stripeStatus' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);


        $this->archiveTableIfExists(Table::SUBSCRIPTIONS);
        $this->createTable(Table::SUBSCRIPTIONS, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists(Table::SUBSCRIPTIONDATA);
        $this->createTable(Table::SUBSCRIPTIONDATA, [
            'id' => $this->primaryKey(),
            'subscriptionId' => $this->integer()->notNull(),
            'stripeId' => $this->string()->notNull(),
            'stripeStatus' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::PAYMENTMETHODDATA);
        $this->createTable(Table::PAYMENTMETHODDATA, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string()->notNull(),
            //'customerDataId' => $this->integer()->notNull(), //$this->string(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::CUSTOMERDATA);
        $this->createTable(Table::CUSTOMERDATA, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string()->notNull(),
            'email' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::INVOICEDATA);
        $this->createTable(Table::INVOICEDATA, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string()->notNull(),
            //'customerDataId' => $this->integer()->notNull(), //$this->string(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);
    }

    /**
     * @return void
     */
    public function createIndexes(): void
    {
        $this->createIndex(null, Table::PRODUCTDATA, ['stripeId'], true);
        $this->createIndex(null, Table::PRICEDATA, ['stripeId'], true);
        $this->createIndex(null, Table::SUBSCRIPTIONDATA, ['stripeId'], true);
        $this->createIndex(null, Table::CUSTOMERDATA, ['stripeId'], true);
        $this->createIndex(null, Table::INVOICEDATA, ['stripeId'], true);
        $this->createIndex(null, Table::PAYMENTMETHODDATA, ['stripeId'], true);
    }

    /**
     * @return void
     */
    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::PRODUCTDATA, ['productId'], Table::PRODUCTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, Table::PRICES, ['primaryOwnerId'], Table::PRODUCTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRICEDATA, ['priceId'],Table::PRICES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRICES, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, Table::SUBSCRIPTIONDATA, ['subscriptionId'],Table::SUBSCRIPTIONS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

//        $this->addForeignKey(null, Table::INVOICEDATA, ['customerId'], Table::CUSTOMERDATA, ['stripeId'], 'SET NULL', 'CASCADE');
//        $this->addForeignKey(null, Table::PAYMENTMETHODDATA, ['customerId'], Table::CUSTOMERDATA, ['stripeId'], 'SET NULL', 'CASCADE');
//        $this->addForeignKey(null, Table::SUBSCRIPTIONDATA, ['customerId'], Table::CUSTOMERDATA, ['stripeId'], 'SET NULL', 'SET NULL');
//        $this->addForeignKey(null, Table::PRICEDATA, ['productId'], Table::PRODUCTDATA, ['stripeId'], 'SET NULL', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [ProductElement::class]]);
        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [PriceElement::class]]);
        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [SubscriptionElement::class]]);

        return true;
    }

    /**
     * Drop the tables
     */
    public function dropTables(): void
    {
        $tables = $this->getAllTableNames();
        foreach ($tables as $table) {
            $this->dropTableIfExists($table);
        }
    }


    /**
     * Removes the foreign keys.
     */
    public function dropForeignKeys(): void
    {
        $tables = $this->getAllTableNames();

        foreach ($tables as $table) {
            $this->dropForeignKeyToAndFromTable($table);
        }
    }

    /**
     * @param $tableName
     * @throws NotSupportedException
     */
    private function dropForeignKeyToAndFromTable($tableName): void
    {
        if ($this->tableExists($tableName)) {
            $this->dropAllForeignKeysToTable($tableName);
            //MigrationHelper::dropAllForeignKeysOnTable($tableName, $this);
        }
    }

    /**
     * Returns if the table exists.
     *
     * @param string $tableName
     * @return bool If the table exists.
     * @throws NotSupportedException
     */
    private function tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }


    /**
     * @return string[]
     */
    private function getAllTableNames(): array
    {
        $class = new ReflectionClass(Table::class);
        return $class->getConstants();
    }
}
