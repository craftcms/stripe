<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\web\twig;

use craft\stripe\helpers\Price;
use craft\stripe\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

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
            new TwigFilter('unitAmount', [Price::class, 'asUnitAmount']),
            new TwigFilter('unitPrice', [Price::class, 'asUnitPrice']),
            new TwigFilter('pricePerUnit', [Price::class, 'asPricePerUnit']),
            new TwigFilter('interval', [Price::class, 'getInterval']),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('stripeCheckoutUrl', function(
                array $lineItems = [],
                ?string $customer = null,
                ?string $successUrl = null,
                ?string $cancelUrl = null,
                ?array $params = null,
            ) {
                return Plugin::getInstance()->getCheckout()->getCheckoutUrl($lineItems, $customer, $successUrl, $cancelUrl, $params);
            }),
        ];
    }
}
