<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\PaymentMethod;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Support\InvoiceTable;
use CraftCms\Stripe\Support\StripeLink;

use function CraftCms\Cms\t;

/**
 * The Stripe screen of the Edit User page: the user’s customers, subscriptions, invoices and payment methods.
 *
 * @since 3.0.0
 */
readonly class CustomersController
{
    use EditUserTrait;

    public const string SCREEN = 'stripe';

    public function __construct(
        private Customers $customers,
        private Invoices $invoices,
        private PaymentMethods $paymentMethods,
    ) {}

    public function index(?int $userId = null): CpScreenResponse
    {
        $user = $this->editedUser($userId);
        $response = $this->asEditUserScreen($user, self::SCREEN);

        $customers = $this->customers->getCustomersByEmail($user->email);
        $formatter = I18N::getFormatter();

        $content = Html::tag('h2', t('Customers', category: 'stripe'));
        $content .= $customers === []
            ? Html::tag('p', t('No Stripe customers have this user’s email.', category: 'stripe'), ['class' => 'zilch'])
            : Html::ul(array_map(fn (Customer $customer) => StripeLink::html(
                $customer->getStripeEditUrl(),
                (($customer->getData()['name'] ?? '') ?: $customer->email)." ($customer->stripeId)",
            ), array_values($customers)), encode: false);

        $content .= Html::tag('hr').Html::tag('h2', t('Subscriptions', category: 'stripe'));
        $content .= $this->table([
            ['key' => 'title', 'label' => t('Subscription', category: 'stripe')],
            ['key' => 'status', 'label' => t('Stripe Status', category: 'stripe')],
            ['key' => 'products', 'label' => t('Products', category: 'stripe')],
            ['key' => 'currentPeriodEnd', 'label' => t('Current period end', category: 'stripe')],
        ], Subscription::find()->userId($user->id)->status(null)->collect()->map(fn (Subscription $subscription) => [
            'title' => ['label' => $subscription->title, 'url' => $subscription->getCpEditUrl(), 'internal' => true],
            'status' => $subscription->stripeStatus,
            'products' => implode(', ', array_map(fn (Product $product) => $product->title, $subscription->getProducts() ?? [])),
            'currentPeriodEnd' => isset($subscription->getData()['current_period_end']) ? $formatter->asDate($subscription->getData()['current_period_end'], 'short') : '',
        ])->all(), t('No subscriptions exist yet.', category: 'stripe'));

        $content .= Html::tag('hr').Html::tag('h2', t('Invoices', category: 'stripe'));
        $content .= $this->table(InvoiceTable::columns(), InvoiceTable::rows($this->invoices->getInvoicesByUser($user)), t('No invoices exist yet.', category: 'stripe'));

        $content .= Html::tag('hr').Html::tag('h2', t('Payment Methods', category: 'stripe'));
        $content .= $this->table([
            ['key' => 'id', 'label' => t('Stripe ID', category: 'stripe')],
            ['key' => 'type', 'label' => t('Type', category: 'stripe')],
            ['key' => 'last4', 'label' => t('Last 4', category: 'stripe')],
            ['key' => 'created', 'label' => t('Created', category: 'stripe')],
        ], array_map(function (PaymentMethod $paymentMethod) use ($formatter) {
            $data = $paymentMethod->getData();
            $type = $data['type'] ?? '';

            return [
                'id' => ['label' => $paymentMethod->stripeId, 'url' => $paymentMethod->getStripeEditUrl()],
                'type' => $type,
                'last4' => $data[$type]['last4'] ?? '',
                'created' => isset($data['created']) ? $formatter->asDateTime($data['created'], 'short') : '',
            ];
        }, $this->paymentMethods->getPaymentMethodsByUser($user)), t('No payment methods exist yet.', category: 'stripe'));

        return $response->contentHtml($content);
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function table(array $columns, array $rows, string $emptyMessage): string
    {
        if ($rows === []) {
            return Html::tag('p', $emptyMessage, ['class' => 'zilch']);
        }

        $head = implode('', array_map(fn (array $column) => Html::tag('th', e($column['label']), ['scope' => 'col']), $columns));
        $body = implode('', array_map(fn (array $row) => Html::tag('tr', implode('', array_map(function (array $column) use ($row) {
            $value = $row[$column['key']] ?? '';

            return Html::tag('td', match (true) {
                ! is_array($value) => e((string) $value),
                $value['internal'] ?? false => Html::a(e($value['label']), $value['url']),
                default => StripeLink::html($value['url'], $value['label']),
            });
        }, $columns))), $rows));

        return Html::tag('table', Html::tag('thead', Html::tag('tr', $head)).Html::tag('tbody', $body), ['class' => 'data fullwidth']);
    }
}
