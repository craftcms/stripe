<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\Plugin;
use craft\stripe\services\Api;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;

class PaymentMethodsServiceTest extends TestCase
{
    public function testCreateOrUpdatePaymentMethodCreatesAndUpdatesRecord(): void
    {
        $this->syncPaymentMethod('pm_update', 'cus_a');
        $this->syncPaymentMethod('pm_update', 'cus_b');

        $paymentMethod = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_update');

        $this->assertNotNull($paymentMethod);
        $this->assertSame('cus_b', $paymentMethod->data['customer']);
    }

    public function testGetPaymentMethodsByCustomerIdReturnsMatches(): void
    {
        $this->syncPaymentMethod('pm_for_customer', 'cus_target');
        $this->syncPaymentMethod('pm_other_customer', 'cus_other');

        $paymentMethods = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodsByCustomerId('cus_target');

        $this->assertCount(1, $paymentMethods);
        $this->assertSame('pm_for_customer', $paymentMethods[0]->stripeId);
    }

    public function testDeletePaymentMethodByStripeIdRemovesRecord(): void
    {
        $this->syncPaymentMethod('pm_delete', 'cus_a');
        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete'));

        Plugin::getInstance()->getPaymentMethods()->deletePaymentMethodByStripeId('pm_delete');

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete'));
    }

    public function testDeletePaymentMethodsByCustomerIdRemovesRecord(): void
    {
        $this->syncPaymentMethod('pm_delete_by_customer', 'cus_to_delete');

        Plugin::getInstance()->getPaymentMethods()->deletePaymentMethodsByCustomerId('cus_to_delete');

        $this->assertNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_delete_by_customer'));
    }

    public function testSyncAllPaymentMethodsCreatesFromMockedApi(): void
    {
        $api = $this->mockComponent('api', Api::class);
        $api->method('fetchAllPaymentMethods')->willReturn([
            StripeApiObjectFactory::paymentMethod('pm_from_stripe', 'cus_from_stripe'),
        ]);

        $count = Plugin::getInstance()->getPaymentMethods()->syncAllPaymentMethods();

        $this->assertSame(1, $count);
        $this->assertNotNull(Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_from_stripe'));
    }

    public function testGetTableDataFormatsPaymentMethods(): void
    {
        $this->syncPaymentMethod('pm_table', 'cus_table', ['card' => ['last4' => '4242']]);

        $paymentMethod = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById('pm_table');
        $tableData = Plugin::getInstance()->getPaymentMethods()->getTableData([$paymentMethod]);

        $this->assertCount(1, $tableData);
        $this->assertSame('pm_table', $tableData[0]['title']);
        $this->assertSame('card', $tableData[0]['type']);
        $this->assertSame('4242', $tableData[0]['last4']);
        $this->assertSame($paymentMethod->getStripeEditUrl(), $tableData[0]['url']);
    }

    private function syncPaymentMethod(string $stripeId, string $customerId, array $overrides = []): void
    {
        Plugin::getInstance()->getPaymentMethods()->createOrUpdatePaymentMethod(
            StripeApiObjectFactory::paymentMethod($stripeId, $customerId, 'card', $overrides)
        );
    }
}
