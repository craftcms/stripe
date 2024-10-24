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
        $orderBy = $this->getOrderByParam($sort);
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

    /**
     * Returns orderBy param to be used when querying invoices.
     *
     * @param array|null $sort
     * @return array
     */
    private function getOrderByParam(?array $sort): array
    {
        // the default
        $orderBy = ["created" => SORT_DESC];

        // if sort value was passed
        if (!empty($sort)) {
            // get the sortField name;
            $field = $sort[0]['sortField'];

            // if it's set to true, the col name matches the sort field, so get it from ['field']
            if ($field == 'true') {
                $field = $sort[0]['field'];
            }
            // if it starts with 'custom:' it means we have to do some magic first as we don't have a column with that name in the db
            if (str_starts_with($field, 'custom:')) {
                // we'll be doing some casting too
                if (substr_count($field, ':') == 2) {
                    $cast = substr($field, strrpos($field, ':') + 1);
                    $fieldName = substr(str_replace(':' . $cast, '', $field), 7);
                } else {
                    $cast = null;
                    $fieldName = substr($field, 7);
                }

                $field = Craft::$app->getDb()->getQueryBuilder()->jsonExtract('data', [$fieldName]);
                if ($cast) {
                    $field = 'CAST(' . $field . ' AS ' . $cast . ')';
                }
            }

            // figure out sort direction
            if ($sort[0]['direction'] == 'asc') {
                $direction = SORT_ASC;
            } else {
                $direction = SORT_DESC;
            }

            // put it all together
            $orderBy = ["$field" => $direction];
        }

        return $orderBy;
    }
}
