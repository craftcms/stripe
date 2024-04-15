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
        $subscriptionsService = Plugin::getInstance()->getSubscriptions();

        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, 'stripe');

        $subscriptions = Subscription::find()->user($user)->all();
        $invoices = $invoicesService->getInvoicesByUser($user);
//        $subscriptions = Cp::elementIndexHtml(Subscription::class, [
//            'criteria' => ['userId' => $user->id],
//            'context' => 'embedded-index',
//        ]);

        $response->contentTemplate('stripe/customers/_customer', [
            'subscriptions' => $subscriptionsService->getTableData($subscriptions, true),
            'invoices' => $invoicesService->getTableData($invoices),
            //'tableDataEndpoint' => UrlHelper::actionUrl('stripe/invoices/table-data', ['userId' => $user->id]),
        ]);

        return $response;
    }
}
