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
use craft\stripe\tests\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Subscription as StripeSubscription;

class StripeCustomerBehaviorTest extends TestCase
{
    private const EMAIL = 'stripe-customer-behavior-test@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Solo edition caps Craft at 1 user, which the test DB's real admin user already uses up.
        // These tests need to create their own users, so raise the in-memory edition for this run.
        Craft::$app->edition = CmsEdition::Pro;
    }

    public function testGetStripeCustomerReturnsMatchByEmail(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(StripeCustomer::constructFrom([
            'id' => 'cus_match',
            'email' => self::EMAIL,
            'created' => 1700000000,
        ]));

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
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(StripeCustomer::constructFrom([
            'id' => 'cus_with_sub',
            'email' => self::EMAIL,
            'created' => 1700000000,
        ]));

        Plugin::getInstance()->getSubscriptions()->createOrUpdateSubscription(StripeSubscription::constructFrom([
            'id' => 'sub_for_customer',
            'status' => 'active',
            'customer' => 'cus_with_sub',
            'items' => ['data' => []],
        ]));

        $user = $this->createUser(self::EMAIL);

        $subscriptions = $user->getStripeSubscriptions();

        $this->assertFalse($subscriptions->isEmpty());
        $this->assertSame('sub_for_customer', $subscriptions->first()->stripeId);
    }

    public function testGetStripePaymentMethodsReturnsMatchByCustomerId(): void
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(StripeCustomer::constructFrom([
            'id' => 'cus_with_pm',
            'email' => self::EMAIL,
            'created' => 1700000000,
        ]));

        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(StripePaymentMethod::constructFrom([
            'id' => 'pm_for_customer',
            'customer' => 'cus_with_pm',
            'type' => 'card',
        ]));

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
