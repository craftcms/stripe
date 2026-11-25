<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Tests;

use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Stripe\Tests\Support\FakeStripe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;
use Stripe\ApiRequestor;

abstract class TestCase extends PluginTestCase
{
    public const string SECRET_KEY = 'sk_test_plugin';

    public const string WEBHOOK_SECRET = 'whsec_test_plugin';

    protected FakeStripe $stripe;

    #[Override]
    protected function setUp(): void
    {
        // Every Stripe SDK request goes to the in-memory fake, never api.stripe.com
        $this->stripe = new FakeStripe;
        ApiRequestor::setHttpClient($this->stripe);

        parent::setUp();

        // Use Craft’s model factories, as core’s tests do
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CraftCms\\Cms\\Database\\Factories\\'.class_basename($modelName).'Factory',
        );
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('craft.stripe', [
            'secretKey' => self::SECRET_KEY,
            'publishableKey' => 'pk_test_plugin',
            'defaultSuccessUrl' => 'https://localhost/thanks',
            'defaultCancelUrl' => 'https://localhost/cancel',
        ]);
    }
}
