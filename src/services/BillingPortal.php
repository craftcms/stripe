<?php

namespace craft\stripe\services;

use Craft;
use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\stripe\behaviors\StripeCustomerBehavior;
use craft\stripe\events\BillingPortalSessionEvent;
use craft\stripe\events\CheckoutSessionEvent;
use craft\stripe\models\Customer;
use craft\stripe\Plugin;
use Stripe\BillingPortal\Configuration;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

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
     * @param Customer|string $customer The customer to create a billing portal session for
     * @param string|null $configurationId The ID of an existing configuration to use for this session
     * @param string|null $returnUrl This is the URL the customer will be redirected to after they are done managing their billing portal session.
     * @param array $params These are the parameters that will be passed to the Stripe API when creating the session.
     * @return string|null
     * @throws Exception
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @throws \Throwable
     */
    public function getSessionUrl(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        /** @var User|StripeCustomerBehavior|null $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser === null) {
            return null;
        }

        $customerStripeId = is_string($customer) ? $customer : $customer->stripeId;
        if (!$currentUser->getStripeCustomers()->firstWhere('stripeId', $customerStripeId)) {
            return null;
        }

        return $this->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, $params);
    }

    /**
     * Starts a billing session and returns the URL to use the stripe-hosted billing portal.
     *
     * @param Customer|string $customer
     * @param string|null $configurationId
     * @param string|null $returnUrl
     * @param array $params
     * @return string|null
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function getCustomerBillingPortalSessionUrl(
        Customer|string $customer,
        ?string $configurationId = null,
        ?string $returnUrl = null,
        array $params = [],
    ): ?string {
        $stripe = Plugin::getInstance()->getApi()->getClient();
        $returnUrl = $returnUrl ? UrlHelper::siteUrl($returnUrl) : UrlHelper::siteUrl();

        if (is_string($customer)) {
            $customerId = $customer;
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByStripeId($customerId);

            if ($customer === null) {
                Craft::error('No stripe customer found for the provided ID: ' . $customerId);
                return null;
            }
        }

        $params['customer'] = $customer->stripeId;

        if ($configurationId !== null) {
            $params['configuration'] = $configurationId;
        }

        $params['return_url'] = $returnUrl;

        // Trigger a 'beforeStartCheckoutSession' event
        $event = new BillingPortalSessionEvent([
            'params' => $params,
        ]);
        $this->trigger(self::EVENT_BEFORE_START_BILLING_PORTAL_SESSION, $event);

        $session = null;

        try {
            $session = $stripe->billingPortal->sessions->create($event->params);
        } catch (\Exception $e) {
            Craft::error('Unable to start Stripe billing portal session: ' . $e->getMessage());
        }

        return $session ? $session->url : '';
    }
}
