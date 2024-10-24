<?php

namespace craft\stripe\migrations;

use Craft;
use craft\db\Migration;
use craft\stripe\db\Table;
use craft\stripe\records\Webhook;

/**
 * m241011_135012_add_webhooks_table migration.
 */
class m241011_135012_add_webhooks_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // create the table
        $this->createTable(Table::WEBHOOKS, [
            'id' => $this->primaryKey(),
            'webhookSigningSecret' => $this->string()->notNull(),
            'webhookId' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->string(),
        ]);

        // migrate the raw values from settings
        $rawPluginSettings = Craft::$app->getProjectConfig()->get('plugins.stripe.settings');

        if (!empty($rawPluginSettings)) {
            $record = new Webhook();
            $record->webhookSigningSecret = $rawPluginSettings['webhookSigningSecret'];
            $record->webhookId = $rawPluginSettings['webhookId'];
            $record->save();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241011_135012_add_webhooks_table cannot be reverted.\n";
        return false;
    }
}
