<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Fields;

use craft\stripe\elements\Subscription;
use craft\stripe\fields\Subscriptions;
use craft\stripe\tests\UnitTestCase;

class SubscriptionsFieldTest extends UnitTestCase
{
    public function testDisplayName(): void
    {
        $this->assertNotEmpty(Subscriptions::displayName());
    }

    public function testIcon(): void
    {
        $this->assertSame('clock-rotate-left', Subscriptions::icon());
    }

    public function testDefaultSelectionLabel(): void
    {
        $this->assertNotEmpty(Subscriptions::defaultSelectionLabel());
    }

    public function testElementType(): void
    {
        $this->assertSame(Subscription::class, Subscriptions::elementType());
    }
}
