<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Element\EmbeddedElementIndex;
use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Stripe\Customers;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\Invoices;
use CraftCms\Stripe\Models\Customer;
use CraftCms\Stripe\Models\PaymentMethod;
use CraftCms\Stripe\PaymentMethods;
use CraftCms\Stripe\Support\InvoiceTable;

use function CraftCms\Cms\t;
use function CraftCms\Cms\currentUser;

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
                ['key' => 'stripeId', 'label' => t('Stripe ID', category: 'stripe')],
            ])
            ->rows(array_map(fn (Customer $customer) => [
                'name' => $customer->getData()['name'] ?? '',
                'email' => $customer->email,
                'stripeId' => ['label' => $customer->stripeId, 'url' => $customer->getStripeEditUrl()],
            ], array_values($this->customers->getCustomersByEmail($user->email))))
            ->emptyMessage(t('No Stripe customers have this user’s email.', category: 'stripe'));

        $subscriptions = EmbeddedElementIndex::make(Subscription::class)
            ->criteria(['userId' => $user->id])
            ->readOnly(! currentUser()?->can('accessPlugin-stripe'))
            ->storageKey('commerce:user-subscriptions');

        $invoices = Table::make('stripe-invoices')
            ->columns(InvoiceTable::columns())
            ->rows(InvoiceTable::rows($this->invoices->getInvoicesByUser($user)))
            ->emptyMessage(t('No invoices exist yet.', category: 'stripe'));

        $paymentMethods = Table::make('stripe-payment-methods')
            ->columns([
                ['key' => 'stripeId', 'label' => t('Stripe ID', category: 'stripe')],
                ['key' => 'type', 'label' => t('Type', category: 'stripe')],
                ['key' => 'last4', 'label' => t('Last 4', category: 'stripe')],
                ['key' => 'created', 'label' => t('Created', category: 'stripe')],
            ])
            ->rows(array_map(function (PaymentMethod $paymentMethod) use ($formatter) {
                $data = $paymentMethod->getData();
                $type = $data['type'] ?? '';

                return [
                    'stripeId' => ['label' => $paymentMethod->stripeId, 'url' => $paymentMethod->getStripeEditUrl()],
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
            $subscriptions->uiNode('subscriptions'),
            Heading::make('stripe-invoices-heading', t('Invoices', category: 'stripe')),
            $invoices,
            Heading::make('stripe-payment-methods-heading', t('Payment Methods', category: 'stripe')),
            $paymentMethods,
        ]), props: [
            // The Ui page shows `metadataHtml` in its Info panel, rather than the `details` other user screens use
            'metadataHtml' => (string) value($response->metaSidebarHtml),
        ]);
    }
}
