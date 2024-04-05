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
        $meta[Craft::t('stripe', 'Stripe ID')] = Html::tag(
            'code',
            (string)$price->stripeId,
            ['class' => 'break-word no-scroll'],
        );
        $meta[Craft::t('stripe', 'Product')] =
            Cp::elementChipHtml($price->product, ['size' => Cp::CHIP_SIZE_SMALL]);

        // Data
        $dataAttributesToDisplay = [
            'currency',
            'nickname',
            'recurring',
            'type',
            'unit_amount',
            'billing_scheme',
            'lookup_key',
            'tax_behavior',
            'tiers_mode',
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
                            case 'recurring':
                                $meta[Craft::t('stripe', $label)] = Html::tag(
                                    'span',
                                    $value['interval_count'] . ' ' . $value['interval'],
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
        }

        $meta[Craft::t('stripe', 'Created at')] = $formatter->asDatetime($price->data['created'], Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        // This is the date updated in the database which represents the last time it was updated from a Stripe webhook or sync.
        $dateCreated = DateTimeHelper::toDateTime($price->data['created']);
        $now = new \DateTime();
        $diff = $now->diff($dateCreated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Created ' . $duration . ' ago.' . $spinner, [
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
                'trigger' => 'every 60s',
            ],
        ]);
    }
}
