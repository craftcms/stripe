<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Base;

use craft\stripe\base\Model;
use craft\stripe\tests\UnitTestCase;

class ModelTest extends UnitTestCase
{
    public function testGetDataDefaultsToEmptyArray(): void
    {
        $model = new Model();
        $this->assertSame([], $model->getData());
    }

    public function testSetDataAcceptsArray(): void
    {
        $model = new Model();
        $model->setData(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $model->getData());
    }

    public function testSetDataDecodesJsonString(): void
    {
        $model = new Model();
        $model->setData('{"foo":"bar"}');
        $this->assertSame(['foo' => 'bar'], $model->getData());
    }

    public function testSetDataAcceptsNull(): void
    {
        $model = new Model();
        $model->setData(null);
        $this->assertSame([], $model->getData());
    }

    public function testAttributesIncludesData(): void
    {
        $model = new Model();
        $this->assertContains('data', $model->attributes());
    }
}
