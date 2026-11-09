<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Stripe\Elements\Subscription;
use Override;

/**
 * @since 3.0.0
 */
class ResaveSubscriptionsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:stripe-subscriptions'.self::DEFAULT_OPTIONS;

    #[Override]
    protected $description = 'Re-saves Stripe subscriptions.';

    #[Override]
    protected $aliases = ['resave/stripe-subscriptions'];

    public function handle(): int
    {
        if (! $this->validateResaveOptions()) {
            return self::FAILURE;
        }

        if ($this->resolvedWithFields !== [] && ! $this->hasTheFields(Fields::getLayoutByType(Subscription::class))) {
            $this->components->warn('The Stripe subscriptions field layout doesn’t satisfy `--with-fields`.');

            return self::FAILURE;
        }

        return $this->resaveElements(Subscription::class);
    }
}
