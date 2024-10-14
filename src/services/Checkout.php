<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\services;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
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
     * @param string|User|false|null $user User Element or email address
     * @param string|null $successUrl
     * @param string|null $cancelUrl
     * @param array|null $params
     * @return string
     */
    public function getCheckoutUrl(
        array $lineItems = [],
        string|User|false|null $user = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?array $params = null,
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

        return $this->startCheckoutSession(
            array_values($lineItems),
            $customer,
            $this->getUrl('success', $successUrl),
            $this->getUrl('cancel', $cancelUrl),
            $params,
        );
    }

    /**
     * Returns checkout mode based on the line items for the checkout.
     * If there are only one-time products in the $lineItems, the mode should be 'payment'.
     * If there are any recurring products in the $lineItems, the mode should be 'subscription'.
     *
     * @param array $lineItems
     * @return string
     */
    public function getCheckoutMode(array $lineItems): string
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
        ?array $params = null,
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
        } elseif (is_string($customer)) {
            $data['customer_email'] = $customer;
        }

        if ($params !== null) {
            $data += $params;
        }

        // Trigger a 'beforeStartCheckoutSession' event
        $event = new CheckoutSessionEvent([
            'params' => $data,
        ]);
        $this->trigger(self::EVENT_BEFORE_START_CHECKOUT_SESSION, $event);

        // ensure the mode is still correct
        $checkoutSession = $event->params;
        $checkoutSession['mode'] = $this->getCheckoutMode($event->params['line_items']);

        $session = $stripe->checkout->sessions->create($checkoutSession);

        return $session->url;
    }

    /**
     * Figure out the URL to use in the checkout session params.
     *
     * If $url is passed - ensure it's absolute and return.
     * If no $url is passed - use the default for the type (success or cancel).
     * If we still don't have the URL, use the one the request originated from (e.g. the product page).
     *
     * @param string $type
     * @param string|null $url
     * @return string
     * @throws \yii\base\Exception
     */
    private function getUrl(string $type, ?string $url = null): string
    {
        // if we have one, ensure it's a valid site URL
        if ($url !== null) {
            return UrlHelper::siteUrl($url);
        }

        // if we're still here and we have a default - use it
        $settings = Plugin::getInstance()->getSettings();
        if ($type === 'success' && !empty($settings['defaultSuccessUrl'])) {
            return UrlHelper::siteUrl($settings['defaultSuccessUrl']);
        }

        if ($type === 'cancel' && !empty($settings['defaultCancelUrl'])) {
            return UrlHelper::siteUrl($settings['defaultCancelUrl']);
        }

        // if we still don't have the url, assume they want to go back to the same page
        return Craft::$app->getRequest()->getAbsoluteUrl();
    }
}
