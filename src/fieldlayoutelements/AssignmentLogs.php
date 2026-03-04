<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseUiElement;
use craft\stripe\Plugin;

/**
 * Subscription/group assignment logs field layout element
 *
 * Displays a list of actions the plugin has taken in response to subscriptions starting and ending.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.7.x
 */
class AssignmentLogs extends BaseUiElement
{
    /**
     * @inheritdoc
     */
    protected function selectorLabel(): string
    {
        return Craft::t('stripe', 'Subscription Logs');
    }

    /**
     * @inheritdoc
     */
    protected function selectorIcon(): ?string
    {
        return '@appicons/list-timeline.svg';
    }

    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::$app->getView()->renderTemplate('stripe/fieldlayoutelements/assignmentlogs', [
            'logs' => Plugin::getInstance()->getSubscriptions()->getLogs($element),
            'canManagePrices' => Craft::$app->getUser()->getIdentity()->can('accessPlugin-stripe'),
        ]);
    }
}