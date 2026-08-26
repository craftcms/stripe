<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\tests\TestCase;
use Stripe\Customer as StripeCustomer;

class CustomersServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);

        parent::tearDown();
    }

    public function testCreateOrUpdateCustomerCreatesAndUpdatesRecord(): void
    {
        $this->syncCustomer('cus_update', 'first@example.com');
        $this->syncCustomer('cus_update', 'second@example.com');

        $customer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_update');

        $this->assertNotNull($customer);
        $this->assertSame('second@example.com', $customer->email);
    }

    public function testGetCustomersByEmailReturnsMatches(): void
    {
        $this->syncCustomer('cus_by_email', 'find-me@example.com');

        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail('find-me@example.com');

        $this->assertArrayHasKey('cus_by_email', $customers);
    }

    public function testGetCustomerByStripeIdReturnsMatch(): void
    {
        $this->syncCustomer('cus_by_id', 'by-id@example.com');

        $customer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_by_id');

        $this->assertNotNull($customer);
        $this->assertSame('by-id@example.com', $customer->email);
    }

    public function testDeleteCustomerDataByStripeIdRemovesRecord(): void
    {
        $this->syncCustomer('cus_delete', 'delete-me@example.com');
        $this->assertNotNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_delete'));

        Plugin::getInstance()->getCustomers()->deleteCustomerDataByStripeId('cus_delete');

        $this->assertNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_delete'));
    }

    public function testSyncAllCustomersCreatesFromMockedApi(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchAllCustomers')->willReturn([
            StripeCustomer::constructFrom([
                'id' => 'cus_from_stripe',
                'email' => 'from-stripe@example.com',
                'created' => 1700000000,
            ]),
        ]);
        Plugin::getInstance()->set('api', $api);

        $count = Plugin::getInstance()->getCustomers()->syncAllCustomers();

        $this->assertSame(1, $count);
        $this->assertNotNull(Plugin::getInstance()->getCustomers()->getCustomerByStripeId('cus_from_stripe'));
    }

    private function syncCustomer(string $stripeId, string $email): bool
    {
        $stripeCustomer = StripeCustomer::constructFrom([
            'id' => $stripeId,
            'email' => $email,
            'created' => 1700000000,
        ]);

        return Plugin::getInstance()->getCustomers()->createOrUpdateCustomer($stripeCustomer);
    }
}
