<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\behaviors;

use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\stripe\elements\Subscription;
use craft\stripe\models\Customer;
use craft\stripe\models\PaymentMethod;
use craft\stripe\Plugin;
use Illuminate\Support\Collection;
use RuntimeException;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * Stripe Customer behavior.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @property Collection<Customer> $stripeCustomers
 * @property User $owner
 */
class StripeCustomerBehavior extends Behavior
{
    /**
     * @var Collection|null
     */
    private ?Collection $_customers = null;

    /**
     * @var Collection|null
     */
    private ?Collection $_subscriptions = null;

    /**
     * @var Collection|null
     */
    private ?Collection $_paymentMethods = null;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof User) {
            throw new RuntimeException('StripeCustomerBehavior can only be attached to a User element');
        }

        parent::attach($owner);
    }

    /**
     * @return Customer|null
     * @throws InvalidConfigException
     */
    public function getStripeCustomer(): ?Customer
    {
        return $this->getStripeCustomers()->first();
    }

    /**
     * @return Collection<Customer>
     * @throws InvalidConfigException
     */
    public function getStripeCustomers(): Collection
    {
        if ($this->_customers === null) {
            if (!$this->owner->hasErrors('email')) {
                $stripeCustomers = Plugin::getInstance()->getCustomers()->getCustomersByEmail($this->owner->email);

                if (!empty($stripeCustomers)) {
                    $this->_customers = collect($stripeCustomers);
                }
            }
        }

        return $this->_customers ?? new Collection();
    }

    /**
     * @return Collection<Subscription>
     */
    public function getStripeSubscriptions(): Collection
    {
        if ($this->_subscriptions === null) {
            $stripeSubscriptions = Subscription::find()->userEmail($this->owner->email)->collect();

            if ($stripeSubscriptions->isNotEmpty()) {
                $this->_subscriptions = $stripeSubscriptions;
            }
        }

        return $this->_subscriptions ?? new Collection();
    }

    /**
     * @return Collection<PaymentMethod>
     * @throws InvalidConfigException
     */
    public function getStripePaymentMethods(): Collection
    {
        if ($this->_paymentMethods === null && $this->getStripeCustomers()->isNotEmpty()) {
            $paymentMethods = new Collection();
            foreach ($this->getStripeCustomers() as $customer) {
                $pm = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodsByCustomerId($customer->stripeId);

                if (!empty($pm)) {
                    $paymentMethods->push(...$pm);
                }
            }

            if ($paymentMethods->isNotEmpty()) {
                $this->_paymentMethods = $paymentMethods;
            }
        }

        return $this->_paymentMethods ?? new Collection();
    }

    /**
     * @param string|null $configurationId
     * @param string|null $returnUrl
     * @param array $params
     * @return string|null
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getStripeBillingPortalSessionUrl(?string $returnUrl = null, ?string $configurationId = null, array $params = []): ?string
    {
        $customer = $this->getStripeCustomer();

        if ($customer === null) {
            return null;
        }

        return Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, $params);
    }

    /**
     * @param string|null $configurationId
     * @param string|null $returnUrl
     * @param array $params
     * @return string|null
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @throws \Throwable
     * @throws \yii\base\Exception
     */
    public function getStripeBillingPortalSessionPaymentMethodUpdateUrl(?string $returnUrl = null, ?string $configurationId = null, array $params = []): ?string
    {
        $customer = $this->getStripeCustomer();
        if (!$customer) {
            return null;
        }

        $returnUrl = $returnUrl ? UrlHelper::siteUrl($returnUrl) : UrlHelper::siteUrl();

        $params = ArrayHelper::merge([
            'flow_data' => [
                'type' => 'payment_method_update',
            ],
        ], $params);

        return Plugin::getInstance()->getBillingPortal()->getCustomerBillingPortalSessionUrl($customer, $configurationId, $returnUrl, $params);
    }
}
