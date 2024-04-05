<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\events;

use craft\events\CancelableEvent;
use craft\stripe\elements\Price as PriceElement;
use Stripe\Price as StripePrice;

/**
 * Event triggered just before a synchronized price element is going to be saved.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class StripePriceSyncEvent extends CancelableEvent
{
    /**
     * @var PriceElement Craft price element being synchronized.
     */
    public PriceElement $element;

    /**
     * @var StripePrice Stripe API Price object.
     */
    public StripePrice $source;
}
