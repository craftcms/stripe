<?php

namespace craft\stripe\services;

use Craft;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\stripe\events\BillingPortalSessionEvent;
use craft\stripe\events\CheckoutSessionEvent;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use Stripe\BillingPortal\Configuration;
use yii\base\Component;

/**
 * Customer Portal service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class BillingPortal extends Component
{
    /**
     * @event CheckoutSessionEvent The event that is triggered before the checkout session is started.
     *
     * It allows you to change the parameters that are used to start the checkout session.
     */
    public const EVENT_BEFORE_START_BILLING_PORTAL_SESSION = 'beforeStartBillingPortalSession';

    /**
     * Returns billing portal URL for the current user.
     *
     * @param string|null $configurationId The ID of an existing configuration to use for this session
     * @param string|null $returnUrl This is the URL the customer will be redirected to after they are done managing their billing portal session.
     * @param array $params These are the parameters that will be passed to the Stripe API when creating the session.
     * @return string
     */
    public function getSessionUrl(
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): string {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser === null) {
            return '';
        }

        $customer = $this->getCustomerByEmail($currentUser->email);

        if ($customer === null) {
            return '';
        }

        return $this->startBillingPortalSession($customer, $configurationId, $returnUrl, $params);
    }

    /**
     * Returns the first customer associated with given email address or null.
     *
     * @param string $email
     * @return Customer|null
     */
    private function getCustomerByEmail(string $email): ?Customer
    {
        $customer = null;

        // get the first customer for this email
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($email);
        if (!empty($customers)) {
            $customer = reset($customers);
        }

        return $customer;
    }

    /**
     * Starts a billing session and returns the URL to use the stripe-hosted billing portal.
     *
     * @param Customer|string $customer
     * @param string|null $returnUrl
     * @return string|null
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    public function getCustomerBillingPortalSession(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $stripe = Plugin::getInstance()->getApi()->getClient();

        if (is_string($customer)) {
            $stripeCustomer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId($customer);

            if ($stripeCustomer === null) {
                Craft::error('No stripe customer found for the provided ID: ' . $customer);
                return '';
            }
        }

        $params['customer'] = $customer->stripeId;

        if ($configurationId !== null) {
            $params['configuration'] = $configurationId;
        }

        $params['return_url'] = $returnUrl ?? UrlHelper::baseSiteUrl();

        // Trigger a 'beforeStartCheckoutSession' event
        $event = new BillingPortalSessionEvent([
            'params' => $params,
        ]);
        $this->trigger(self::EVENT_BEFORE_START_BILLING_PORTAL_SESSION, $event);

        try {
            $session = $stripe->billingPortal->sessions->create($event->params);
        } catch (\Exception $e) {
            Craft::error('Unable to start Stripe billing portal session: ' . $e->getMessage());
        }

        return $session?->url;
    }
}
