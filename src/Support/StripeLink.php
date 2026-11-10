<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Support;

use CraftCms\Cms\Cp\Icons;
use CraftCms\Cms\Support\Html;

use function CraftCms\Cms\t;

/**
 * A link that opens the Stripe Dashboard in a new window.
 *
 * @since 2.0.0
 */
class StripeLink
{
    /**
     * Returns the link, with an icon after the label. Without a label, the icon is the link, labelled for screen readers.
     */
    public static function html(string $url, ?string $label = null): string
    {
        return Html::beginTag('a', ['href' => $url, 'target' => '_blank', 'rel' => 'noopener', 'class' => 'inline-flex items-center gap-1']).
            ($label !== null && $label !== '' ? Html::tag('span', e($label)) : Html::tag('span', t('Open in Stripe', category: 'stripe'), ['class' => 'sr-only'])).
            Html::tag('span', Icons::svg('arrow-up-right-from-square'), ['class' => ['cp-icon', 'small', 'inline-flex'], 'aria-hidden' => 'true']).
            Html::tag('span', t('Opens in a new window'), ['class' => 'sr-only']).
            Html::endTag('a');
    }
}
