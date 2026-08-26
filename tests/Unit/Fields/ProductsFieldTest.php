<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Fields;

use craft\stripe\elements\Product;
use craft\stripe\fields\Products;
use craft\stripe\tests\UnitTestCase;

class ProductsFieldTest extends UnitTestCase
{
    public function testDisplayName(): void
    {
        $this->assertNotEmpty(Products::displayName());
    }

    public function testIcon(): void
    {
        $this->assertSame('box-archive', Products::icon());
    }

    public function testDefaultSelectionLabel(): void
    {
        $this->assertNotEmpty(Products::defaultSelectionLabel());
    }

    public function testElementType(): void
    {
        $this->assertSame(Product::class, Products::elementType());
    }
}
