<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Html;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use yii\base\InvalidArgumentException;

/**
 * Class CustomersField.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CustomersField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'stripeCustomers';

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
        return Craft::t('stripe', 'Stripe Customers');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof User) {
            throw new InvalidArgumentException(sprintf('%s can only be used in user field layout.', __CLASS__));
        }

        $value = Plugin::getInstance()->getCustomers()->getCustomersByEmail($element->email);

        if (empty($value)) {
            return Html::tag('p', Craft::t('stripe', 'No Stripe Customers available.'), [
                'class' => 'light',
            ]);
        }

        $id = Html::id($this->attribute);
        $html = "<div id='$id' class='customer-select-wrapper noteditable'>";

        /** @var Customer $customer */
        foreach ($value as $customer) {
            $html .= "<div class='customer-wrapper'>" .
            "<div>" .
                $customer->data['name'] .
                " (<a href='{$customer->getStripeEditUrl()}' target='_blank'>" .
                    $customer->stripeId .
                    " <span data-icon='external'></span>" .
                "</a>)".
            "</div>" .
            "</div>";
        }

        $html .= '</div>';

        return $html;
    }
}
