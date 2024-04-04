<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use craft\events\CancelableEvent;
use craft\stripe\elements\Product as ProductElement;
use Stripe\Product as StripeProduct;

/**
 * Event triggered just before a synchronized product element is going to be saved.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class StripeProductSyncEvent extends CancelableEvent
{
    /**
     * @var ProductElement Craft product element being synchronized.
     */
    public ProductElement $element;

    /**
     * @var StripeProduct Stripe API Product object.
     */
    public StripeProduct $source;
}
