<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\web\twig;

use craft\stripe\helpers\Price;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class StripeTwigExtension
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Extension extends AbstractExtension
{
    public function getName(): string
    {
        return 'Craft Stripe Twig Extension';
    }

    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('priceAmount', [Price::class, 'asPriceAmount']),
            new TwigFilter('unitPrice', [Price::class, 'asUnitPrice']),
            new TwigFilter('pricePerUnit', [Price::class, 'asPricePerUnit']),
            new TwigFilter('interval', [Price::class, 'getInterval']),
        ];
    }
}
