<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\enums\CmsEdition;
use craft\fieldlayoutelements\BaseNativeField;
use craft\stripe\elements\Price;
use yii\base\InvalidArgumentException;

/**
 * A field layout element that provides a UI for selecting user groups.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.7.x
 */
class UserGroupAssignmentsField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'userGroupAssignments';

    /**
     * @inheritdoc
     */
    public bool $required = false;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        unset(
            $config['mandatory'],
            $config['translatable'],
            $config['maxlength'],
            $config['required'],
            $config['autofocus']
        );

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset(
            $fields['mandatory'],
            $fields['translatable'],
            $fields['maxlength'],
            $fields['required'],
            $fields['autofocus']
        );
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('stripe', 'User Group Assignments');
    }

    /**
     * @inheritdoc
     */
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('stripe', 'Select the groups you want subscribers of this plan to be added to.');
    }

    /**
     * @inheritdoc
     */
    protected function selectorIcon(): ?string
    {
        return '@appicons/people-group.svg';
    }

    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Price $element */
        // We don’t want to display this for one-time items:
        if (!$element->isRecurring()) {
            return null;
        }

        // User groups are only configurable in Craft Pro, which is not a requirement of the plugin:
        if (Craft::$app->edition->value !== CmsEdition::Pro->value) {
            return null;
        }

        return parent::formHtml($element, $static);
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Price) {
            throw new InvalidArgumentException(sprintf('%s can only be used in price field layouts.', __CLASS__));
        }

        Craft::$app->getView()->registerDeltaName($this->attribute());

        return Craft::$app->getView()->renderTemplate('stripe/fieldlayoutelements/usergroupassignmentsfield', [
            'plan' => $element,
        ]);
    }
}
