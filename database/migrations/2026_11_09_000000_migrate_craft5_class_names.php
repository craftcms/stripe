<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Json;
use CraftCms\Stripe\Elements\Conditions;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use CraftCms\Stripe\FieldLayoutElements\PricesField;
use CraftCms\Stripe\FieldLayouts;
use CraftCms\Stripe\Fields\Products;
use CraftCms\Stripe\Fields\Subscriptions;
use CraftCms\Stripe\LinkTypes\Product as ProductLinkType;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the Craft 5 (`craft\stripe\*`) class names stored in the database and project config.
 */
return new class extends Migration
{
    /** @var array<string, class-string> */
    private const array MAP = [
        'craft\stripe\elements\Product' => Product::class,
        'craft\stripe\elements\Price' => Price::class,
        'craft\stripe\elements\Subscription' => Subscription::class,
        'craft\stripe\fields\Products' => Products::class,
        'craft\stripe\fields\Subscriptions' => Subscriptions::class,
        'craft\stripe\fieldlayoutelements\PricesField' => PricesField::class,
        'craft\stripe\linktypes\Product' => ProductLinkType::class,
        'craft\stripe\elements\conditions\products\ProductCondition' => Conditions\ProductCondition::class,
        'craft\stripe\elements\conditions\products\ProductPriceTypeConditionRule' => Conditions\ProductPriceTypeConditionRule::class,
        'craft\stripe\elements\conditions\products\StripeStatusConditionRule' => Conditions\StripeStatusConditionRule::class,
        'craft\stripe\elements\conditions\prices\PriceCondition' => Conditions\PriceCondition::class,
        'craft\stripe\elements\conditions\prices\PriceTypeConditionRule' => Conditions\PriceTypeConditionRule::class,
        'craft\stripe\elements\conditions\prices\CurrencyConditionRule' => Conditions\CurrencyConditionRule::class,
        'craft\stripe\elements\conditions\prices\UnitAmountConditionRule' => Conditions\UnitAmountConditionRule::class,
        'craft\stripe\elements\conditions\users\HasStripeCustomerConditionRule' => Conditions\HasStripeCustomerConditionRule::class,
    ];

    public function up(): void
    {
        foreach ([Table::ELEMENTS, Table::FIELDLAYOUTS, Table::FIELDS] as $table) {
            foreach (self::MAP as $old => $new) {
                DB::table($table)->where('type', $old)->update(['type' => $new]);
            }
        }

        foreach ([Table::FIELDLAYOUTS => 'config', Table::FIELDS => 'settings'] as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->lazyById() as $row) {
                $config = Json::decode($row->$column);
                $updated = $this->replace($config);

                if ($updated !== $config) {
                    DB::table($table)->where('id', $row->id)->update([$column => Json::encode($updated)]);
                }
            }
        }

        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($projectConfig->get() ?? [] as $key => $config) {
                $updated = $this->replace($config);

                if ($key === ProjectConfig::PATH_ELEMENT_SOURCES && is_array($updated)) {
                    foreach (self::MAP as $old => $new) {
                        if (array_key_exists($old, $updated) && ! array_key_exists($new, $updated)) {
                            $updated[$new] = $updated[$old];
                            unset($updated[$old]);
                        }
                    }
                }

                if ($updated !== $config) {
                    $projectConfig->set($key, $updated);
                }
            }

            // Craft only migrates the layout element types of the field layouts it knows the project config paths of
            foreach (FieldLayouts::PATHS as $elementType => $path) {
                $layout = Fields::getLayoutByType($elementType);

                if ($layout->id !== null && $projectConfig->get($path) !== null) {
                    $projectConfig->set($path, [$layout->uid => $layout->getConfig()]);
                }
            }

            $projectConfig->saveModifiedConfigData();
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }

    private function replace(mixed $config): mixed
    {
        if (is_string($config)) {
            return self::MAP[$config] ?? $config;
        }

        if (! is_array($config)) {
            return $config;
        }

        foreach ($config as &$value) {
            $value = $this->replace($value);
        }

        return $config;
    }

    public function down(): void {}
};
