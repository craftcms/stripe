<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Twig;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Stripe\Checkout;
use CraftCms\Stripe\Support\PriceFormatter;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Price filters, and the `stripeCheckoutUrl()` function.
 *
 * @since 2.0.0
 */
class Extension extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('unitAmount', PriceFormatter::asUnitAmount(...)),
            new TwigFilter('unitPrice', PriceFormatter::asUnitPrice(...)),
            new TwigFilter('pricePerUnit', PriceFormatter::asPricePerUnit(...)),
            new TwigFilter('interval', PriceFormatter::getInterval(...)),
        ];
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('stripeCheckoutUrl', fn (
                array $lineItems = [],
                string|User|false|null $customer = null,
                ?string $successUrl = null,
                ?string $cancelUrl = null,
                ?array $params = null,
            ) => app(Checkout::class)->getCheckoutUrl($lineItems, $customer, $successUrl, $cancelUrl, $params)),
        ];
    }
}
