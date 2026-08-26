<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Elements;

use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\stripe\tests\TestCase;
use ReflectionMethod;
use Stripe\Subscription as StripeSubscription;

class SubscriptionActionMenuItemsTest extends TestCase
{
    public function testSafeActionMenuItemsIncludesResumeWhenCancelAtPeriodEndIsTrue(): void
    {
        $subscription = $this->seedSubscription('sub_resume', 'active', true);

        $items = $this->invokeSafeActionMenuItems($subscription);

        $this->assertTrue($this->menuItemsContainAction($items, 'stripe/subscriptions/resume'));
    }

    public function testSafeActionMenuItemsExcludesResumeWhenCancelAtPeriodEndIsFalse(): void
    {
        $subscription = $this->seedSubscription('sub_no_resume', 'active', false);

        $items = $this->invokeSafeActionMenuItems($subscription);

        $this->assertFalse($this->menuItemsContainAction($items, 'stripe/subscriptions/resume'));
    }

    public function testDestructiveActionMenuItemsIncludesCancelOptionsWhenNotCanceled(): void
    {
        $subscription = $this->seedSubscription('sub_cancelable', 'active', false);

        $items = $this->invokeDestructiveActionMenuItems($subscription);

        $cancelItems = array_filter($items, fn($item) => ($item['action'] ?? null) === 'stripe/subscriptions/cancel');
        $this->assertCount(2, $cancelItems);
    }

    public function testDestructiveActionMenuItemsExcludesCancelOptionsWhenAlreadyCanceled(): void
    {
        $subscription = $this->seedSubscription('sub_already_canceled', 'canceled', false);

        $items = $this->invokeDestructiveActionMenuItems($subscription);

        $this->assertFalse($this->menuItemsContainAction($items, 'stripe/subscriptions/cancel'));
    }

    private function seedSubscription(string $stripeId, string $status, bool $cancelAtPeriodEnd): Subscription
    {
        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => $stripeId,
            'status' => $status,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'items' => ['data' => []],
        ]);

        $this->assertTrue(Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription($stripeSubscription));

        return Subscription::find()->stripeId($stripeId)->one();
    }

    private function invokeSafeActionMenuItems(Subscription $subscription): array
    {
        $method = new ReflectionMethod(Subscription::class, 'safeActionMenuItems');
        $method->setAccessible(true);

        return $method->invoke($subscription);
    }

    private function invokeDestructiveActionMenuItems(Subscription $subscription): array
    {
        $method = new ReflectionMethod(Subscription::class, 'destructiveActionMenuItems');
        $method->setAccessible(true);

        return $method->invoke($subscription);
    }

    private function menuItemsContainAction(array $items, string $action): bool
    {
        foreach ($items as $item) {
            if (($item['action'] ?? null) === $action) {
                return true;
            }
        }

        return false;
    }
}
