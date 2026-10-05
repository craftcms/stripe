<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Behaviors;

use Craft;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;

class StripeCustomerBehaviorTest extends TestCase
{
    private const EMAIL = 'stripe-customer-behavior-test@example.com';

    private CmsEdition $originalEdition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEdition = Craft::$app->edition;

        // Solo edition caps Craft at 1 user, which the test DB's real admin user already uses up.
        // These tests need to create their own users, so raise the in-memory edition for this run.
        Craft::$app->edition = CmsEdition::Pro;
    }

    protected function tearDown(): void
    {
        Craft::$app->edition = $this->originalEdition;

        parent::tearDown();
    }

    public function testGetStripeCustomerReturnsMatchByEmail(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(
            StripeApiObjectFactory::customer('cus_match', self::EMAIL)
        );

        $user = $this->createUser(self::EMAIL);

        $customer = $user->getStripeCustomer();

        $this->assertNotNull($customer);
        $this->assertSame('cus_match', $customer->stripeId);
    }

    public function testGetStripeCustomerReturnsNullWhenNoMatch(): void
    {
        $user = $this->createUser('no-stripe-customer@example.com');

        $this->assertNull($user->getStripeCustomer());
        $this->assertTrue($user->getStripeSubscriptions()->isEmpty());
        $this->assertTrue($user->getStripePaymentMethods()->isEmpty());
    }

    public function testGetStripeSubscriptionsReturnsMatchByUserEmail(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(
            StripeApiObjectFactory::customer('cus_with_sub', self::EMAIL)
        );

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(
            StripeApiObjectFactory::subscription('sub_for_customer', ['customer' => 'cus_with_sub'])
        );

        $user = $this->createUser(self::EMAIL);

        $subscriptions = $user->getStripeSubscriptions();

        $this->assertFalse($subscriptions->isEmpty());
        $this->assertSame('sub_for_customer', $subscriptions->first()->stripeId);
    }

    public function testGetStripePaymentMethodsReturnsMatchByCustomerId(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(
            StripeApiObjectFactory::customer('cus_with_pm', self::EMAIL)
        );

        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(
            StripeApiObjectFactory::paymentMethod('pm_for_customer', 'cus_with_pm')
        );

        $user = $this->createUser(self::EMAIL);

        $paymentMethods = $user->getStripePaymentMethods();

        $this->assertFalse($paymentMethods->isEmpty());
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->username = $email;
        $user->email = $email;
        $user->attachBehavior('stripeCustomer', StripeCustomerBehavior::class);

        $this->assertTrue(Craft::$app->getElements()->saveElement($user), json_encode($user->getErrors()));

        return $user;
    }
}
