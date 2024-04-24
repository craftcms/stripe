<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use Craft;
use craft\controllers\EditUserTrait;
use craft\db\Query;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\stripe\db\Table;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Invoice;
use craft\stripe\Plugin;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * The CustomersController handles listing and showing Stripe customer data.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CustomersController extends Controller
{
    use EditUserTrait;

    /**
     * Displays the Stripe Customer info for given user.
     *
     * @return Response
     */
    public function actionIndex(?int $userId = null): Response
    {
        $this->requireCpRequest();
        $invoicesService = Plugin::getInstance()->getInvoices();
        $paymentMethodsService = Plugin::getInstance()->getPaymentMethods();

        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, 'stripe');

        $invoices = $invoicesService->getInvoicesByUser($user);
        $paymentMethods = $paymentMethodsService->getPaymentMethodsByUser($user);
        $subscriptions = Cp::elementIndexHtml(Subscription::class, [
            'context' => 'embedded-index',
            'jsSettings' => [
                'criteria' => ['userId' => $user->id],
            ]
        ]);
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($user->email);

        $response->contentTemplate('stripe/customers/_customer', [
            'customers' => $customers,
            'subscriptions' => $subscriptions,
            'invoices' => $invoicesService->getTableData($invoices),
            'paymentMethods' => $paymentMethodsService->getTableData($paymentMethods),
            //'tableDataEndpoint' => UrlHelper::actionUrl('stripe/invoices/table-data', ['userId' => $user->id]),
        ]);

        return $response;
    }
}
