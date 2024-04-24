<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\AdminTable;
use craft\helpers\UrlHelper;
use craft\stripe\db\Table;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * The InvoicesController handles listing and showing Stripe invoices.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class InvoicesController extends Controller
{
    /**
     * Displays the invoices index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $newInvoiceUrl = Plugin::getInstance()->stripeBaseUrl . "/invoices/create";

        return $this->renderTemplate('stripe/invoices/_index', [
            'newInvoiceUrl' => $newInvoiceUrl,
            'tableDataEndpoint' => UrlHelper::actionUrl('stripe/invoices/table-data'),
        ]);
    }

    /**
     * Returns invoices data for the Vue Admin Table
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 100);
        $search = $this->request->getParam('search');
        $sort = $this->request->getParam('sort');
        $offset = ($page - 1) * $limit;

        $sqlQuery = (new Query())
            ->select([
                'stripeId',
                'data',
            ])
            ->from([Table::INVOICEDATA]);

        $qb = Craft::$app->getDb()->getQueryBuilder();

        // searching
        if ($search) {
            $sqlQuery->andWhere([
                'or',
                ['like', "stripe_invoicedata.number", $search],
                ['like', "stripe_invoicedata.customer_email", $search],
            ]);
        }

        // ordering
        $orderBy = ["stripe_invoicedata.created" => SORT_DESC];
        if (!empty($sort)) {
            $sort = $sort[0];
            $field = substr($sort['sortField'], 0, strpos($sort['sortField'], '|'));
            $orderBy = ["stripe_invoicedata.$field" => SORT_DESC];
        }
        $sqlQuery->orderBy($orderBy);


        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);

        $invoicesService = Plugin::getInstance()->getInvoices();
        $invoices = $invoicesService->populateInvoices($sqlQuery->all());
        $tableData = $invoicesService->getTableData($invoices);

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }
}
