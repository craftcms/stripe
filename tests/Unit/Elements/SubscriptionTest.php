<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Elements;

use craft\enums\Color;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\stripe\tests\UnitTestCase;

class SubscriptionTest extends UnitTestCase
{
    public function testStripeStatuses(): void
    {
        $statuses = Subscription::stripeStatuses();

        $this->assertSame(Color::Green, $statuses[Subscription::STRIPE_STATUS_ACTIVE]['color']);
        $this->assertSame(Color::Orange, $statuses[Subscription::STRIPE_STATUS_SCHEDULED]['color']);
        $this->assertSame(Color::Red, $statuses[Subscription::STRIPE_STATUS_CANCELED]['color']);
        $this->assertSame(Color::Yellow, $statuses[Subscription::STRIPE_STATUS_TRIALING]['color']);
    }

    public function testGetStripeStatusHtmlFallsBackToBlueForUnknownStatus(): void
    {
        $subscription = new Subscription();
        $subscription->stripeStatus = 'past_due';

        $this->assertStringContainsString('blue', $subscription->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlTitleizesMultiWordUnknownStatus(): void
    {
        $subscription = new Subscription();
        $subscription->stripeStatus = 'past_due';

        $this->assertStringContainsString('Past Due', $subscription->getStripeStatusHtml());
    }

    public function testShowStatusFieldIsFalse(): void
    {
        $subscription = new Subscription();
        $this->assertFalse($subscription->showStatusField());
    }

    public function testGetStatusReturnsScheduled(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = true;
        $subscription->stripeStatus = Subscription::STRIPE_STATUS_SCHEDULED;

        $this->assertSame(Subscription::STATUS_STRIPE_SCHEDULED, $subscription->getStatus());
    }

    public function testGetStatusReturnsCanceled(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = true;
        $subscription->stripeStatus = Subscription::STRIPE_STATUS_CANCELED;

        $this->assertSame(Subscription::STATUS_STRIPE_CANCELED, $subscription->getStatus());
    }

    public function testGetStatusReturnsLiveForActive(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = true;
        $subscription->stripeStatus = Subscription::STRIPE_STATUS_ACTIVE;

        $this->assertSame(Subscription::STATUS_LIVE, $subscription->getStatus());
    }

    public function testGetStatusReturnsLiveForTrialing(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = true;
        $subscription->stripeStatus = Subscription::STRIPE_STATUS_TRIALING;

        $this->assertSame(Subscription::STATUS_LIVE, $subscription->getStatus());
    }

    public function testGetStatusReturnsSuspendedByDefault(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = true;
        $subscription->stripeStatus = 'past_due';

        $this->assertSame(Subscription::STATUS_STRIPE_SUSPENDED, $subscription->getStatus());
    }

    public function testGetStatusReturnsDisabledWhenNotEnabled(): void
    {
        $subscription = new Subscription();
        $subscription->enabled = false;

        $this->assertSame(Subscription::STATUS_DISABLED, $subscription->getStatus());
    }

    public function testGetStripeStatusHtmlColors(): void
    {
        $subscription = new Subscription();

        $subscription->stripeStatus = Subscription::STRIPE_STATUS_ACTIVE;
        $this->assertStringContainsString('green', $subscription->getStripeStatusHtml());

        $subscription->stripeStatus = Subscription::STRIPE_STATUS_SCHEDULED;
        $this->assertStringContainsString('orange', $subscription->getStripeStatusHtml());

        $subscription->stripeStatus = Subscription::STRIPE_STATUS_CANCELED;
        $this->assertStringContainsString('red', $subscription->getStripeStatusHtml());

        $subscription->stripeStatus = Subscription::STRIPE_STATUS_TRIALING;
        $this->assertStringContainsString('yellow', $subscription->getStripeStatusHtml());

        $subscription->stripeStatus = 'past_due';
        $this->assertStringContainsString('blue', $subscription->getStripeStatusHtml());
    }

    public function testGetStripeEditUrl(): void
    {
        $subscription = new Subscription();
        $subscription->stripeId = 'sub_123';

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/subscriptions/sub_123',
            $subscription->getStripeEditUrl()
        );
    }

    public function testSetDataAcceptsArray(): void
    {
        $subscription = new Subscription();
        $subscription->setData(['id' => 'sub_123']);

        $this->assertSame(['id' => 'sub_123'], $subscription->getData());
    }

    public function testGetDataDefaultsToEmptyArray(): void
    {
        $subscription = new Subscription();

        $this->assertSame([], $subscription->getData());
    }
}
