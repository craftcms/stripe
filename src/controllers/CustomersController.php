<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\controllers;

use craft\controllers\EditUserTrait;
use craft\elements\User;
use craft\helpers\Cp;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
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
     * @param int|null $userId
     * @return Response
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIndex(?int $userId = null): Response
    {
        $this->requireCpRequest();
        $invoicesService = Plugin::getInstance()->getInvoices();
        $paymentMethodsService = Plugin::getInstance()->getPaymentMethods();

        /** @var User|StripeCustomerBehavior $user */
        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, 'stripe');

        $invoices = $invoicesService->getInvoicesByUser($user);
        $paymentMethods = $user->getStripePaymentMethods()->all();
        $subscriptions = Cp::elementIndexHtml(Subscription::class, [
            'context' => 'embedded-index',
            'jsSettings' => [
                'criteria' => ['userId' => $user->id],
            ],
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
