<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Models;

use craft\stripe\models\Settings;
use craft\stripe\tests\UnitTestCase;

class SettingsTest extends UnitTestCase
{
    public function testFailsValidationWithoutRequiredKeys(): void
    {
        $settings = new Settings();
        $this->assertFalse($settings->validate());
        $this->assertArrayHasKey('secretKey', $settings->getErrors());
        $this->assertArrayHasKey('publishableKey', $settings->getErrors());
    }

    public function testPassesValidationWithRequiredKeys(): void
    {
        $settings = new Settings();
        $settings->secretKey = 'sk_test_123';
        $settings->publishableKey = 'pk_test_123';

        $this->assertTrue($settings->validate());
    }

    public function testAttributeLabels(): void
    {
        $labels = (new Settings())->attributeLabels();

        $this->assertArrayHasKey('secretKey', $labels);
        $this->assertArrayHasKey('publishableKey', $labels);
        $this->assertArrayHasKey('webhookSigningSecret', $labels);
        $this->assertArrayHasKey('productUriFormat', $labels);
        $this->assertArrayHasKey('productTemplate', $labels);
    }
}
