<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Console\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Stripe\Elements\Product;
use Override;

/**
 * @since 2.0.0
 */
class ResaveProductsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:stripe-products'.self::DEFAULT_OPTIONS;

    #[Override]
    protected $description = 'Re-saves Stripe products.';

    #[Override]
    protected $aliases = ['resave/stripe-products'];

    public function handle(): int
    {
        if (! $this->validateResaveOptions()) {
            return self::FAILURE;
        }

        if ($this->resolvedWithFields !== [] && ! $this->hasTheFields(Fields::getLayoutByType(Product::class))) {
            $this->components->warn('The Stripe products field layout doesn’t satisfy `--with-fields`.');

            return self::FAILURE;
        }

        return $this->resaveElements(Product::class);
    }
}
