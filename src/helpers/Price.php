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
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\stripe\elements\Price as PriceElement;

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

        /** @var \Stripe\Price $stripePrice */
        $stripePrice = $price->getData();

        $properties = [
            'unitPrice',
            //'type',
            'currency',
            'interval',
            'presetAmount',
            'minAmount',
            'maxAmount',
            'pricePerUnit',
            'partialPackages',
            'isDefaultPrice',
        ];

        $title = Html::tag('h3', $price->title, [
            'class' => 'pec-title',
        ]);

        $externalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external',
            ],
        ]);
        $cardHeader = Html::a($title . $externalLink, $price->getStripeEditUrl(), [
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


        if (count($stripePrice) > 0) {
            $unitPrice = $formatter->asCurrency($stripePrice['unit_amount'] / 100, $stripePrice['currency']);

            if ($stripePrice['recurring'] === null) {
                $interval = Craft::t('stripe', 'One-time');
            } else {
                $interval = "Every {$stripePrice['recurring']['interval_count']} {$stripePrice['recurring']['interval']}";
            }

            if ($stripePrice['transform_quantity'] === null) {
                $pricePerUnit = $unitPrice;
            } else {
                $pricePerUnit = $unitPrice . " per group of " . $stripePrice['transform_quantity']['divide_by'];
            }

            foreach ($properties as $property) {
                switch ($property) {
                    case 'unitPrice':
                        if (isset($stripePrice['custom_unit_amount'])) {
                            $unitPrice = Craft::t('stripe', 'Customer input price');
                        }
                        if ($stripePrice['recurring'] !== null) {
                            if ($stripePrice['recurring']['interval_count'] == 1) {
                                $unitPrice = "$pricePerUnit/{$stripePrice['recurring']['interval']}";
                            } else {
                                $unitPrice = $pricePerUnit . ' ' . lcfirst($interval);
                            }
                        }
                        $meta[Craft::t('stripe', 'Unit price')] = $unitPrice;
                        break;
                    case 'currency':
                        $meta[Craft::t('stripe', 'Currency')] = strtoupper($stripePrice['currency']);
                        break;
                    case 'interval':
                        $meta[Craft::t('stripe', 'Interval')] = $interval;
                        break;
                    case 'presetAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Preset Amount')] =
                                $formatter->asCurrency($stripePrice['custom_unit_amount']['preset']/100, $stripePrice['currency']);
                        }
                        break;
                    case 'minAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Min Amount')] =
                                $formatter->asCurrency($stripePrice['custom_unit_amount']['minimum']/100, $stripePrice['currency']);
                        }
                        break;
                    case 'maxAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Max Amount')] =
                                $formatter->asCurrency($stripePrice['custom_unit_amount']['maximum']/100, $stripePrice['currency']);
                        }
                        break;
                    case 'pricePerUnit':
                        if ($stripePrice['custom_unit_amount']) {
                            $pricePerUnit = Craft::t('stripe', 'Customer chooses');
                        }

                        $meta[Craft::t('stripe', 'Price per Unit')] = $pricePerUnit;
                        break;
                    case 'partialPackages':
                        if ($stripePrice['transform_quantity'] !== null) {
                            $meta[Craft::t('stripe', 'Partial packages')] =
                                Craft::t('stripe', 'Round {roundDirection} to nearest complete package', [
                                    'roundDirection' => $stripePrice['transform_quantity']['round'],
                                ]);
                            break;
                        }
                }
            }

            $meta[Craft::t('stripe', 'Metadata')] = collect($stripePrice['metadata'])
                ->map(function($value, $key) {
                    // todo: style me!
                    return Html::beginTag('div', ['class' => 'fullwidth']) .
                        Html::tag('em', $key . ': ') .
                        $value .
                        Html::endTag('div');
                })
                ->join(' ');
        }

        $meta[Craft::t('stripe', 'Created at')] = $formatter->asDatetime($stripePrice['created'], Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        $dateCreated = DateTimeHelper::toDateTime($stripePrice['created']);
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
