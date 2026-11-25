<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Feature;

use Craft;
use craft\stripe\tests\TestCase;

class InstallTest extends TestCase
{
    public function testCraftHasDatabase(): void
    {
        $this->assertTrue(Craft::$app->getDb()->getIsActive());
    }

    public function testStripeIsInstalled(): void
    {
        $this->assertNotNull(Craft::$app->getPlugins()->getPlugin('stripe'));
    }
}
