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
use craft\stripe\elements\Price as PriceElement;
use craft\stripe\records\PriceData;

/**
 * Stipe Price Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Price
{
    /**
     * @return string
     */
    public static function renderCardHtml(PriceElement $price): string
    {
        $formatter = Craft::$app->getFormatter();

        $title = Html::tag('h3', $price->title, [
            'class' => 'pec-title',
        ]);

//        $subTitle = Html::tag('p', $product->productType, [
//            'class' => 'pec-subtitle',
//        ]);
        $externalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external',
            ],
        ]);
        $cardHeader = Html::a($title . /*$subTitle .*/ $externalLink, $price->getStripeEditUrl(), [
            'style' => '',
            'class' => 'pec-header',
            'target' => '_blank',
            'title' => Craft::t('stripe', 'Open in Stripe'),
        ]);

        $hr = Html::tag('hr', '', [
            'class' => '',
        ]);

        $meta = [];

        $meta[Craft::t('stripe', 'Status')] = $price->getStripeStatusHtml();
        $meta[Craft::t('stripe', 'Stripe ID')] = Html::tag('code', (string)$price->stripeId);

        /*// Data
        $dataAttributesToDisplay = [
            'url',
            'type',
            'images',
//            'created',
//            'updated',
            'features',
            'metadata',
            'tax_code',
            'shippable',
            'attributes',
            'unit_label',
            'description',
            'default_price',
            'package_dimensions',
            'statement_descriptor',
        ];

        if (count($price->getData()) > 0) {
            foreach ($price->getData() as $key => $value) {
                $label = StringHelper::titleize(implode(' ', StringHelper::toWords($key, false, true)));
                if (in_array($key, $dataAttributesToDisplay)) {
                    if (!is_array($value)) {
                        $meta[Craft::t('stripe', $label)] = $value;
                    }
                    else {
                        switch ($key) {
                            case 'images':
                                // despite it being called "images" it looks like you can only have one?
                                $meta[Craft::t('stripe', $label)] = collect($price->data[$key])
                                    ->map(function($img) {
                                        return Html::a(Html::img($img, ['width' => 64]), $img, ['target' => '_blank']);
                                    })
                                    ->join(' ');
                                break;
                            case 'features':
                                $meta[Craft::t('stripe', $label)] = collect($price->data[$key])
                                    ->pluck('name')
                                    ->filter()
                                    ->join(', ');
                                break;
                            case 'metadata':
                                $meta[Craft::t('stripe', $label)] = collect($price->data[$key])
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
                                $meta[Craft::t('stripe', $label)] = Html::tag(
                                    'span',
                                    $value['id'],
                                    [
                                        'class' => 'break-word no-scroll',
                                    ]
                                );
                                break;
                            default:
                                $meta[Craft::t('stripe', $label)] = collect($price->data[$key])
                                    ->join('; ');
                                break;
                        }
                    }
                }
            }
        }*/

        $meta[Craft::t('stripe', 'Created at')] = $formatter->asDatetime($price->data['created'], Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('stripe', 'Updated at')] = $formatter->asDatetime($price->data['updated'], Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        // This is the date updated in the database which represents the last time it was updated from a Stripe webhook or sync.
        $dateUpdated = DateTimeHelper::toDateTime($price->data['updated']);
        $now = new \DateTime();
        $diff = $now->diff($dateUpdated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Updated ' . $duration . ' ago.' . $spinner, [
            'class' => 'pec-footer',
        ]);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $price->id,
            'hx' => [
                'get' => UrlHelper::actionUrl('stripe/prices/render-meta-card-html', [
                    'id' => $price->id,
                ]),
                'swap' => 'outerHTML',
                'trigger' => 'every 15s',
            ],
        ]);
    }
}
