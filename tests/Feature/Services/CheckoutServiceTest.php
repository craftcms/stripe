<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature\Services;

use craft\stripe\Plugin;
use craft\stripe\tests\Helpers\StripeApiObjectFactory;
use craft\stripe\tests\TestCase;
use Stripe\Checkout\Session as StripeCheckoutSession;

class CheckoutServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct(
            StripeApiObjectFactory::product('prod_for_checkout', ['name' => 'Product for checkout'])
        );
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
        Plugin::getInstance()->getPrices()->createOrUpdatePrice(
            StripeApiObjectFactory::price($stripeId, 'prod_for_checkout', [
                'type' => $type,
                'recurring' => $type === 'recurring' ? ['interval' => 'month', 'interval_count' => 1] : null,
            ])
        );
    }
}
