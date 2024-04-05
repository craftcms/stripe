<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\ElementCollection;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\stripe\elements\Product;
use yii\base\InvalidArgumentException;

/**
 * Class PricesField.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PricesField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'prices';

    /**
     * @inheritdoc
     */
    public bool $required = true;

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
        return Craft::t('stripe', 'Prices');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(sprintf('%s can only be used in product field layouts.', __CLASS__));
        }

        $value = $element->getPrices();

        if (empty($value)) {
            return '<p class="light">' . Craft::t('stripe', 'No Stripe Prices available.') . '</p>';
        }

        $size = Cp::CHIP_SIZE_SMALL;

        $id = Html::id($this->attribute);
        $html = "<div id='$id' class='elementselect noteditable'>" .
            "<div class='elements chips" . ($size === Cp::CHIP_SIZE_LARGE ? ' inline-chips' : '') . "'>";

        foreach ($value as $relatedElement) {
            $html .= Cp::elementChipHtml($relatedElement, [
                'size' => $size,
            ]);
        }

        $html .= '</div></div>';

        return $html;
    }
}
