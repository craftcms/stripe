<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Controllers;

use Craft;
use craft\stripe\controllers\InvoicesController;
use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use yii\web\ForbiddenHttpException;

class InvoicesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->syncInvoice('in_oldest', [
            'number' => 'INV-001',
            'customer_email' => 'alice@example.com',
            'status' => 'paid',
            'total' => 1000,
            'created' => 1700000000,
        ]);
        $this->syncInvoice('in_middle', [
            'number' => 'INV-002',
            'customer_email' => 'bob@example.com',
            'status' => 'open',
            'total' => 3000,
            'created' => 1700000100,
        ]);
        $this->syncInvoice('in_newest', [
            'number' => 'INV-003',
            'customer_email' => 'carol@example.com',
            'status' => 'draft',
            'total' => 2000,
            'created' => 1700000200,
        ]);
    }

    public function testTableDataRequiresPermission(): void
    {
        Craft::$app->getUser()->setIdentity(null);
        $this->expectException(ForbiddenHttpException::class);

        $this->runTableData([]);
    }

    public function testTableDataDefaultsToCreatedDescending(): void
    {
        $data = $this->runTableData(['per_page' => 1000]);

        $this->assertSame(['in_newest', 'in_middle', 'in_oldest'], $this->ownIds($data));
    }

    public function testTableDataSearchMatchesByNumber(): void
    {
        $data = $this->runTableData(['search' => 'INV-002']);

        $this->assertSame(['in_middle'], $this->ownIds($data));
    }

    public function testTableDataSearchMatchesByCustomerEmail(): void
    {
        $data = $this->runTableData(['search' => 'carol@example.com']);

        $this->assertSame(['in_newest'], $this->ownIds($data));
    }

    public function testTableDataSortsByPlainColumnAscending(): void
    {
        $data = $this->runTableData([
            'per_page' => 1000,
            'sort' => [['sortField' => true, 'field' => 'number', 'direction' => 'asc']],
        ]);

        $this->assertSame(['in_oldest', 'in_middle', 'in_newest'], $this->ownIds($data));
    }

    public function testTableDataSortsByCustomJsonFieldWithoutCast(): void
    {
        $data = $this->runTableData([
            'per_page' => 1000,
            'sort' => [['sortField' => 'custom:status', 'direction' => 'asc']],
        ]);

        // alphabetical: draft, open, paid
        $this->assertSame(['in_newest', 'in_middle', 'in_oldest'], $this->ownIds($data));
    }

    public function testTableDataSortsByCustomJsonFieldWithCast(): void
    {
        $data = $this->runTableData([
            'per_page' => 1000,
            'sort' => [['sortField' => 'custom:total:float', 'direction' => 'desc']],
        ]);

        // numeric descending: 3000, 2000, 1000 — would sort wrong alphabetically without the cast
        $this->assertSame(['in_middle', 'in_newest', 'in_oldest'], $this->ownIds($data));
    }

    public function testTableDataRespectsPagination(): void
    {
        // Craft::$app->getResponse() is a shared singleton, so each response's ->data must be
        // copied out immediately — holding onto two Response objects across calls would leave
        // both pointing at the same (last-written) data.
        $firstPageData = $this->runResponse(['page' => 1, 'per_page' => 1])->data;
        $secondPageData = $this->runResponse(['page' => 2, 'per_page' => 1])->data;

        $this->assertCount(1, $firstPageData['data']);
        $this->assertCount(1, $secondPageData['data']);
        $this->assertNotSame($firstPageData['data'][0]['id'], $secondPageData['data'][0]['id']);
        $this->assertSame(1, $firstPageData['pagination']['current_page']);
        $this->assertSame(2, $secondPageData['pagination']['current_page']);
        $this->assertGreaterThanOrEqual(3, $firstPageData['pagination']['total']);
    }

    private function runTableData(array $params): array
    {
        return $this->runResponse($params)->data['data'];
    }

    private function runResponse(array $params)
    {
        Craft::$app->getRequest()->setQueryParams($params);
        Craft::$app->getRequest()->setAcceptableContentTypes(['application/json' => ['q' => 1]]);

        $controller = new InvoicesController('invoices', Craft::$app);

        return $controller->runAction('table-data');
    }

    private function ids(array $tableData): array
    {
        return array_column($tableData, 'id');
    }

    /**
     * The invoices table isn't isolated per-test (no ID filter on the controller action), so a
     * shared dev DB can have unrelated pre-existing rows. Filter the (order-preserving) response
     * down to just this test's own seeded IDs before asserting relative order.
     */
    private function ownIds(array $tableData): array
    {
        $ownIds = ['in_oldest', 'in_middle', 'in_newest'];

        return array_values(array_filter(
            $this->ids($tableData),
            fn(string $id) => in_array($id, $ownIds, true)
        ));
    }

    private function syncInvoice(string $stripeId, array $overrides): void
    {
        Plugin::getInstance()->getInvoices()->createOrUpdateInvoice(
            StripeApiObjectFactory::invoice($stripeId, $overrides)
        );
    }
}
