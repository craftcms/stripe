<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Support;

use CraftCms\Cms\Support\Facades\I18N;

use function CraftCms\Cms\t;

/**
 * Formats Stripe price objects for display.
 *
 * Each method takes a Stripe price as an array or `Stripe\Price`, or a price element’s `data`.
 *
 * @since 3.0.0
 */
final class PriceFormatter
{
    /**
     * @var string[] Currencies Stripe counts in whole units rather than cents
     */
    public const array ZERO_DECIMAL_CURRENCIES = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
        'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    /**
     * Converts an amount in a currency’s smallest unit, as Stripe sends it, to a decimal amount.
     */
    public static function toDecimal(int|float|string|null $amount, string $currency): ?float
    {
        if ($amount === null) {
            return null;
        }

        return in_array(strtolower($currency), self::ZERO_DECIMAL_CURRENCIES, true) ? (float) $amount : $amount / 100;
    }

    /**
     * The unit amount as a decimal number, taking the first tier’s amount for tiered prices.
     */
    public static function asUnitAmountNumber(mixed $stripePrice): ?float
    {
        $unitAmount = $stripePrice['unit_amount'] ?? null;

        if ($unitAmount === null && isset($stripePrice['tiers'][0])) {
            $unitAmount = $stripePrice['tiers'][0]['unit_amount'] ?? null;
        }

        return self::toDecimal($unitAmount, (string) ($stripePrice['currency'] ?? ''));
    }

    public static function asUnitAmount(mixed $stripePrice): ?string
    {
        $unitAmount = self::asUnitAmountNumber($stripePrice);

        return $unitAmount ? I18N::getFormatter()->asCurrency($unitAmount, $stripePrice['currency']) : null;
    }

    public static function asUnitPrice(mixed $stripePrice): string
    {
        $pricePerUnit = self::asPricePerUnit($stripePrice);
        $recurring = $stripePrice['recurring'] ?? null;

        if ($recurring === null) {
            return $pricePerUnit;
        }

        if ((int) $recurring['interval_count'] === 1) {
            return t('{pricePerUnit}/{interval}', [
                'pricePerUnit' => $pricePerUnit,
                'interval' => t($recurring['interval'], category: 'stripe'),
            ], category: 'stripe');
        }

        return $pricePerUnit.' '.lcfirst(self::getInterval($stripePrice));
    }

    public static function asPricePerUnit(mixed $stripePrice): string
    {
        $currency = (string) ($stripePrice['currency'] ?? '');
        $unitAmount = self::asUnitAmount($stripePrice);

        if ($unitAmount === null && ($stripePrice['custom_unit_amount'] ?? null) !== null) {
            return t('Customer chooses', category: 'stripe');
        }

        if (isset($stripePrice['tiers'][0])) {
            $flatAmount = I18N::getFormatter()->asCurrency(
                self::toDecimal($stripePrice['tiers'][0]['flat_amount'] ?? 0, $currency) ?? 0,
                $currency,
            );

            return t('Starts at', category: 'stripe')." $unitAmount ".t('per unit', category: 'stripe')." + $flatAmount";
        }

        if (($stripePrice['transform_quantity'] ?? null) !== null) {
            return t('{unitPrice} per group of {divideBy}', [
                'unitPrice' => $unitAmount,
                'divideBy' => $stripePrice['transform_quantity']['divide_by'],
            ], category: 'stripe');
        }

        return $unitAmount ?? I18N::getFormatter()->asCurrency(0, $currency);
    }

    public static function getInterval(mixed $stripePrice): string
    {
        $recurring = $stripePrice['recurring'] ?? null;

        if ($recurring === null) {
            return t('One-time', category: 'stripe');
        }

        return t('Every {intervalCount} {interval}', [
            'intervalCount' => $recurring['interval_count'],
            'interval' => $recurring['interval'],
        ], category: 'stripe');
    }
}
