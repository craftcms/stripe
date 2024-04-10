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
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\stripe\db\Table;
use craft\stripe\models\Invoice;
use craft\stripe\Plugin;
use craft\web\Controller;
use yii\base\InvalidArgumentException;
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
    private Plugin $_plugin;

    public function init(): void
    {
        parent::init();

        $this->_plugin = Plugin::getInstance();
    }

    /**
     * Displays the invoices index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $dashboardUrl = $this->_plugin->dashboardUrl;
        $mode = $this->_plugin->stripeMode;

        $newInvoiceUrl = "{$dashboardUrl}/{$mode}/invoices/create";

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
        $offset = ($page - 1) * $limit;

        $sqlQuery = (new Query())
            ->select([
                'stripeId',
                'data',
            ])
            ->from([Table::INVOICEDATA]);


        if ($search) {
            $searchTerm = '%' . str_replace(' ', '%', $search) . '%';

            if (Craft::$app->getDb()->getIsPgsql()) {
                // TODO: TEST ME!!!!
                $whereClause = [
                    'or',
                    // Search invoice number
                    ['ILIKE', 'data::number', $searchTerm],
                    // Search customer email
                    ['ILIKE', 'data::customer_email', $searchTerm],
                ];
            } else {
                $whereClause = [
                    'or',
                    // Search invoice number
                    new Expression("JSON_SEARCH(`data`, 'all', '$searchTerm', NULL, '$.number')"),
                    // Search customer email
                    new Expression("JSON_SEARCH(`data`, 'all', '$searchTerm', NULL, '$.customer_email')"),
                ];
            }

            $sqlQuery
                ->andWhere($whereClause);
        }

        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);

        $result = $sqlQuery->all();

        $tableData = [];
        $formatter = Craft::$app->getFormatter();
        foreach ($result as $item) {
            $invoice = new Invoice();
            $invoice->setAttributes($item, false);

            $tableData[] = [
                'id' => $invoice->stripeId,
                'title' => Craft::t('site', $invoice->data['number']),
                'amount' => $formatter->asCurrency($invoice->data['total'] / 100, $invoice->data['currency']),
                'frequency' => '',
                'customerEmail' => $invoice->data['customer_email'],
                'due' => $invoice->data['due_date'] ? $formatter->asDatetime($invoice->data['due_date'], 'php:Y-m-d') : '',
                'created' => $formatter->asDatetime($invoice->data['created'], $formatter::FORMAT_WIDTH_SHORT),
                'url' => $invoice->getStripeEditUrl(),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }
}
