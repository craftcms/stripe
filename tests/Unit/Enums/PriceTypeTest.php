<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Enums;

use craft\stripe\enums\PriceType;
use craft\stripe\tests\UnitTestCase;

class PriceTypeTest extends UnitTestCase
{
    public function testHasTwoCases(): void
    {
        $this->assertCount(2, PriceType::cases());
    }

    public function testOneTimeValue(): void
    {
        $this->assertSame('one_time', PriceType::OneTime->value);
    }

    public function testRecurringValue(): void
    {
        $this->assertSame('recurring', PriceType::Recurring->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(PriceType::Recurring, PriceType::from('recurring'));
    }
}
