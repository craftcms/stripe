<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\enums;

/**
 * PriceType defines all possible types of prices.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
enum PriceType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
