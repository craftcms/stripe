<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\linktypes;

use craft\fields\linktypes\BaseElementLinkType;
use craft\stripe\elements\Product as ProductElement;

/**
 * Stripe Product link type.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.2.0
 */
class Product extends BaseElementLinkType
{
    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return ProductElement::class;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return ProductElement::lowerDisplayName();
    }
}
