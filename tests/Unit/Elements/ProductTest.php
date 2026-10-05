<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Elements;

use craft\stripe\elements\Product;
use craft\stripe\Plugin;
use craft\stripe\tests\UnitTestCase;

class ProductTest extends UnitTestCase
{
    public function testGetStatusReturnsStripeArchivedWhenStripeStatusIsArchived(): void
    {
        $product = new Product();
        $product->enabled = true;
        $product->stripeStatus = 'archived';

        $this->assertSame(Product::STATUS_STRIPE_ARCHIVED, $product->getStatus());
    }

    public function testGetStatusReturnsLiveByDefaultWhenEnabled(): void
    {
        $product = new Product();
        $product->enabled = true;
        $product->stripeStatus = 'active';

        $this->assertSame(Product::STATUS_LIVE, $product->getStatus());
    }

    public function testGetStatusReturnsDisabledWhenNotEnabled(): void
    {
        $product = new Product();
        $product->enabled = false;

        $this->assertSame(Product::STATUS_DISABLED, $product->getStatus());
    }

    public function testGetStripeStatusHtmlForActive(): void
    {
        $product = new Product();
        $product->stripeStatus = 'active';

        $this->assertStringContainsString('green', $product->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlForArchived(): void
    {
        $product = new Product();
        $product->stripeStatus = 'archived';

        $this->assertStringContainsString('red', $product->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlForUnknownStatus(): void
    {
        $product = new Product();
        $product->stripeStatus = 'something-else';

        $this->assertStringContainsString('orange', $product->getStripeStatusHtml());
    }

    public function testGetStripeStatusHtmlTitleizesMultiWordStatus(): void
    {
        $product = new Product();
        $product->stripeStatus = 'past_due';

        $this->assertStringContainsString('Past Due', $product->getStripeStatusHtml());
    }

    public function testGetStripeEditUrl(): void
    {
        $product = new Product();
        $product->stripeId = 'prod_123';

        $this->assertSame(
            Plugin::getInstance()->stripeBaseUrl . '/products/prod_123',
            $product->getStripeEditUrl()
        );
    }

    public function testSetDataAcceptsArray(): void
    {
        $product = new Product();
        $product->setData(['id' => 'prod_123']);

        $this->assertSame(['id' => 'prod_123'], $product->getData());
    }

    public function testSetDataDecodesJsonString(): void
    {
        $product = new Product();
        $product->setData('{"id":"prod_123"}');

        $this->assertSame(['id' => 'prod_123'], $product->getData());
    }

    public function testGetDataDefaultsToEmptyArray(): void
    {
        $product = new Product();

        $this->assertSame([], $product->getData());
    }
}
