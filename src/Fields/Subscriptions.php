<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Fields;

use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Stripe\Elements\Queries\SubscriptionQuery;
use CraftCms\Stripe\Elements\Subscription;
use Override;

use function CraftCms\Cms\t;

/**
 * Relates Stripe subscriptions.
 *
 * @since 1.0.0
 */
class Subscriptions extends BaseRelationField
{
    #[Override]
    public static function displayName(): string
    {
        return t('Stripe Subscriptions', category: 'stripe');
    }

    #[Override]
    public static function icon(): string
    {
        return 'arrows-rotate';
    }

    #[Override]
    public static function defaultSelectionLabel(): string
    {
        return t('Add a subscription', category: 'stripe');
    }

    public static function elementType(): string
    {
        return Subscription::class;
    }

    #[Override]
    public static function phpType(): string
    {
        return sprintf('\\%s|\\%s<\\%s>', SubscriptionQuery::class, ElementCollection::class, Subscription::class);
    }
}
