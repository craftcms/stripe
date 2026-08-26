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
use Stripe\PaymentMethod as StripePaymentMethod;

class PaymentMethodsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);

        parent::tearDown();
    }

    public function testCreateOrUpdatePaymentMethodCreatesAndUpdatesRecord(): void
    {
        $this->syncPaymentMethod('pm_update', 'cus_a', 'card');
        $this->syncPaymentMethod('pm_update', 'cus_b', 'card');

        $paymentMethod = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_update');

        $this->assertNotNull($paymentMethod);
        $this->assertSame('cus_b', $paymentMethod->data['customer']);
    }

    public function testGetPaymentMethodsByCustomerIdReturnsMatches(): void
    {
        $this->syncPaymentMethod('pm_for_customer', 'cus_target', 'card');
        $this->syncPaymentMethod('pm_other_customer', 'cus_other', 'card');

        $paymentMethods = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodsByCustomerId('cus_target');

        $this->assertCount(1, $paymentMethods);
        $this->assertSame('pm_for_customer', $paymentMethods[0]->stripeId);
    }

    public function testDeletePaymentMethodByStripeIdRemovesRecord(): void
    {
        $this->syncPaymentMethod('pm_delete', 'cus_a', 'card');
        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete'));

        Plugin::getInstance()->getPaymentMethods()->deletePaymentMethodByStripeId('pm_delete');

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete'));
    }

    public function testDeletePaymentMethodsByCustomerIdRemovesRecord(): void
    {
        $this->syncPaymentMethod('pm_delete_by_customer', 'cus_to_delete', 'card');

        Plugin::getInstance()->getPaymentMethods()->deletePaymentMethodsByCustomerId('cus_to_delete');

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete_by_customer'));
    }

    public function testSyncAllPaymentMethodsCreatesFromMockedApi(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchAllPaymentMethods')->willReturn([
            StripePaymentMethod::constructFrom([
                'id' => 'pm_from_stripe',
                'customer' => 'cus_from_stripe',
                'type' => 'card',
            ]),
        ]);
        Plugin::getInstance()->set('api', $api);

        $count = Plugin::getInstance()->getPaymentMethods()->syncAllPaymentMethods();

        $this->assertSame(1, $count);
        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_from_stripe'));
    }

    public function testGetTableDataFormatsPaymentMethods(): void
    {
        $this->syncPaymentMethod('pm_table', 'cus_table', 'card', ['last4' => '4242']);

        $paymentMethod = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_table');
        $tableData = Plugin::getInstance()->getPaymentMethods()->getTableData([$paymentMethod]);

        $this->assertCount(1, $tableData);
        $this->assertSame('pm_table', $tableData[0]['title']);
        $this->assertSame('card', $tableData[0]['type']);
        $this->assertSame('4242', $tableData[0]['last4']);
        $this->assertSame($paymentMethod->getStripeEditUrl(), $tableData[0]['url']);
    }

    private function syncPaymentMethod(string $stripeId, string $customerId, string $type, array $typeData = []): void
    {
        $stripePaymentMethod = StripePaymentMethod::constructFrom(array_merge([
            'id' => $stripeId,
            'customer' => $customerId,
            'type' => $type,
            'created' => 1700000000,
            $type => $typeData,
        ]));

        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod($stripePaymentMethod);
    }
}
