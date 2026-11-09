<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Utilities;

use CraftCms\Cms\Utility\Utility;
use CraftCms\Cms\View\TemplateMode;
use Override;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

/**
 * Syncs everything from Stripe.
 *
 * @since 1.0.0
 */
class Sync extends Utility
{
    #[Override]
    public static function id(): string
    {
        return 'stripe-sync-all';
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Sync All', category: 'stripe');
    }

    #[Override]
    public static function icon(): ?string
    {
        return dirname(__DIR__, 2).'/resources/icon-mask.svg';
    }

    #[Override]
    public static function contentHtml(): string
    {
        return template('stripe/_utilities/sync', templateMode: TemplateMode::Cp);
    }
}
