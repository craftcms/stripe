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
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;

class SubscriptionsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->getSettings()->createUserIfMissing = false;

        parent::tearDown();
    }

    public function testCreateOrUpdateSubscriptionCreatesNewElement(): void
    {
        $this->assertTrue($this->syncSubscription('sub_new', ['status' => 'active']));

        $subscription = Subscription::find()->stripeId('sub_new')->one();

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->stripeStatus);
    }

    public function testCreateOrUpdateSubscriptionUpdatesExistingElementInsteadOfDuplicating(): void
    {
        $this->syncSubscription('sub_update', ['status' => 'active']);
        $this->syncSubscription('sub_update', ['status' => 'canceled']);

        $subscriptions = Subscription::find()->stripeId('sub_update')->status(null)->all();

        $this->assertCount(1, $subscriptions);
        $this->assertSame('canceled', $subscriptions[0]->stripeStatus);
    }

    public function testDeleteSubscriptionByStripeIdRemovesElement(): void
    {
        $this->syncSubscription('sub_delete');
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
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchCustomerById')->willReturn(
            StripeApiObjectFactory::customer('cus_ensure_user', 'ensure-user-test@example.com')
        );

        $this->syncSubscription('sub_ensure_user', ['customer' => 'cus_ensure_user']);

        $user = User::find()->email('ensure-user-test@example.com')->status(null)->one();
        $this->assertNotNull($user);
    }

    public function testEnsureUserDoesNothingWhenSettingDisabled(): void
    {
        Plugin::getInstance()->getSettings()->createUserIfMissing = false;

        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchCustomerById')->willReturn(
            StripeApiObjectFactory::customer('cus_no_ensure_user', 'no-ensure-user-test@example.com')
        );

        $this->syncSubscription('sub_no_ensure_user', ['customer' => 'cus_no_ensure_user']);

        $user = User::find()->email('no-ensure-user-test@example.com')->status(null)->one();
        $this->assertNull($user);
    }

    public function testSyncAllSubscriptionsCreatesAndRemovesOrphans(): void
    {
        $this->syncSubscription('sub_orphan');

        $api = $this->mockComponent('api', Api::class);
        $api->method('prepExpandForFetchAll')->willReturn([]);
        $api->method('fetchAllIterator')->willReturn((function() {
            yield [StripeApiObjectFactory::subscription('sub_from_stripe')];
        })());

        Plugin::getInstance()->getSubscriptions()->syncAllSubscriptions();

        $this->assertNotNull(Subscription::find()->stripeId('sub_from_stripe')->one());
        $this->assertNull(Subscription::find()->stripeId('sub_orphan')->status(null)->one());
    }

    private function syncSubscription(string $stripeId, array $overrides = []): bool
    {
        return Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(
            StripeApiObjectFactory::subscription($stripeId, $overrides)
        );
    }
}
