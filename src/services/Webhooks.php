<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use craft\stripe\events\StripeEvent;
use craft\stripe\Plugin;
use craft\stripe\records\Webhook;
use yii\base\Component;

/**
 * Webhooks service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Webhooks extends Component
{
    /**
     * @event StripeWebhookEvent Event triggered once an event from Stripe has been received (but after the plugin had a chance to process it too).
     * @since 1.2.0
     *
     * ---
     *
     * ```php
     * use craft\stripe\events\StripeWebhookEvent;
     * use craft\stripe\services\Webhooks;
     * use yii\base\Event;
     *
     * Event::on(
     *     Webhooks::class,
     *     Webhooks::EVENT_STRIPE_EVENT,
     *     function(StripeWebhookEvent $event) {
     *         $stripeEvent = $event->stripeEvent;
     *         $eventObject = $stripeEvent->data->object;
     *         // process the event based on its type
     *         switch ($stripeEvent->type) {
     *             case 'product.created':
     *                 $productStripeId = $eventObject->id;
     *                 // do something
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_STRIPE_EVENT = 'stripeEvent';

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
                // retrieve the subscription again as we need some expandable info too
                $subscription = $plugin->getApi()->fetchSubscriptionById($eventObject->id);
                // get the unsaved draft for a subscription when subscription
                $subscriptionElement = $plugin->getSubscriptions()->getUnsavedDraftByUid($subscription);
                // proceed with creating the element
                $plugin->getSubscriptions()->createOrUpdateSubscriptionElement($subscription, $subscriptionElement);
                break;
            case 'customer.subscription.updated':
            case 'customer.subscription.paused':
            case 'customer.subscription.resumed':
            case 'customer.subscription.pending_update_applied':
            case 'customer.subscription.pending_update_expired':
            case 'customer.subscription.deleted':
                // retrieve the subscription again as we need some expandable info too
                $subscription = $plugin->getApi()->fetchSubscriptionById($eventObject->id);
                // we only want to check if there's an unsaved draft for a subscription when subscription has been created; not in any other cases
                $plugin->getSubscriptions()->createOrUpdateSubscription($subscription);
                break;
            case 'customer.created':
            case 'customer.updated':
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
            default:
                // do nothing
                break;
        }


        $stripeEvent = new StripeEvent([
            'stripeEvent' => $event,
        ]);
        $this->trigger(self::EVENT_STRIPE_EVENT, $stripeEvent);
    }

    /**
     * Get the webhook data from the database. There should always be max one record there.
     *
     * @return Webhook
     */
    public function getWebhookRecord(): Webhook
    {
        /** @var Webhook|null $record */
        $record = Webhook::find()->one();
        return  $record ?? new Webhook();
    }
}
