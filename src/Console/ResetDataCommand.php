<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\Invoice;
use CraftCms\Stripe\Models\PaymentMethod;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Deletes the synced Stripe data from Craft. Stripe itself is unaffected.
 *
 * @since 2.0.0
 */
class ResetDataCommand extends Command
{
    use ConfirmableTrait;
    use CraftCommand;

    #[Override]
    protected $signature = 'stripe:reset-data {--force : Reset without asking for confirmation.}';

    #[Override]
    protected $description = 'Deletes all synced Stripe products, prices, subscriptions, customers, invoices and payment methods from Craft.';

    public function handle(): int
    {
        if (! $this->confirmToProceed('This permanently deletes all synced Stripe data from Craft. (Data in Stripe will not be affected.)', fn () => true)) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            // Deleting a product deletes its prices too
            $this->components->task('Removing products and prices', function () {
                foreach (Product::find()->status(null)->lazy() as $product) {
                    Elements::deleteElement($product, true);
                }
            });

            $this->components->task('Removing subscriptions', function () {
                foreach (Subscription::find()->status(null)->lazy() as $subscription) {
                    Elements::deleteElement($subscription, true);
                }
            });

            $this->components->task('Removing customers', fn () => Customer::query()->delete());
            $this->components->task('Removing invoices', fn () => Invoice::query()->delete());
            $this->components->task('Removing payment methods', fn () => PaymentMethod::query()->delete());
        });

        return self::SUCCESS;
    }
}
