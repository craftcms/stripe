<?php

namespace craft\stripe\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\MigrationHelper;
use craft\stripe\db\Table;
use craft\stripe\elements\Product as ProductElement;
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
            'id' => $this->integer()->notNull(),
            'stripeId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->archiveTableIfExists(Table::PRODUCTDATA);
        $this->createTable(Table::PRODUCTDATA, [
            'stripeId' => $this->string(),
            'stripeStatus' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
            'PRIMARY KEY([[stripeId]])',
        ]);

        $this->archiveTableIfExists(Table::PRICES);
        $this->createTable(Table::PRICES, [
            'id' => $this->integer()->notNull(),
            'primaryOwnerId' => $this->integer()->defaultValue(NULL),
            'stripeId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->archiveTableIfExists(Table::PRICEDATA);
        $this->createTable(Table::PRICEDATA, [
            'stripeId' => $this->string(),
            'stripeStatus' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
            'PRIMARY KEY([[stripeId]])',
        ]);
    }

    /**
     * @return void
     */
    public function createIndexes(): void
    {
        $this->createIndex(null, Table::PRODUCTDATA, ['stripeId'], true);
        $this->createIndex(null, Table::PRICEDATA, ['stripeId'], true);
    }

    /**
     * @return void
     */
    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::PRODUCTS, ['stripeId'], Table::PRODUCTDATA, ['stripeId'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRODUCTS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, Table::PRICES, ['primaryOwnerId'], Table::PRODUCTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRICES, ['stripeId'], Table::PRICEDATA, ['stripeId'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::PRICES, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [ProductElement::class]]);

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
