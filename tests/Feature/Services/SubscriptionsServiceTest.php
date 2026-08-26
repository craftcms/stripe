<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use Craft;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\services\Subscriptions;
use craft\stripe\tests\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\Subscription as StripeSubscription;

class SubscriptionsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);
        Plugin::getInstance()->getSettings()->createUserIfMissing = false;

        parent::tearDown();
    }

    public function testCreateOrUpdateSubscriptionCreatesNewElement(): void
    {
        $this->assertTrue($this->syncSubscription('sub_new', 'active'));

        $subscription = Subscription::find()->stripeId('sub_new')->one();

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->stripeStatus);
    }

    public function testCreateOrUpdateSubscriptionUpdatesExistingElementInsteadOfDuplicating(): void
    {
        $this->syncSubscription('sub_update', 'active');
        $this->syncSubscription('sub_update', 'canceled');

        $subscriptions = Subscription::find()->stripeId('sub_update')->status(null)->all();

        $this->assertCount(1, $subscriptions);
        $this->assertSame('canceled', $subscriptions[0]->stripeStatus);
    }

    public function testDeleteSubscriptionByStripeIdRemovesElement(): void
    {
        $this->syncSubscription('sub_delete', 'active');
        $this->assertNotNull(Subscription::find()->stripeId('sub_delete')->status(null)->one());

        Plugin::getInstance()->getSubscriptions()->deleteSubscriptionByStripeId('sub_delete');

        $this->assertNull(Subscription::find()->stripeId('sub_delete')->status(null)->one());
    }

    public function testEnsureUserCreatesUserWhenCreateUserIfMissingIsEnabled(): void
    {
        Craft::$app->edition = CmsEdition::Pro;
        Plugin::getInstance()->getSettings()->createUserIfMissing = true;

        // `Subscription::getCustomer()` is never populated during a plain sync, so `ensureUser()`
        // always falls back to `Api::fetchCustomerById()` — mock that instead of pre-seeding a Customer.
        $api = $this->createMock(Api::class);
        $api->method('fetchCustomerById')->willReturn(StripeCustomer::constructFrom([
            'id' => 'cus_ensure_user',
            'email' => 'ensure-user-test@example.com',
            'created' => 1700000000,
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->syncSubscription('sub_ensure_user', 'active', 'cus_ensure_user');

        $user = User::find()->email('ensure-user-test@example.com')->status(null)->one();
        $this->assertNotNull($user);
    }

    public function testEnsureUserDoesNothingWhenSettingDisabled(): void
    {
        Plugin::getInstance()->getSettings()->createUserIfMissing = false;

        $api = $this->createMock(Api::class);
        $api->method('fetchCustomerById')->willReturn(StripeCustomer::constructFrom([
            'id' => 'cus_no_ensure_user',
            'email' => 'no-ensure-user-test@example.com',
            'created' => 1700000000,
        ]));
        Plugin::getInstance()->set('api', $api);

        $this->syncSubscription('sub_no_ensure_user', 'active', 'cus_no_ensure_user');

        $user = User::find()->email('no-ensure-user-test@example.com')->status(null)->one();
        $this->assertNull($user);
    }

    public function testSyncAllSubscriptionsCreatesAndRemovesOrphans(): void
    {
        $this->syncSubscription('sub_orphan', 'active');

        $api = $this->createMock(Api::class);
        $api->method('prepExpandForFetchAll')->willReturn([]);
        $api->method('fetchAllIterator')->willReturn((function() {
            yield [
                StripeSubscription::constructFrom([
                    'id' => 'sub_from_stripe',
                    'status' => 'active',
                    'items' => ['data' => []],
                ]),
            ];
        })());
        Plugin::getInstance()->set('api', $api);

        Plugin::getInstance()->getSubscriptions()->syncAllSubscriptions();

        $this->assertNotNull(Subscription::find()->stripeId('sub_from_stripe')->one());
        $this->assertNull(Subscription::find()->stripeId('sub_orphan')->status(null)->one());
    }

    private function syncSubscription(string $stripeId, string $status, ?string $customerId = null): bool
    {
        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => $stripeId,
            'status' => $status,
            'customer' => $customerId,
            'items' => ['data' => []],
        ]);

        return Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription($stripeSubscription);
    }
}
