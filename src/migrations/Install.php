<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\stripe\db\Table;
use craft\stripe\elements\Price as PriceElement;
use craft\stripe\elements\Product as ProductElement;
use craft\stripe\elements\Subscription as SubscriptionElement;
use ReflectionClass;
use yii\base\NotSupportedException;

/**
 * Install migration.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
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
            'primaryOwnerId' => $this->integer()->defaultValue(null),
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
            'currencies' => $this->text()->defaultValue(null),
            'unitAmount' => $this->float()->defaultValue(null),
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
            'prices' => $this->text()->defaultValue(null),
        ]);

        $this->archiveTableIfExists(Table::PAYMENTMETHODDATA);
        $this->createTable(Table::PAYMENTMETHODDATA, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string()->notNull(),
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
            'stripeCreated' => $this->dateTime()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::INVOICEDATA);
        $this->createTable(Table::INVOICEDATA, [
            'id' => $this->primaryKey(),
            'stripeId' => $this->string()->notNull(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        $this->archiveTableIfExists(Table::WEBHOOKS);
        $this->createTable(Table::WEBHOOKS, [
            'id' => $this->primaryKey(),
            'webhookSigningSecret' => $this->string()->notNull(),
            'webhookId' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        // create generated columns
        $this->createGeneratedColumns();
    }

    /**
     * @return void
     */
    private function createGeneratedColumns(): void
    {
        $db = Craft::$app->getDb();
        $qb = $db->getQueryBuilder();

        // price data
        // type
        $this->execute("ALTER TABLE " . Table::PRICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('type') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['type']) . ") STORED;");
        // productId
        $this->execute("ALTER TABLE " . Table::PRICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('productId') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['product']) . ") STORED;");
        // primary currency
        $this->execute("ALTER TABLE " . Table::PRICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('primaryCurrency') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['currency']) . ") STORED;");

        // subscription data
        // canceledAt
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('canceledAt') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['canceled_at']) . ") STORED;");
        // currentPeriodEnd
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('currentPeriodEnd') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['current_period_end']) . ") STORED;");
        // customerId
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('customerId') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['customer']) . ") STORED;");
        // latestInvoiceId
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('latestInvoiceId') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['latest_invoice']) . ") STORED NULL;");
        // startDate
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('startDate') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['start_date']) . ") STORED;");
        // trialStart
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('trialStart') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['trial_start']) . ") STORED;");
        // trialEnd
        $this->execute("ALTER TABLE " . Table::SUBSCRIPTIONDATA . " ADD COLUMN " .
            $db->quoteColumnName('trialEnd') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['trial_end']) . ") STORED;");

        // invoice data
        // created
        $this->execute("ALTER TABLE " . Table::INVOICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('created') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['created']) . ") STORED;");
        // customerEmail
        $this->execute("ALTER TABLE " . Table::INVOICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('customerEmail') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['customer_email']) . ") STORED;");
        // number
        $this->execute("ALTER TABLE " . Table::INVOICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('number') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['number']) . ") STORED;");
        // subscriptionId
        $this->execute("ALTER TABLE " . Table::INVOICEDATA . " ADD COLUMN " .
            $db->quoteColumnName('subscriptionId') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['subscription']) . ") STORED;");

        // payment method data => customerId
        $this->execute("ALTER TABLE " . Table::PAYMENTMETHODDATA . " ADD COLUMN " .
            $db->quoteColumnName('customerId') . " VARCHAR(255) GENERATED ALWAYS AS (" .
            $qb->jsonExtract('data', ['customer']) . ") STORED;");
    }

    /**
     * @return void
     */
    public function createIndexes(): void
    {
        $this->createIndex(null, Table::PRODUCTDATA, ['stripeId'], true);

        $this->createIndex(null, Table::PRICEDATA, ['stripeId'], true);

        $this->createIndex(null, Table::SUBSCRIPTIONDATA, ['stripeId'], true);
        $this->createIndex(null, Table::SUBSCRIPTIONDATA, ['customerId']);
        $this->createIndex(null, Table::SUBSCRIPTIONDATA, ['latestInvoiceId']);

        $this->createIndex(null, Table::CUSTOMERDATA, ['stripeId'], true);
        $this->createIndex(null, Table::CUSTOMERDATA, ['email']);

        $this->createIndex(null, Table::INVOICEDATA, ['stripeId'], true);
        $this->createIndex(null, Table::INVOICEDATA, ['customerEmail']);
        $this->createIndex(null, Table::INVOICEDATA, ['number']);
        $this->createIndex(null, Table::INVOICEDATA, ['subscriptionId']);

        $this->createIndex(null, Table::PAYMENTMETHODDATA, ['stripeId'], true);
        $this->createIndex(null, Table::PAYMENTMETHODDATA, ['customerId']);
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
