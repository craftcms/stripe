<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Models;

use craft\stripe\models\Customer;
use craft\stripe\models\Invoice;
use craft\stripe\models\PaymentMethod;
use craft\stripe\Plugin;
use craft\stripe\tests\UnitTestCase;

class StripeModelEditUrlTest extends UnitTestCase
{
    public function testCustomerStripeEditUrl(): void
    {
        $customer = new Customer();
        $customer->stripeId = 'cus_123';

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/customers/cus_123',
            $customer->getStripeEditUrl()
        );
    }

    public function testInvoiceStripeEditUrl(): void
    {
        $invoice = new Invoice();
        $invoice->stripeId = 'in_123';

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/invoices/in_123',
            $invoice->getStripeEditUrl()
        );
    }

    public function testPaymentMethodStripeEditUrlUsesCustomerFromData(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setData(['customer' => 'cus_456']);

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/customers/cus_456',
            $paymentMethod->getStripeEditUrl()
        );
    }
}
