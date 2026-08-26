<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Elements\Db;

use craft\stripe\elements\Subscription;
use craft\stripe\tests\UnitTestCase;

class SubscriptionQueryTest extends UnitTestCase
{
    public function testStripeStatusSetsValue(): void
    {
        $query = Subscription::find()->stripeStatus('active');

        $this->assertSame('active', $query->stripeStatus);
    }

    public function testStripeStatusAcceptsArray(): void
    {
        $query = Subscription::find()->stripeStatus(['active', 'trialing']);

        $this->assertSame(['active', 'trialing'], $query->stripeStatus);
    }

    public function testStripeStatusNullResetsToNull(): void
    {
        $query = Subscription::find()->stripeStatus('active')->stripeStatus(null);

        $this->assertNull($query->stripeStatus);
    }

    public function testStripeStatusWildcardResetsToNull(): void
    {
        $query = Subscription::find()->stripeStatus('active')->stripeStatus('*');

        $this->assertNull($query->stripeStatus);
    }
}
