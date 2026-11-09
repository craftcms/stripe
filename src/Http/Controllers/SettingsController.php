<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Http\Controllers;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Tab;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\FieldLayouts;
use CraftCms\Stripe\Plugin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * The plugin settings, with the product, price and subscription field layouts.
 *
 * @since 2.0.0
 */
class SettingsController
{
    use RespondsWithFlash;

    /**
     * Field layout values, by element type.
     *
     * @var array<class-string, string>
     */
    private const array LAYOUTS = [
        Product::class => 'productFieldLayout',
        Price::class => 'priceFieldLayout',
        Subscription::class => 'subscriptionFieldLayout',
    ];

    private readonly bool $readOnly;

    public function __construct(
        GeneralConfig $generalConfig,
        private readonly FieldLayouts $fieldLayouts,
    ) {
        $this->readOnly = ! $generalConfig->allowAdminChanges;
    }

    public function index(): CpScreenResponse
    {
        $settings = Plugin::getInstance()->getSettings();
        $envTriggers = SelectOptions::getEnvTextExpanderTriggers(true);

        $values = [
            ...$settings->toArray(),
            ...array_map(fn (string $elementType) => $this->fieldLayouts->getFieldLayout($elementType)->getConfig() ?? [], array_flip(self::LAYOUTS)),
        ];

        $layoutDesigner = fn (string $elementType) => Field::make(null, FieldLayoutDesigner::make(self::LAYOUTS[$elementType])
            ->elementType($elementType)
            ->withCardViewDesigner());

        $ui = Ui::make([
            Tab::make('api', t('API Connection', category: 'stripe'), [
                Field::make(t('Secret Key', category: 'stripe'), Text::make('secretKey')->monospace()->textExpanderTriggers($envTriggers))
                    ->instructions(t('Your Stripe secret API key, or an environment variable that holds it.', category: 'stripe'))
                    ->required(),
                Field::make(t('Publishable Key', category: 'stripe'), Text::make('publishableKey')->monospace()->textExpanderTriggers($envTriggers))
                    ->instructions(t('Your Stripe publishable API key, or an environment variable that holds it.', category: 'stripe')),
                Field::make(t('Default Checkout Success URL', category: 'stripe'), Text::make('defaultSuccessUrl'))
                    ->instructions(t('Where Checkout sends customers after a successful payment, unless the checkout request names a URL.', category: 'stripe')),
                Field::make(t('Default Checkout Cancel URL', category: 'stripe'), Text::make('defaultCancelUrl'))
                    ->instructions(t('Where Checkout sends customers who cancel, unless the checkout request names a URL.', category: 'stripe')),
                Field::make(t('Sync changed user emails to Stripe', category: 'stripe'), Lightswitch::make('syncChangedUserEmailsToStripe')),
                Field::make(t('Create users for new subscriptions', category: 'stripe'), Lightswitch::make('createUserIfMissing'))
                    ->instructions(t('Whether syncing a subscription creates a user for its customer’s email, when none exists yet. Requires Craft Pro.', category: 'stripe')),
            ]),
            Tab::make('products', t('Products', category: 'stripe'), [
                Field::make(t('Product URI Format', category: 'stripe'), Text::make('productUriFormat')->monospace())
                    ->instructions(t('What product URIs should look like, such as `products/{slug}`. Leave it blank if products don’t have URLs.', category: 'stripe')),
                Field::make(t('Product Template', category: 'stripe'), Text::make('productTemplate')->monospace())
                    ->instructions(t('The template that renders product pages.', category: 'stripe')),
                $layoutDesigner(Product::class),
            ]),
            Tab::make('prices', t('Prices', category: 'stripe'), [
                $layoutDesigner(Price::class),
            ]),
            Tab::make('subscriptions', t('Subscriptions', category: 'stripe'), [
                $layoutDesigner(Subscription::class),
            ]),
        ]);

        return new CpScreenResponse()
            ->title(t('Stripe Settings', category: 'stripe'))
            ->selectedSubnavItem('stripe/settings')
            ->ui($ui, new UiContext(
                values: $values,
                mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
            ), [
                'readOnly' => $this->readOnly,
                ...($this->readOnly ? [] : ['submit' => ['method' => 'post', 'url' => Url::actionUrl('stripe/settings/save-settings')]]),
            ]);
    }

    public function store(Request $request, Plugins $plugins): Response
    {
        abort_if($this->readOnly, 403, 'Administrative changes are disallowed in this environment.');

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $originalUriFormat = $settings->productUriFormat;

        $data = $request->validate([
            'secretKey' => ['nullable', 'string'],
            'publishableKey' => ['nullable', 'string'],
            'defaultSuccessUrl' => ['nullable', 'string'],
            'defaultCancelUrl' => ['nullable', 'string'],
            'syncChangedUserEmailsToStripe' => ['boolean'],
            'createUserIfMissing' => ['boolean'],
            'productUriFormat' => ['nullable', 'string'],
            'productTemplate' => ['nullable', 'string'],
            ...array_fill_keys(self::LAYOUTS, ['nullable', 'array']),
        ]);

        $saved = $plugins->savePluginSettings($plugin, [
            'secretKey' => (string) ($data['secretKey'] ?? ''),
            'publishableKey' => (string) ($data['publishableKey'] ?? ''),
            'defaultSuccessUrl' => $data['defaultSuccessUrl'] ?? null,
            'defaultCancelUrl' => $data['defaultCancelUrl'] ?? null,
            'syncChangedUserEmailsToStripe' => $request->boolean('syncChangedUserEmailsToStripe'),
            'createUserIfMissing' => $request->boolean('createUserIfMissing'),
            'productUriFormat' => (string) ($data['productUriFormat'] ?? ''),
            'productTemplate' => (string) ($data['productTemplate'] ?? ''),
        ]);

        foreach (self::LAYOUTS as $elementType => $key) {
            if (array_key_exists($key, $data)) {
                $saved = $this->fieldLayouts->saveFieldLayout($elementType, Fields::createLayout($data[$key] ?? [])) && $saved;
            }
        }

        if (! $saved) {
            return $this->asFailure(t('Couldn’t save settings.', category: 'stripe'), [
                'errors' => $settings->errors()->getMessages(),
            ]);
        }

        if ($settings->productUriFormat !== $originalUriFormat) {
            dispatch(new ResaveElements(Product::class, [
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ]));
        }

        return $this->asSuccess(t('Settings saved.', category: 'stripe'));
    }
}
