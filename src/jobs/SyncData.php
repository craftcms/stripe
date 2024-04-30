<?php

namespace craft\stripe\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\stripe\Plugin;

/**
 * Sync Data queue job
 */
class SyncData extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $plugin = Plugin::getInstance();

        $plugin->getCustomers()->syncAllCustomers();
        $this->setProgress($queue, 0.25);

        $plugin->getSubscriptions()->syncAllSubscriptions();
        $this->setProgress($queue, 0.5);

        $plugin->getInvoices()->syncAllInvoices();
        $this->setProgress($queue, 0.75);

        $plugin->getPaymentMethods()->syncAllPaymentMethods();
        $this->setProgress($queue, 1);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('stripe', 'Sync customer-related Stripe data.');
    }
}
