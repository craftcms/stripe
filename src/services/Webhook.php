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
 * Webhook service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Webhook extends Component
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
                $plugin->getProducts()->createOrUpdateProduct($eventObject);
                break;
            case 'product.deleted':
                $plugin->getProducts()->deleteProductByStripeId($eventObject->id);
                break;
            case 'price.created':
            case 'price.updated':
                $plugin->getPrices()->createOrUpdatePrice($eventObject);
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
                $plugin->getSubscriptions()->createOrUpdateSubscription($eventObject);
                break;
            case 'customer.subscription.deleted':
                $plugin->getSubscriptions()->deleteSubscriptionByStripeId($eventObject->id);
                break;
            case 'customer.created':
                $plugin->getCustomers()->createOrUpdateCustomer($eventObject);
                break;
            case 'customer.deleted':
                $plugin->getCustomers()->deleteCustomerByStripeId($eventObject->id);
                break;
            case 'payment_method.attached':
            case 'payment_method.automatically_updated':
            case 'payment_method.updated':
                $plugin->getPaymentMethods()->createOrUpdatePaymentMethod($eventObject);
                break;
            case 'payment_method.detached':
                $plugin->getPaymentMethods()->deletePaymentMethodByStripeId($eventObject->id);
                break;
            // this gets triggered when creating a draft invoice; not sure if we want to deal with those
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
                $plugin->getInvoices()->createOrUpdateInvoice($eventObject);
                break;
            case 'invoice.deleted':
                $plugin->getInvoices()->deleteInvoiceByStripeId($eventObject->id);
                break;
            default:
                echo 'Received unknown event type ' . $event->type;
        }
    }
}
