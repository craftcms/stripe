<?php

declare(strict_types=1);

use CraftCms\Stripe\Support\PriceFormatter;

it('converts the unit amount to a decimal number', function (array $price, float $expected) {
    expect(PriceFormatter::asUnitAmountNumber($price))->toBe($expected);
})->with([
    'cents' => [['unit_amount' => 1050, 'currency' => 'usd'], 10.5],
    'zero-decimal currency' => [['unit_amount' => 1050, 'currency' => 'jpy'], 1050.0],
    'first tier' => [['unit_amount' => null, 'currency' => 'usd', 'tiers' => [['unit_amount' => 500]]], 5.0],
]);

it('describes the billing interval', function (?array $recurring, string $expected) {
    expect(PriceFormatter::getInterval(['recurring' => $recurring]))->toBe($expected);
})->with([
    'one-time' => [null, 'One-time'],
    'recurring' => [['interval' => 'month', 'interval_count' => 3], 'Every 3 months'],
]);

it('formats the price per unit', function (array $price, string $expected) {
    expect(PriceFormatter::asPricePerUnit($price + ['currency' => 'usd']))->toBe($expected);
})->with([
    'simple' => [['unit_amount' => 1000], '$10.00'],
    'customer chooses' => [['unit_amount' => null, 'custom_unit_amount' => ['minimum' => 100]], 'Customer chooses'],
    'tiered' => [['unit_amount' => null, 'tiers' => [['unit_amount' => 500, 'flat_amount' => 100]]], 'Starts at $5.00 per unit + $1.00'],
    'per group' => [['unit_amount' => 1000, 'transform_quantity' => ['divide_by' => 5]], '$10.00 per group of 5'],
]);

it('formats the unit price with its interval', function (?array $recurring, string $expected) {
    expect(PriceFormatter::asUnitPrice(['unit_amount' => 1000, 'currency' => 'usd', 'recurring' => $recurring]))->toBe($expected);
})->with([
    'one-time' => [null, '$10.00'],
    'every interval' => [['interval' => 'month', 'interval_count' => 1], '$10.00/month'],
    'every few intervals' => [['interval' => 'month', 'interval_count' => 3], '$10.00 every 3 months'],
]);
