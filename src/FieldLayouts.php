<?php

declare(strict_types=1);

namespace CraftCms\Stripe;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use Illuminate\Container\Attributes\Singleton;

/**
 * Keeps the field layouts of the Stripe element types in project config.
 *
 * The paths are the same as in Stripe 1.x, so existing layouts carry over.
 *
 * @since 2.0.0
 */
#[Singleton]
class FieldLayouts
{
    /**
     * Project config paths, by element type.
     *
     * @var array<class-string, string>
     */
    public const array PATHS = [
        Product::class => 'stripe.productFieldLayout',
        Price::class => 'stripe.priceFieldLayout',
        Subscription::class => 'stripe.subscriptionFieldLayout',
    ];

    public function __construct(
        private readonly ProjectConfig $projectConfig,
    ) {}

    /**
     * Applies project config changes to the field layouts.
     */
    public function registerProjectConfigListeners(): void
    {
        foreach (self::PATHS as $elementType => $path) {
            $this->projectConfig
                ->onAdd($path, fn (ConfigEvent $event) => $this->handleChangedFieldLayout($event, $elementType))
                ->onUpdate($path, fn (ConfigEvent $event) => $this->handleChangedFieldLayout($event, $elementType))
                ->onRemove($path, fn () => Fields::deleteLayoutsByType($elementType));
        }
    }

    /**
     * @param  class-string  $elementType
     */
    public function getFieldLayout(string $elementType): FieldLayout
    {
        return Fields::getLayoutByType($elementType);
    }

    /**
     * Saves an element type’s field layout to project config.
     *
     * @param  class-string  $elementType
     */
    public function saveFieldLayout(string $elementType, FieldLayout $layout): bool
    {
        $layout->type = $elementType;

        if (! $layout->validate()) {
            return false;
        }

        return $this->projectConfig->set(self::PATHS[$elementType], [
            $layout->uid => $layout->getConfig(),
        ], "Save the Stripe {$elementType::lowerDisplayName()} field layout");
    }

    /**
     * @param  class-string  $elementType
     */
    public function handleChangedFieldLayout(ConfigEvent $event, string $elementType): void
    {
        $data = $event->newValue;

        if (empty($data) || empty($config = reset($data))) {
            Fields::deleteLayoutsByType($elementType);

            return;
        }

        ProjectConfigHelper::ensureAllFieldsProcessed();

        $layout = FieldLayout::createFromConfig($config);
        $layout->id = $this->getFieldLayout($elementType)->id;
        $layout->type = $elementType;
        $layout->uid = key($data);
        Fields::saveLayout($layout, false);

        ElementCaches::invalidateForElementType($elementType);
    }
}
