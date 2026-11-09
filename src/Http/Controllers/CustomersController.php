<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\PaymentMethod;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Support\InvoiceTable;

use function CraftCms\Cms\t;

/**
 * The Stripe screen of the Edit User page: the user’s customers, subscriptions, invoices and payment methods.
 *
 * @since 2.0.0
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

        $formatter = I18N::getFormatter();

        $customers = Table::make('stripe-customers')
            ->columns([
                ['key' => 'name', 'label' => t('Name', category: 'stripe')],
                ['key' => 'email', 'label' => t('Email', category: 'stripe')],
                ['key' => 'id', 'label' => t('Stripe ID', category: 'stripe')],
            ])
            ->rows(array_map(fn (Customer $customer) => [
                'name' => $customer->getData()['name'] ?? '',
                'email' => $customer->email,
                'id' => ['label' => $customer->stripeId, 'url' => $customer->getStripeEditUrl()],
            ], array_values($this->customers->getCustomersByEmail($user->email))))
            ->emptyMessage(t('No Stripe customers have this user’s email.', category: 'stripe'));

        $subscriptions = Table::make('stripe-subscriptions')
            ->columns([
                ['key' => 'title', 'label' => t('Subscription', category: 'stripe')],
                ['key' => 'status', 'label' => t('Stripe Status', category: 'stripe')],
                ['key' => 'products', 'label' => t('Products', category: 'stripe')],
                ['key' => 'currentPeriodEnd', 'label' => t('Current period end', category: 'stripe')],
            ])
            ->rows(Subscription::find()->userId($user->id)->status(null)->collect()->map(fn (Subscription $subscription) => [
                '_status' => $subscription->getStatus(),
                'title' => ['label' => $subscription->title, 'url' => $subscription->getCpEditUrl()],
                'status' => $subscription->stripeStatus,
                'products' => implode(', ', array_map(fn (Product $product) => $product->title, $subscription->getProducts() ?? [])),
                'currentPeriodEnd' => isset($subscription->getData()['current_period_end']) ? $formatter->asDate($subscription->getData()['current_period_end'], 'short') : '',
            ]))
            ->emptyMessage(t('No subscriptions exist yet.', category: 'stripe'));

        $invoices = Table::make('stripe-invoices')
            ->columns(InvoiceTable::columns())
            ->rows(InvoiceTable::rows($this->invoices->getInvoicesByUser($user)))
            ->emptyMessage(t('No invoices exist yet.', category: 'stripe'));

        $paymentMethods = Table::make('stripe-payment-methods')
            ->columns([
                ['key' => 'id', 'label' => t('Stripe ID', category: 'stripe')],
                ['key' => 'type', 'label' => t('Type', category: 'stripe')],
                ['key' => 'last4', 'label' => t('Last 4', category: 'stripe')],
                ['key' => 'created', 'label' => t('Created', category: 'stripe')],
            ])
            ->rows(array_map(function (PaymentMethod $paymentMethod) use ($formatter) {
                $data = $paymentMethod->getData();
                $type = $data['type'] ?? '';

                return [
                    'id' => ['label' => $paymentMethod->stripeId, 'url' => $paymentMethod->getStripeEditUrl()],
                    'type' => $type,
                    'last4' => $data[$type]['last4'] ?? '',
                    'created' => isset($data['created']) ? $formatter->asDateTime($data['created'], 'short') : '',
                ];
            }, $this->paymentMethods->getPaymentMethodsByUser($user)))
            ->emptyMessage(t('No payment methods exist yet.', category: 'stripe'));

        return $response->ui(Ui::make([
            Heading::make('stripe-customers-heading', t('Customers', category: 'stripe')),
            $customers,
            Heading::make('stripe-subscriptions-heading', t('Subscriptions', category: 'stripe')),
            $subscriptions,
            Heading::make('stripe-invoices-heading', t('Invoices', category: 'stripe')),
            $invoices,
            Heading::make('stripe-payment-methods-heading', t('Payment Methods', category: 'stripe')),
            $paymentMethods,
        ]));
    }
}
