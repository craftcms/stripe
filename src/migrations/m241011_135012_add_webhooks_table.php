<?php

namespace craft\stripe\migrations;

use Craft;
use craft\db\Migration;
use craft\stripe\db\Table;
use craft\stripe\Plugin;
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

        // migrate the values from settings
        $plugin = Plugin::getInstance();
        $pluginSettings = $plugin->getSettings();

        $record = new Webhook();
        $record->webhookSigningSecret = $pluginSettings['webhookSigningSecret'];
        $record->webhookId = $pluginSettings['webhookId'];
        $record->save();

        // clear $webhookSigningSecret and $webhookId from the plugin's settings
        $pluginSettings->webhookSigningSecret = '';
        $pluginSettings->webhookId = '';
        Craft::$app->getPlugins()->savePluginSettings($plugin, $pluginSettings->toArray());

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
