<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\web\twig;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use craft\stripe\elements\Subscription;
use craft\stripe\Plugin;
use yii\base\Behavior;

/**
 * Class CraftVariableBehavior
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class CraftVariableBehavior extends Behavior
{
    /**
     * @var Plugin
     */
    public Plugin $stripe;

    public function init(): void
    {
        parent::init();

        $this->stripe = Plugin::getInstance();
    }

    /**
     * Returns a new ProductQuery instance.
     *
     * @param array $criteria
     * @return ElementQueryInterface
     */
    public function stripeProducts(array $criteria = []): ElementQueryInterface
    {
        $query = Product::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    /**
     * Returns a new PriceQuery instance.
     *
     * @param array $criteria
     * @return ElementQueryInterface
     */
    public function stripePrices(array $criteria = []): ElementQueryInterface
    {
        $query = Price::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    /**
     * Returns a new SubscriptionQuery instance.
     *
     * @param array $criteria
     * @return ElementQueryInterface
     */
    public function stripeSubscriptions(array $criteria = []): ElementQueryInterface
    {
        $query = Subscription::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    /**
     * @param array $lineItems
     * @param string|null $customer
     * @param string|null $successUrl
     * @param string|null $cancelUrl
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function stripeCheckoutUrl(
        array $lineItems = [],
        ?string $customer = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?array $params = null,
    ): string {
        return Plugin::getInstance()->getCheckout()->getCheckoutUrl($lineItems, $customer, $successUrl, $cancelUrl, $params);
    }
}
