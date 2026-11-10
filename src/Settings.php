<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Stripe settings, stored in project config or set from `config/craft/stripe.php`.
 *
 * @since 2.0.0
 */
class Settings extends PluginSettings
{
    /**
     * @var string The secret API key, or an environment variable that holds it
     */
    public string $secretKey = '';

    /**
     * @var string The publishable API key, or an environment variable that holds it
     */
    public string $publishableKey = '';

    /**
     * @var string The URI format for product pages
     */
    public string $productUriFormat = '';

    /**
     * @var string The template that renders product pages
     */
    public string $productTemplate = '';

    /**
     * @var bool Whether a user’s changed email should be sent to their Stripe customers
     */
    public bool $syncChangedUserEmailsToStripe = true;

    /**
     * @var string|null Where Checkout sends customers after a successful payment, unless the request names a URL
     */
    public ?string $defaultSuccessUrl = null;

    /**
     * @var string|null Where Checkout sends customers who cancel, unless the request names a URL
     */
    public ?string $defaultCancelUrl = null;

    /**
     * @var bool Whether syncing a subscription creates a user for a customer email that has none (Craft Pro only)
     */
    public bool $createUserIfMissing = false;
}
