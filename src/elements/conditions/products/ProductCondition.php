<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\elements\conditions\products;

use craft\elements\conditions\ElementCondition;
use craft\errors\InvalidTypeException;

/**
 * Product condition
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ProductCondition extends ElementCondition
{
    /**
     * @throws InvalidTypeException
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            StripeStatusConditionRule::class,
        ]);
    }
}
