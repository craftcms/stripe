<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fields;

use Craft;
use craft\fields\BaseRelationField;
use craft\stripe\elements\Subscription;

/**
 * Class Stripe Subscriptions Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property-read array $contentGqlType
 */
class Subscriptions extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Subscriptions');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return 'clock-rotate-left';
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('stripe', 'Add a subscription');
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return Subscription::class;
    }
}
