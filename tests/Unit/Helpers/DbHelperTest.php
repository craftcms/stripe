<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests\Unit\Helpers;

use craft\stripe\elements\Price;
use craft\stripe\helpers\Db as DbHelper;
use craft\stripe\tests\UnitTestCase;

class DbHelperTest extends UnitTestCase
{
    public function testWrapsStringValueWithWildcards(): void
    {
        $query = Price::find()->currency('GBP');
        $this->assertSame('*GBP*', DbHelper::prepareForLikeSearch($query, 'currency'));
    }

    public function testWrapsEachArrayValueWithWildcards(): void
    {
        $query = Price::find()->currency(['GBP', 'USD']);
        $result = DbHelper::prepareForLikeSearch($query, 'currency');

        $this->assertSame(['or', '*GBP*', '*USD*'], $result);
    }

    public function testPreservesNotOperatorForArrayValues(): void
    {
        $query = Price::find()->currency(['not', 'GBP', 'USD']);
        $result = DbHelper::prepareForLikeSearch($query, 'currency');

        $this->assertSame(['not', '*GBP*', '*USD*'], $result);
    }

    public function testDoesNotWrapValuesThatAreAlreadyOperatorPrefixed(): void
    {
        $query = Price::find()->currency([':empty:']);
        $result = DbHelper::prepareForLikeSearch($query, 'currency');

        $this->assertSame(['or', ':empty:'], $result);
    }

    public function testReturnsEmptyArrayUnchangedForEmptyArrayValue(): void
    {
        $query = Price::find()->currency([]);
        $result = DbHelper::prepareForLikeSearch($query, 'currency');

        $this->assertSame([], $result);
    }

    public function testReturnsNullUnchangedForNullValue(): void
    {
        $query = Price::find()->currency(null);
        $result = DbHelper::prepareForLikeSearch($query, 'currency');

        $this->assertNull($result);
    }
}
