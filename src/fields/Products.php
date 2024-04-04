<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\fields;

use Craft;
use craft\fields\BaseRelationField;
use craft\stripe\elements\Product;

/**
 * Class Stripe Product Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 *
 * @property-read array $contentGqlType
 */
class Products extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('stripe', 'Stripe Products');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return 'box-archive';
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('stripe', 'Add a product');
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return Product::class;
    }
}
