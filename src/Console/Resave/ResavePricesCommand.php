<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Stripe\Elements\Price;
use Override;

/**
 * @since 2.0.0
 */
class ResavePricesCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:stripe-prices'.self::DEFAULT_OPTIONS;

    #[Override]
    protected $description = 'Re-saves Stripe prices.';

    #[Override]
    protected $aliases = ['resave/stripe-prices'];

    public function handle(): int
    {
        if (! $this->validateResaveOptions()) {
            return self::FAILURE;
        }

        if ($this->resolvedWithFields !== [] && ! $this->hasTheFields(Fields::getLayoutByType(Price::class))) {
            $this->components->warn('The Stripe prices field layout doesn’t satisfy `--with-fields`.');

            return self::FAILURE;
        }

        return $this->resaveElements(Price::class);
    }
}
