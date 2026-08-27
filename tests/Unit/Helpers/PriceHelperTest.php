<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Helpers;

use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\tests\UnitTestCase;

class PriceHelperTest extends UnitTestCase
{
    public function testUnitAmountNumberDividesByHundred(): void
    {
        $stripePrice = ['unit_amount' => 1050, 'currency' => 'usd'];
        $this->assertSame(10.5, PriceHelper::asUnitAmountNumber($stripePrice));
    }

    public function testUnitAmountNumberSkipsDivisionForZeroDecimalCurrency(): void
    {
        $stripePrice = ['unit_amount' => 1000, 'currency' => 'JPY'];
        $this->assertSame(1000.0, PriceHelper::asUnitAmountNumber($stripePrice));
    }

    public function testUnitAmountNumberFallsBackToFirstTier(): void
    {
        $stripePrice = [
            'unit_amount' => null,
            'currency' => 'usd',
            'tiers' => [
                ['unit_amount' => 600],
            ],
        ];
        $this->assertSame(6.0, PriceHelper::asUnitAmountNumber($stripePrice));
    }

    public function testGetIntervalReturnsOneTimeForNonRecurringPrice(): void
    {
        $stripePrice = ['recurring' => null];
        $this->assertSame('One-time', PriceHelper::getInterval($stripePrice));
    }

    public function testGetIntervalReturnsEveryNIntervalForRecurringPrice(): void
    {
        $stripePrice = ['recurring' => ['interval_count' => 3, 'interval' => 'month']];
        $this->assertSame('Every 3 months', PriceHelper::getInterval($stripePrice));
    }

    public function testAsPricePerUnitReturnsCustomerChoosesForCustomUnitAmount(): void
    {
        $stripePrice = [
            'unit_amount' => null,
            'currency' => 'usd',
            'custom_unit_amount' => ['minimum' => 100],
        ];
        $this->assertSame('Customer chooses', PriceHelper::asPricePerUnit($stripePrice));
    }

    public function testAsPricePerUnitBuildsTieredStartsAtString(): void
    {
        $stripePrice = [
            'unit_amount' => null,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'tiers' => [
                ['unit_amount' => 1000, 'flat_amount' => 500],
            ],
            'transform_quantity' => null,
        ];
        $result = PriceHelper::asPricePerUnit($stripePrice);
        $this->assertSame('Starts at $10.00 per unit + $5.00', $result);
    }

    public function testAsPricePerUnitBuildsPerGroupStringForTransformQuantity(): void
    {
        $stripePrice = [
            'unit_amount' => 600,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => ['divide_by' => 10, 'round' => 'up'],
        ];
        $result = PriceHelper::asPricePerUnit($stripePrice);
        $this->assertStringContainsString('per group of 10', $result);
    }

    public function testAsPricePerUnitReturnsPlainAmountForSimplePrice(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
        ];
        $this->assertSame('$10.50', PriceHelper::asPricePerUnit($stripePrice));
    }

    public function testAsUnitPriceAppendsIntervalForSingleIntervalCount(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'recurring' => ['interval_count' => 1, 'interval' => 'month'],
        ];
        $this->assertSame('$10.50/month', PriceHelper::asUnitPrice($stripePrice));
    }

    public function testAsUnitPriceAppendsEveryNIntervalForMultipleIntervalCount(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'recurring' => ['interval_count' => 3, 'interval' => 'month'],
        ];
        $this->assertStringContainsString('every 3 month', PriceHelper::asUnitPrice($stripePrice));
    }

    public function testAsUnitPriceReturnsPricePerUnitForOneTimePrice(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'recurring' => null,
        ];
        $this->assertSame(PriceHelper::asPricePerUnit($stripePrice), PriceHelper::asUnitPrice($stripePrice));
    }
}
