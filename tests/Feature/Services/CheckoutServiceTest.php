<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\Plugin;
use craft\stripe\tests\TestCase;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;

class CheckoutServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(StripeProduct::constructFrom([
            'id' => 'prod_for_checkout',
            'name' => 'Product for checkout',
            'active' => true,
        ]));
    }

    public function testGetCheckoutModeReturnsPaymentForOnlyOneTimePrices(): void
    {
        $this->seedPrice('price_onetime_checkout', 'one_time');

        $mode = Plugin::getInstance()->getCheckout()->getCheckoutMode([
            ['price' => 'price_onetime_checkout', 'quantity' => 1],
        ]);

        $this->assertSame(StripeCheckoutSession::MODE_PAYMENT, $mode);
    }

    public function testGetCheckoutModeReturnsSubscriptionWhenAnyPriceIsRecurring(): void
    {
        $this->seedPrice('price_onetime_checkout2', 'one_time');
        $this->seedPrice('price_recurring_checkout', 'recurring');

        $mode = Plugin::getInstance()->getCheckout()->getCheckoutMode([
            ['price' => 'price_onetime_checkout2', 'quantity' => 1],
            ['price' => 'price_recurring_checkout', 'quantity' => 1],
        ]);

        $this->assertSame(StripeCheckoutSession::MODE_SUBSCRIPTION, $mode);
    }

    private function seedPrice(string $stripeId, string $type): void
    {
        $stripePrice = StripePrice::constructFrom([
            'id' => $stripeId,
            'active' => true,
            'currency' => 'usd',
            'unit_amount' => 1000,
            'type' => $type,
            'product' => 'prod_for_checkout',
            'recurring' => $type === 'recurring' ? ['interval' => 'month', 'interval_count' => 1] : null,
        ]);

        Plugin::getInstance()->getPrices()->createOrUpdatePrice($stripePrice);
    }
}
