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
     * @param string|null $returnUrl
     * @return string
     */
    public function getSessionUrl(
        ?string $configurationId = null,
        ?string $returnUrl = null,

    ): string {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if($currentUser === null) {
            return '';
        }

        $customer = $this->getCustomerByEmail($currentUser->email);

        if ($customer === null) {
            return '';
        }

        return $this->startBillingPortalSession($customer, $configurationId, $returnUrl);
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

        // get the first customer for this user
        $customers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($email);
        if (!empty($customers)) {
            $customer = reset($customers);
        }

        return $customer;
    }

    /**
     * Starts a billing session and returns the URL to use the stripe-hosted billing portal.
     *
     * @param Customer|string|null $customer
     * @param string|null $returnUrl
     * @return string|null
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    private function startBillingPortalSession(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $stripe = Plugin::getInstance()->getApi()->getClient();

        if (is_string($customer)) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId($customer);

            if ($customer === null) {
                throw new \Exception('No customer found for the provided ID.');
            }
        }

        $params['customer'] = $customer->stripeId;

        if ($configurationId !== null) {
            $params['configurationId'] = $configurationId;
        }

        $params['return_url'] = $returnUrl ?? UrlHelper::baseSiteUrl();

        // Trigger a 'beforeStartCheckoutSession' event
        $event = new BillingPortalSessionEvent([
            'configurationId' => $configurationId,
            'customer' => $params['customer'],
            'returnUrl' => $params['return_url'],
            'params' => $params,
        ]);
        $this->trigger(self::EVENT_BEFORE_START_BILLING_PORTAL_SESSION, $event);

        // In case they were changed in the event
        $params['customer'] = $event->customer;
        $params['return_url'] = $event->returnUrl;
        $params['configuration'] = $event->configurationId;
        $params = ArrayHelper::merge($params, $event->params);

        try {
            $session = $stripe->billingPortal->sessions->create($params);
        } catch (\Exception $e) {
            Craft::error('Unable to start Stripe billing portal session: ' . $e->getMessage());
        }

        return $session?->url;
    }
}
