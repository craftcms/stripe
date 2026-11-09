<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Json;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Models\SubscriptionData;
use Illuminate\Console\Command;
use Override;

use function Laravel\Prompts\select;

/**
 * Deletes duplicate subscription elements for the same Stripe subscription.
 *
 * Keeps the most recently updated one, or asks which to keep when their custom field values differ.
 *
 * @since 2.0.0
 */
class RemoveDuplicatesCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'stripe:remove-duplicates {--dry-run : List the duplicates without deleting them.}';

    #[Override]
    protected $description = 'Deletes duplicate subscription elements for the same Stripe subscription.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $groups = Subscription::find()
            ->status(null)
            ->orderBy(['stripe_subscriptions.stripeId' => SORT_ASC, 'elements.dateUpdated' => SORT_DESC])
            ->collect()
            ->filter(fn (Subscription $subscription) => $subscription->stripeId !== null)
            ->groupBy('stripeId')
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->components->info('No duplicate subscriptions found.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($groups as $stripeId => $group) {
            $this->components->twoColumnDetail("<fg=yellow>$stripeId</>", "{$group->count()} elements");

            $keeper = $group->first();
            $differ = $group->contains(fn (Subscription $subscription) => $subscription->getSerializedFieldValues() !== $keeper->getSerializedFieldValues());

            if ($differ) {
                if ($dryRun) {
                    $this->components->warn('The duplicates have different custom field values; you’ll be asked which to keep.');

                    continue;
                }

                $keeperId = select(
                    'The duplicates have different custom field values. Which element should be kept?',
                    $group->mapWithKeys(fn (Subscription $subscription) => [
                        $subscription->id => sprintf('ID %d, updated %s: %s', $subscription->id, $subscription->dateUpdated?->format('Y-m-d H:i:s'), Json::encode($subscription->getSerializedFieldValues())),
                    ])->all(),
                );
                $keeper = $group->firstWhere('id', $keeperId);
            }

            foreach ($group->reject(fn (Subscription $subscription) => $subscription->id === $keeper->id) as $duplicate) {
                $this->components->twoColumnDetail("  Deleting ID $duplicate->id", $dryRun ? 'dry run' : 'deleted');

                if (! $dryRun) {
                    // Keep the Stripe data, which cascades with the element it points to
                    SubscriptionData::where('subscriptionId', $duplicate->id)->update(['subscriptionId' => $keeper->id]);
                    Elements::deleteElement($duplicate, true);
                }

                $deleted++;
            }
        }

        $this->components->info(($dryRun ? 'Would have deleted' : 'Deleted')." $deleted duplicate element(s).");

        return self::SUCCESS;
    }
}
