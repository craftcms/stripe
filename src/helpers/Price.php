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
use yii\base\InvalidConfigException;

/**
 * Stipe Price Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Price
{
    /**
     * @var array|string[] list of currencies that have zero decimals
     */
    public static array $zeroDecimalCurrencies = [
        'bif',
        'clp',
        'djf',
        'gnf',
        'jpy',
        'kmf',
        'krw',
        'mga',
        'pyg',
        'rwf',
        'ugx',
        'vnd',
        'vuv',
        'xaf',
        'xof',
        'xpf',
    ];

    public static array $threeDecimalCurrencies = [
        'bdh',
        'jod',
        'kwd',
        'omr',
        'tnd',
    ];

    /**
     * Returns unit amount as a number
     *
     * examples:
     * 10.50
     * 13.35
     * 1,000
     * 6.00 (when the price is: £6.00 per group of 10)
     * 10.00 (when the price is: Starts at £10.00 per unit + £0.00)
     * 0.00 (when the price is: Customer chooses)
     *
     * @param mixed $stripePrice
     * @return float|null
     * @throws InvalidConfigException
     */
    public static function asUnitAmountNumber(mixed $stripePrice): ?float
    {
        $unitAmount = $stripePrice['unit_amount'];

        if ($unitAmount === null) {
            if (isset($stripePrice['tiers'])) {
                $unitAmount = $stripePrice['tiers'][0]['unit_amount'];
            }
        }

        if (!in_array(strtolower($stripePrice['currency']), self::$zeroDecimalCurrencies)) {
            $unitAmount = $unitAmount / 100;
        }

        return $unitAmount;
    }

    /**
     * Returns unit amount as number & currency.
     *
     * examples:
     * £10.50
     * $13.35
     * ¥1,000
     * £6.00 (when the price is: £6.00 per group of 10)
     * £10.00 (when the price is: Starts at £10.00 per unit + £0.00)
     * Customer chooses
     *
     * @param mixed $stripePrice
     * @return string|null
     * @throws InvalidConfigException
     */
    public static function asUnitAmount(mixed $stripePrice): ?string
    {
        $unitAmount = self::asUnitAmountNumber($stripePrice);

        return $unitAmount ? Craft::$app->getFormatter()->asCurrency($unitAmount, $stripePrice['currency']) : null;
    }

    /**
     * Returns the unit price of the Stripe Price object.
     * It can be loosely thought of as price per unit + interval.
     *
     * examples:
     * £10.50
     * £10.50/month
     * £10.50 every 3 month
     * £6.00 per group of 10
     * Starts at £10.00 per unit + £0.00/month
     * Customer chooses
     *
     * @param mixed $stripePrice
     * @return string
     * @throws InvalidConfigException
     */
    public static function asUnitPrice(mixed $stripePrice): string
    {
        $interval = self::getInterval($stripePrice);
        $pricePerUnit = self::asPricePerUnit($stripePrice);

        if ($stripePrice['recurring'] !== null) {
            if ($stripePrice['recurring']['interval_count'] == 1) {
                $unitPrice = "$pricePerUnit/{$stripePrice['recurring']['interval']}";
            } else {
                $unitPrice = $pricePerUnit . ' ' . lcfirst($interval);
            }
        } else {
            $unitPrice = $pricePerUnit;
        }

        return $unitPrice;
    }

    /**
     * Returns the price per unit
     *
     * examples:
     * £10.50
     * £10.50/month
     * £10.50 every 3 month
     * £6.00 per group of 10
     * Starts at £10.00 per unit + £0.00
     * Customer chooses
     *
     * @param mixed $stripePrice
     * @return string
     * @throws InvalidConfigException
     */
    public static function asPricePerUnit(mixed $stripePrice): string
    {
        $unitAmount = self::asUnitAmount($stripePrice);

        if ($unitAmount === null && $stripePrice['custom_unit_amount'] !== null) {
            return Craft::t('stripe', 'Customer chooses');
        }

        if (isset($stripePrice['tiers'])) {
            $flatAmount = $stripePrice['tiers'][0]['flat_amount'];
            if (!in_array(strtolower($stripePrice['currency']), self::$zeroDecimalCurrencies)) {
                $flatAmount = $flatAmount / 100;
            }
            $flatAmount = Craft::$app->getFormatter()->asCurrency($flatAmount, $stripePrice['currency']);

            return Craft::t('stripe', 'Starts at') .
                " $unitAmount " .
                Craft::t('stripe', 'per unit') .
                " + $flatAmount";
        }

        if ($stripePrice['transform_quantity'] === null) {
            $pricePerUnit = $unitAmount;
        } else {
            $pricePerUnit = Craft::t('stripe', '{unitPrice} per group of {divideBy}', [
                'unitPrice' => $unitAmount,
                'divideBy' => $stripePrice['transform_quantity']['divide_by'],
            ]);
        }

        // if pricePerUnit is still null, change it to zero
        if ($pricePerUnit === null) {
            $pricePerUnit = Craft::$app->getFormatter()->asCurrency(0, $stripePrice['currency']);
        }

        return $pricePerUnit;
    }

    /**
     * Returns the interval of the price.
     *
     * examples:
     * One-time
     * Every 1 month
     *
     * @param mixed $stripePrice
     * @return string
     */
    public static function getInterval(mixed $stripePrice): string
    {
        if ($stripePrice['recurring'] === null) {
            $interval = Craft::t('stripe', 'One-time');
        } else {
            $interval = Craft::t('stripe', 'Every {intervalCount} {interval}', [
                'intervalCount' => $stripePrice['recurring']['interval_count'],
                'interval' => $stripePrice['recurring']['interval'],
            ]);
        }

        return $interval;
    }

    /**
     * Returns html string for the Price card.
     *
     * @param PriceElement $price
     * @return string
     * @throws InvalidConfigException
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
        $meta[Craft::t('stripe', 'Stripe ID')] =
            Cp::renderTemplate('_includes/forms/copytext.twig', [
                'id' => "stripe-price-stripeId",
                'class' => ['code', 'text', 'fullwidth'],
                'value' => (string)$price->stripeId,
            ]);
        $meta[Craft::t('stripe', 'Product')] =
            Cp::elementChipHtml($price->product, ['size' => Cp::CHIP_SIZE_SMALL]);


        if (count($stripePrice) > 0) {
            foreach ($properties as $property) {
                switch ($property) {
                    case 'unitPrice':
                        $meta[Craft::t('stripe', 'Unit price')] = self::asUnitPrice($stripePrice);
                        break;
                    case 'currency':
                        $meta[Craft::t('stripe', 'Currency')] = strtoupper($stripePrice['currency']);
                        break;
                    case 'interval':
                        $meta[Craft::t('stripe', 'Interval')] = self::getInterval($stripePrice);
                        break;
                    case 'presetAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Preset Amount')] =
                                self::asUnitAmount($stripePrice);
                        }
                        break;
                    case 'minAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Min Amount')] =
                                self::asUnitAmount($stripePrice);
                        }
                        break;
                    case 'maxAmount':
                        if ($stripePrice['custom_unit_amount']) {
                            $meta[Craft::t('stripe', 'Max Amount')] =
                                self::asUnitAmount($stripePrice);
                        }
                        break;
                    case 'pricePerUnit':
                        $meta[Craft::t('stripe', 'Price per Unit')] = self::asPricePerUnit($stripePrice);
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
        $footer = Html::tag(
            'div',
            Craft::t('stripe', 'Created {duration} ago.', ['duration' => $duration]) . $spinner,
            [
                'class' => 'pec-footer',
            ]
        );

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
