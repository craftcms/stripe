<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Elements\Db;

use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\stripe\tests\TestCase;
use Stripe\Subscription as StripeSubscription;

class SubscriptionQueryTest extends TestCase
{
    public function testStripeStatusFiltersByStatus(): void
    {
        $this->seedSubscription('sub_active', 'active');
        $this->seedSubscription('sub_canceled', 'canceled');

        $stripeIds = array_map(
            fn(Subscription $subscription) => $subscription->stripeId,
            Subscription::find()->stripeStatus('active')->all()
        );

        $this->assertContains('sub_active', $stripeIds);
        $this->assertNotContains('sub_canceled', $stripeIds);
    }

    public function testStripeStatusAcceptsArrayOfStatuses(): void
    {
        $this->seedSubscription('sub_active2', 'active');
        $this->seedSubscription('sub_trialing2', 'trialing');
        $this->seedSubscription('sub_canceled2', 'canceled');

        $stripeIds = array_map(
            fn(Subscription $subscription) => $subscription->stripeId,
            Subscription::find()->stripeStatus(['active', 'trialing'])->all()
        );

        $this->assertContains('sub_active2', $stripeIds);
        $this->assertContains('sub_trialing2', $stripeIds);
        $this->assertNotContains('sub_canceled2', $stripeIds);
    }

    public function testStripeIdFindsTheRightSubscription(): void
    {
        $this->seedSubscription('sub_findme', 'active');

        $result = Subscription::find()->stripeId('sub_findme')->one();

        $this->assertNotNull($result);
        $this->assertSame('sub_findme', $result->stripeId);
    }

    private function seedSubscription(string $stripeId, string $status): void
    {
        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => $stripeId,
            'status' => $status,
            'description' => "Test subscription $stripeId",
            'items' => [
                'data' => [],
            ],
        ]);

        $this->assertTrue(Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription($stripeSubscription));
    }
}
