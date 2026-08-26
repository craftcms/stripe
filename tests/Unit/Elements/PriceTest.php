<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Elements;

use craft\stripe\elements\Price;
use craft\stripe\helpers\Price as PriceHelper;
use craft\stripe\Plugin;
use craft\stripe\tests\UnitTestCase;

class PriceTest extends UnitTestCase
{
    public function testGetStatusReturnsStripeArchivedWhenStripeStatusIsArchived(): void
    {
        $price = new Price();
        $price->enabled = true;
        $price->stripeStatus = 'archived';

        $this->assertSame(Price::STATUS_STRIPE_ARCHIVED, $price->getStatus());
    }

    public function testGetStatusReturnsLiveByDefaultWhenEnabled(): void
    {
        $price = new Price();
        $price->enabled = true;
        $price->stripeStatus = 'active';

        $this->assertSame(Price::STATUS_LIVE, $price->getStatus());
    }

    public function testGetStatusReturnsDisabledWhenNotEnabled(): void
    {
        $price = new Price();
        $price->enabled = false;

        $this->assertSame(Price::STATUS_DISABLED, $price->getStatus());
    }

    public function testGetStripeStatusHtmlForActive(): void
    {
        $price = new Price();
        $price->stripeStatus = 'active';

        $this->assertStringContainsString('green', $price->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlForArchived(): void
    {
        $price = new Price();
        $price->stripeStatus = 'archived';

        $this->assertStringContainsString('red', $price->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlTitleizesMultiWordStatus(): void
    {
        $price = new Price();
        $price->stripeStatus = 'past_due';

        $this->assertStringContainsString('Past Due', $price->getStripeStatusHtml());
    }

    public function testGetStripeEditUrl(): void
    {
        $price = new Price();
        $price->stripeId = 'price_123';

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/prices/price_123',
            $price->getStripeEditUrl()
        );
    }

    public function testSetDataAcceptsArray(): void
    {
        $price = new Price();
        $price->setData(['id' => 'price_123']);

        $this->assertSame(['id' => 'price_123'], $price->getData());
    }

    public function testGetDataDefaultsToEmptyArray(): void
    {
        $price = new Price();

        $this->assertSame([], $price->getData());
    }

    public function testUnitAmountDelegatesToPriceHelper(): void
    {
        $stripePrice = ['unit_amount' => 1050, 'currency' => 'usd'];
        $price = new Price();
        $price->setData($stripePrice);

        $this->assertSame(PriceHelper::asUnitAmount($stripePrice), $price->unitAmount());
    }

    public function testUnitPriceDelegatesToPriceHelper(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
            'recurring' => null,
        ];
        $price = new Price();
        $price->setData($stripePrice);

        $this->assertSame(PriceHelper::asUnitPrice($stripePrice), $price->unitPrice());
    }

    public function testPricePerUnitDelegatesToPriceHelper(): void
    {
        $stripePrice = [
            'unit_amount' => 1050,
            'currency' => 'usd',
            'custom_unit_amount' => null,
            'transform_quantity' => null,
        ];
        $price = new Price();
        $price->setData($stripePrice);

        $this->assertSame(PriceHelper::asPricePerUnit($stripePrice), $price->pricePerUnit());
    }

    public function testIntervalDelegatesToPriceHelper(): void
    {
        $stripePrice = ['recurring' => ['interval_count' => 1, 'interval' => 'month']];
        $price = new Price();
        $price->setData($stripePrice);

        $this->assertSame(PriceHelper::getInterval($stripePrice), $price->interval());
    }
}
