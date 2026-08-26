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
use Stripe\Invoice as StripeInvoice;

class InvoicesServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Plugin::getInstance()->set('api', Api::class);

        parent::tearDown();
    }

    public function testCreateOrUpdateInvoiceCreatesAndUpdatesRecord(): void
    {
        $this->syncInvoice('in_update', ['status' => 'draft']);
        $this->syncInvoice('in_update', ['status' => 'paid']);

        $invoice = Plugin::getInstance()->getInvoices()->getInvoiceById('in_update');

        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->data['status']);
    }

    public function testGetInvoiceByIdReturnsMatch(): void
    {
        $this->syncInvoice('in_findme');

        $invoice = Plugin::getInstance()->getInvoices()->getInvoiceById('in_findme');

        $this->assertNotNull($invoice);
        $this->assertSame('in_findme', $invoice->stripeId);
    }

    public function testDeleteInvoiceByStripeIdRemovesRecord(): void
    {
        $this->syncInvoice('in_delete');
        $this->assertNotNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_delete'));

        Plugin::getInstance()->getInvoices()->deleteInvoiceByStripeId('in_delete');

        $this->assertNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_delete'));
    }

    public function testSyncAllInvoicesCreatesFromMockedApi(): void
    {
        $api = $this->createMock(Api::class);
        $api->method('fetchAllInvoices')->willReturn([
            StripeInvoice::constructFrom($this->invoiceData('in_from_stripe')),
        ]);
        Plugin::getInstance()->set('api', $api);

        $count = Plugin::getInstance()->getInvoices()->syncAllInvoices();

        $this->assertSame(1, $count);
        $this->assertNotNull(Plugin::getInstance()->getInvoices()->getInvoiceById('in_from_stripe'));
    }

    public function testGetTableDataFormatsInvoiceWithNumber(): void
    {
        $this->syncInvoice('in_table', [
            'number' => 'INV-001',
            'currency' => 'usd',
            'total' => 1000,
            'due_date' => 1700000000,
        ]);

        $invoice = Plugin::getInstance()->getInvoices()->getInvoiceById('in_table');
        $tableData = Plugin::getInstance()->getInvoices()->getTableData([$invoice]);

        $this->assertCount(1, $tableData);
        $this->assertSame('INV-001', $tableData[0]['title']);
        $this->assertSame('customer@example.com', $tableData[0]['customerEmail']);
        $this->assertSame($invoice->getStripeEditUrl(), $tableData[0]['url']);
        $this->assertNotEmpty($tableData[0]['due']);
    }

    public function testGetTableDataFallsBackToDraftTitleWhenNumberMissing(): void
    {
        $this->syncInvoice('in_draft', ['number' => null, 'due_date' => null]);

        $invoice = Plugin::getInstance()->getInvoices()->getInvoiceById('in_draft');
        $tableData = Plugin::getInstance()->getInvoices()->getTableData([$invoice]);

        $this->assertSame('Draft', $tableData[0]['title']);
        $this->assertSame('', $tableData[0]['due']);
    }

    public function testGetTableDataDoesNotDivideZeroDecimalCurrencyAmount(): void
    {
        $this->syncInvoice('in_jpy', ['currency' => 'jpy', 'total' => 1000]);

        $invoice = Plugin::getInstance()->getInvoices()->getInvoiceById('in_jpy');
        $tableData = Plugin::getInstance()->getInvoices()->getTableData([$invoice]);

        // asCurrency(1000, 'jpy') should reflect the undivided amount (1,000), not 1000/100 = 10.
        $this->assertStringContainsString('1,000', $tableData[0]['amount']);
        $this->assertStringNotContainsString('10.00', $tableData[0]['amount']);
    }

    private function syncInvoice(string $stripeId, array $overrides = []): void
    {
        $stripeInvoice = StripeInvoice::constructFrom(array_merge($this->invoiceData($stripeId), $overrides));

        Plugin::getInstance()->getInvoices()->createOrUpdateInvoice($stripeInvoice);
    }

    private function invoiceData(string $stripeId): array
    {
        return [
            'id' => $stripeId,
            'number' => 'INV-' . $stripeId,
            'status' => 'open',
            'currency' => 'usd',
            'total' => 1000,
            'customer_email' => 'customer@example.com',
            'due_date' => null,
            'created' => 1700000000,
        ];
    }
}
