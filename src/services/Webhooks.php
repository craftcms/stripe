<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\App;
use craft\log\MonologTarget;
use craft\stripe\Plugin;
use Stripe\Stripe;
use yii\base\Component;
use Stripe\StripeClient;

/**
 * Webhooks service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Webhooks extends Component
{
    /**
     * Process events received from Stripe
     *
     * @return void
     */
    public function processEvent($event): void
    {
        $plugin = Plugin::getInstance();
        $eventObject = $event->data->object;

        switch ($event->type) {
            case 'product.created':
            case 'product.updated':
                // retrieve the product again as we need some expandable info too
                $product = $plugin->getApi()->fetchProductById($eventObject->id);
                $plugin->getProducts()->createOrUpdateProduct($product);
                break;
            case 'product.deleted':
                $plugin->getProducts()->deleteProductByStripeId($eventObject->id);
                break;
            case 'price.created':
            case 'price.updated':
                // retrieve the price again as we need some expandable info too
                $price = $plugin->getApi()->fetchPriceById($eventObject->id);
                $plugin->getPrices()->createOrUpdatePrice($price);
                break;
            case 'price.deleted':
                $plugin->getPrices()->deletePriceByStripeId($eventObject->id);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.paused':
            case 'customer.subscription.resumed':
            case 'customer.subscription.pending_update_applied':
            case 'customer.subscription.pending_update_expired':
            case 'customer.subscription.deleted':
                // retrieve the subscription again as we need some expandable info too
                $subscription = $plugin->getApi()->fetchSubscriptionById($eventObject->id);
                $plugin->getSubscriptions()->createOrUpdateSubscription($subscription);
                break;
            case 'customer.created':
                // retrieve the customer again as we need some expandable info too
                $customer = $plugin->getApi()->fetchCustomerById($eventObject->id);
                $plugin->getCustomers()->createOrUpdateCustomer($customer);
                break;
            case 'customer.deleted':
                $plugin->getCustomers()->deleteCustomerDataByStripeId($eventObject->id);
                $plugin->getPaymentMethods()->deletePaymentMethodsByCustomerId($eventObject->id);
                break;
            case 'payment_method.attached':
            case 'payment_method.automatically_updated':
            case 'payment_method.updated':
                // retrieve the payment method again as we need some expandable info too
                $paymentMethod = $plugin->getApi()->fetchPaymentMethodByIds($eventObject->customer, $eventObject->id);
                $plugin->getPaymentMethods()->createOrUpdatePaymentMethod($paymentMethod);
                break;
            case 'payment_method.detached':
                $plugin->getPaymentMethods()->deletePaymentMethodByStripeId($eventObject->id);
                break;
            // this gets triggered when creating a draft invoice
            case 'invoice.created':
            case 'invoice.finalized':
            case 'invoice.marked_uncollectible':
            case 'invoice.overdue':
            case 'invoice.paid':
            case 'invoice.payment_action_required':
            case 'invoice.payment_failed':
            case 'invoice.payment_succeeded':
            // this gets triggered all the time when creating an invoice; when you change a due date, add an item or amend it
            case 'invoice.updated':
            case 'invoice.voided':
                // retrieve the invoice again as we need some expandable info too
                $invoice = $plugin->getApi()->fetchInvoiceById($eventObject->id);
                $plugin->getInvoices()->createOrUpdateInvoice($invoice);
                break;
            // this can only happen when it's a draft invoice
            case 'invoice.deleted':
                $plugin->getInvoices()->deleteInvoiceByStripeId($eventObject->id);
                break;
        }
    }
}
