<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\utilities;

use Craft;
use craft\base\Utility;

/**
 * Sync utility
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Sync extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Sync All');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'stripe-sync-all';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();

        return $view->renderTemplate('stripe/utilities/_sync.twig');
    }
}
