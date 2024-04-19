<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\helpers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\stripe\elements\Product as ProductElement;

/**
 * Stipe Product Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Product
{
    /**
     * @return string
     */
    public static function renderCardHtml(ProductElement $product): string
    {
        $formatter = Craft::$app->getFormatter();

        $title = Html::tag('h3', $product->title, [
            'class' => 'pec-title',
        ]);

        $externalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external',
            ],
        ]);
        $cardHeader = Html::a($title . $externalLink, $product->getStripeEditUrl(), [
            'style' => '',
            'class' => 'pec-header',
            'target' => '_blank',
            'title' => Craft::t('stripe', 'Open in Stripe'),
        ]);

        $hr = Html::tag('hr', '', [
            'class' => '',
        ]);

        $meta = [];

        $meta[Craft::t('stripe', 'Status')] = $product->getStripeStatusHtml();
        $meta[Craft::t('stripe', 'Stripe ID')] = Html::tag(
            'code',
            (string)$product->stripeId,
            ['class' => 'break-word no-scroll'],
        );

        // Data
        $dataAttributesToDisplay = [
            'images',
            'features',
            'metadata',
            'tax_code',
            'shippable',
            'attributes',
            'description',
            'default_price',
        ];

        if (count($product->getData()) > 0) {
            foreach ($product->getData() as $key => $value) {
                $label = StringHelper::titleize(implode(' ', StringHelper::toWords($key, false, true)));
                if (in_array($key, $dataAttributesToDisplay)) {
                    if (!is_array($value)) {
                    }
                    else {
                        switch ($key) {
                            case 'images':
                                // despite it being called "images" it looks like you can only have one?
                                $meta[Craft::t('stripe', $label)] = collect($product->getData()[$key])
                                    ->map(function($img) {
                                        return Html::a(Html::img($img, ['width' => 64]), $img, ['target' => '_blank']);
                                    })
                                    ->join(' ');
                                break;
                            case 'features':
                                $meta[Craft::t('stripe', $label)] = collect($value)
                                    ->pluck('name')
                                    ->filter()
                                    ->join(', ');
                                break;
                            case 'metadata':
                                $meta[Craft::t('stripe', $label)] = collect($value)
                                    ->map(function($val, $i) {
                                        // todo: style me!
                                        return Html::beginTag('div', ['class' => 'fullwidth']) .
                                            Html::tag('em', $i . ': ') .
                                            $val .
                                            Html::endTag('div');
                                    })
                                    ->join(' ');
                                break;
                            case 'default_price':
                                $defaultPrice = $product->getDefaultPrice();
                                $meta[Craft::t('stripe', $label)] =
                                    $defaultPrice ? Cp::elementChipHtml($defaultPrice, ['size' => Cp::CHIP_SIZE_SMALL]) : '';
                                break;
                            default:
                                $meta[Craft::t('stripe', $label)] = collect($value)
                                    ->join('; ');
                                break;
                        }
                    }
                }
            }
        }

        $meta[Craft::t('stripe', 'Created at')] = $formatter->asDatetime($product->getData()['created'], Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('stripe', 'Updated at')] = $formatter->asDatetime($product->getData()['updated'], Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        // This is the date updated in the database which represents the last time it was updated from a Stripe webhook or sync.
        $dateUpdated = DateTimeHelper::toDateTime($product->getData()['updated']);
        $now = new \DateTime();
        $diff = $now->diff($dateUpdated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Updated ' . $duration . ' ago.' . $spinner, [
            'class' => 'pec-footer',
        ]);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $product->id,
            'hx' => [
                'get' => UrlHelper::actionUrl('stripe/products/render-meta-card-html', [
                    'id' => $product->id,
                ]),
                'swap' => 'outerHTML',
                'trigger' => 'every 15s',
            ],
        ]);
    }
}
