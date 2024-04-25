<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\elements\User;
use craft\stripe\elements\Price;
use craft\stripe\events\CheckoutSessionEvent;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Price as StripePrice;
use yii\base\Component;

/**
 * Checkout service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Checkout extends Component
{
    /**
     * @event CheckoutSessionEvent The event that is triggered before the checkout session is started.
     *
     * It allows you to change the parameters that are used to start the checkout session.
     */
    public const EVENT_BEFORE_START_CHECKOUT_SESSION = 'beforeStartCheckoutSession';


    /**
     * Returns checkout URL based on the provided email.
     *
     * @param array $lineItems
     * @param string|User|null $user User Element or email address
     * @param string|null $successUrl
     * @param string|null $cancelUrl
     * @return string
     */
    public function getCheckoutUrl(
        array $lineItems = [],
        string|User|null $user = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
    ): string {
        $customer = null;

        // if passed in user is a string - it should be an email address
        if (is_string($user)) {
            // try to find the first Stripe Customer for this email;
            // if none is found just use the email that was passed in
            $customer = $this->getCheckoutCustomerByEmail($user) ?? $user;
        } else {
            // if user is null - try to get the currently logged in user
            if ($user === null) {
                $user = Craft::$app->getUser()->getIdentity();
            }

            // if User element is passed in, or we just got one via getIdentity
            if ($user instanceof User) {
                // try to find the first Stripe Customer for that User's email
                // if none is found just use the User's email we have on account
                $customer = $this->getCheckoutCustomerByEmail($user->email) ?? $user->email;
            }
        }

        return $this->startCheckoutSession(array_values($lineItems), $customer, $successUrl, $cancelUrl);
    }

    /**
     * Returns the first customer associated with given email address or null.
     *
     * @param string $email
     * @return Customer|null
     */
    private function getCheckoutCustomerByEmail(string $email): ?Customer
    {
        $customer = null;

        // get the first customer for this user
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($email);
        if (!empty($customers)) {
            $customer = reset($customers);
        }

        return $customer;
    }

    /**
     * Returns checkout mode based on the line items for the checkout.
     * If there are only one-time products in the $lineItems, the mode should be 'payment'.
     * If there are any recurring products in the $lineItems, the mode should be 'subscription'.
     *
     * @param array $lineItems
     * @return string
     */
    private function getCheckoutMode(array|object $lineItems): string
    {
        // figure out checkout mode based on whether there are any recurring prices in the $lineItems
        $mode = StripeCheckoutSession::MODE_PAYMENT;
        $priceTypes = array_map(function($item) {
            $price = Price::find()->stripeId($item['price'])->one();
            return $price->getData()['type'];
        }, $lineItems);
        if (in_array(StripePrice::TYPE_RECURRING, $priceTypes)) {
            $mode = StripeCheckoutSession::MODE_SUBSCRIPTION;
        }

        return $mode;
    }

    /**
     * Starts a checkout session and returns the URL to use the stripe-hosted checkout.
     *
     * @param Customer|string|null $customer
     * @param array $lineItems
     * @param string|null $successUrl
     * @param string|null $cancelUrl
     * @return string|null
     * @throws \Stripe\Exception\ApiErrorException
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    private function startCheckoutSession(
        array $lineItems,
        Customer|string|null $customer = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
    ): ?string {
        $stripe = Plugin::getInstance()->getApi()->getClient();

        $data = [
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'mode' => $this->getCheckoutMode($lineItems),
        ];
        if ($customer instanceof Customer) {
            $data['customer'] = $customer->stripeId;
        } else {
            $data['customer_email'] = $customer;
        }

        // Trigger a 'beforeStartCheckoutSession' event
        $event = new CheckoutSessionEvent([
            'sessionData' => $data,
        ]);
        $this->trigger(self::EVENT_BEFORE_START_CHECKOUT_SESSION, $event);

        // ensure the mode is still correct
        $checkoutSession = $event->sessionData;
        $checkoutSession['mode'] = $this->getCheckoutMode($event->sessionData['line_items']);

        $session = $stripe->checkout->sessions->create($checkoutSession);

        return $session->url;
    }
}
