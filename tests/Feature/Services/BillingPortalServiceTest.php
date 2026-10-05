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
use craft\helpers\UrlHelper;
use craft\stripe\events\BillingPortalSessionEvent;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\services\BillingPortal;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use Stripe\BillingPortal\Session as StripeBillingPortalSession;
use Stripe\Service\BillingPortal\BillingPortalServiceFactory;
use Stripe\Service\BillingPortal\SessionService;
use Stripe\StripeClient;
use yii\base\Event;

class BillingPortalServiceTest extends TestCase
{
    private CmsEdition $originalEdition;
    private mixed $beforeStartBillingPortalSessionHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEdition = Craft::$app->edition;
    }

    protected function tearDown(): void
    {
        Craft::$app->edition = $this->originalEdition;

        if ($this->beforeStartBillingPortalSessionHandler !== null) {
            Event::off(
                BillingPortal::class,
                BillingPortal::EVENT_BEFORE_START_BILLING_PORTAL_SESSION,
                $this->beforeStartBillingPortalSessionHandler
            );
        }

        parent::tearDown();
    }

    public function testGetSessionUrlReturnsNullWhenNoUserIsLoggedIn(): void
    {
        $url = Plugin::getInstance()->getBillingPortal()->getSessionUrl('cus_anonymous');

        $this->assertNull($url);
    }

    public function testGetSessionUrlReturnsNullWhenCurrentUserDoesNotOwnCustomer(): void
    {
        $this->syncCustomer('cus_other_customer', 'other-customer@example.com');
        Craft::$app->getUser()->setIdentity($this->createUser('current-user@example.com'));

        $url = Plugin::getInstance()->getBillingPortal()->getSessionUrl('cus_other_customer');

        $this->assertNull($url);
    }

    public function testGetCustomerBillingPortalSessionUrlReturnsNullForMissingStringCustomer(): void
    {
        $this->mockStripeBillingPortalSessionCreate();

        $url = Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl('cus_missing');

        $this->assertNull($url);
    }

    public function testGetCustomerBillingPortalSessionUrlCreatesSessionWithExpectedParams(): void
    {
        $customer = $this->syncCustomer('cus_portal_session', 'portal-session@example.com');
        $receivedParams = null;

        $this->mockStripeBillingPortalSessionCreate(function(array $params) use (&$receivedParams) {
            $receivedParams = $params;

            return StripeBillingPortalSession::constructFrom([
                'id' => 'bps_test',
                'url' => 'https://billing.stripe.test/session',
            ]);
        });

        $url = Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl(
            $customer,
            'bpc_test',
            'account/billing',
            ['flow_data' => ['type' => 'payment_method_update']]
        );

        $this->assertSame('https://billing.stripe.test/session', $url);
        $this->assertSame('cus_portal_session', $receivedParams['customer']);
        $this->assertSame('bpc_test', $receivedParams['configuration']);
        $this->assertSame(UrlHelper::siteUrl('account/billing'), $receivedParams['return_url']);
        $this->assertSame(['type' => 'payment_method_update'], $receivedParams['flow_data']);
    }

    public function testGetCustomerBillingPortalSessionUrlAllowsEventToMutateParams(): void
    {
        $customer = $this->syncCustomer('cus_portal_event', 'portal-event@example.com');

        $this->beforeStartBillingPortalSessionHandler = function(BillingPortalSessionEvent $event) {
            $event->params['locale'] = 'auto';
        };
        Event::on(
            BillingPortal::class,
            BillingPortal::EVENT_BEFORE_START_BILLING_PORTAL_SESSION,
            $this->beforeStartBillingPortalSessionHandler
        );

        $this->mockStripeBillingPortalSessionCreate(function(array $params) {
            $this->assertSame('auto', $params['locale']);

            return StripeBillingPortalSession::constructFrom([
                'id' => 'bps_event',
                'url' => 'https://billing.stripe.test/event-session',
            ]);
        });

        $url = Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl($customer);

        $this->assertSame('https://billing.stripe.test/event-session', $url);
    }

    public function testGetCustomerBillingPortalSessionUrlReturnsEmptyStringWhenStripeFails(): void
    {
        $customer = $this->syncCustomer('cus_portal_failure', 'portal-failure@example.com');

        $this->mockStripeBillingPortalSessionCreate(function() {
            throw new \Exception('Stripe failed');
        });

        $url = Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl($customer);

        $this->assertSame('', $url);
    }

    private function syncCustomer(string $stripeId, string $email): Customer
    {
        Plugin::getInstance()->getCustomers()->createOrUpdateCustomer(
            StripeApiObjectFactory::customer($stripeId, $email)
        );

        return Plugin::getInstance()->getCustomers()->getCustomerByStripeId($stripeId);
    }

    private function createUser(string $email): User
    {
        Craft::$app->edition = CmsEdition::Pro;

        $user = new User();
        $user->username = $email;
        $user->email = $email;

        $this->assertTrue(Craft::$app->getElements()->saveElement($user), json_encode($user->getErrors()));

        return $user;
    }

    private function mockStripeBillingPortalSessionCreate(?callable $create = null): void
    {
        $create ??= fn() => StripeBillingPortalSession::constructFrom([
            'id' => 'bps_default',
            'url' => 'https://billing.stripe.test/default-session',
        ]);

        $sessionService = $this->getMockBuilder(SessionService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $sessionService->method('create')->willReturnCallback($create);

        $billingPortal = $this->getMockBuilder(BillingPortalServiceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getService'])
            ->getMock();
        $billingPortal->method('getService')
            ->with('sessions')
            ->willReturn($sessionService);

        $stripe = $this->getMockBuilder(StripeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getService'])
            ->getMock();
        $stripe->method('getService')
            ->with('billingPortal')
            ->willReturn($billingPortal);

        $api = $this->mockComponent('api', Api::class);
        $api->method('getClient')->willReturn($stripe);
    }
}
