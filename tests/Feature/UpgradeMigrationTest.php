<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Json;
use CraftCms\Stripe\Elements\Conditions\StripeStatusConditionRule;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\FieldLayoutElements\PricesField;
use CraftCms\Stripe\FieldLayouts;
use CraftCms\Stripe\Fields\Products;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites class names in the database and project config as Craft 5 and Stripe 1.x stored them.
 */
function downgradeClassNames(): void
{
    $craft5 = [
        Product::class => 'craft\stripe\elements\Product',
        Products::class => 'craft\stripe\fields\Products',
        PricesField::class => 'craft\stripe\fieldlayoutelements\PricesField',
        StripeStatusConditionRule::class => 'craft\stripe\elements\conditions\products\StripeStatusConditionRule',
        CustomField::class => 'craft\fieldlayoutelements\CustomField',
    ];

    $encoded = fn (string $class) => substr(Json::encode($class), 1, -1);
    $toCraft5 = fn (string $json) => str_replace(array_map($encoded, array_keys($craft5)), array_map($encoded, $craft5), $json);

    foreach ([Table::ELEMENTS, Table::FIELDLAYOUTS, Table::FIELDS] as $table) {
        foreach ($craft5 as $new => $old) {
            DB::table($table)->where('type', $new)->update(['type' => $old]);
        }
    }

    foreach (DB::table(Table::FIELDLAYOUTS)->whereNotNull('config')->get() as $row) {
        DB::table(Table::FIELDLAYOUTS)->where('id', $row->id)->update(['config' => $toCraft5($row->config)]);
    }

    $projectConfig = app(ProjectConfig::class);
    $projectConfig->muteEvents = true;

    foreach ($projectConfig->get() as $key => $config) {
        $projectConfig->set($key, Json::decode($toCraft5(Json::encode($config))));
    }

    $projectConfig->saveModifiedConfigData();
    $projectConfig->muteEvents = false;
    $projectConfig->reset();
}

it('upgrades the class names Stripe 1.x stored', function () {
    $this->artisan('stripe:sync', ['type' => 'products-and-prices'])->assertSuccessful();

    $field = Fields::createField(['type' => Products::class, 'name' => 'Plans', 'handle' => 'plans']);
    expect(Fields::saveField($field))->toBeTrue();

    app(FieldLayouts::class)->saveFieldLayout(Product::class, Fields::createLayout([
        'tabs' => [[
            'name' => 'Content',
            'elements' => [
                ['type' => PricesField::class],
                ['type' => CustomField::class, 'fieldUid' => $field->uid],
            ],
        ]],
    ]));

    app(ProjectConfig::class)->set(ProjectConfig::PATH_ELEMENT_SOURCES.'.'.Product::class, [[
        'type' => 'custom',
        'key' => 'custom:active',
        'label' => 'Active',
        'condition' => [
            'elementType' => Product::class,
            'conditionRules' => [['class' => StripeStatusConditionRule::class, 'values' => ['active'], 'operator' => 'in']],
        ],
    ]]);

    $productId = Product::find()->stripeId('prod_basic')->one()->id;

    downgradeClassNames();
    expect(Elements::getElementById($productId))->toBeNull()
        ->and(app(ProjectConfig::class)->get(ProjectConfig::PATH_ELEMENT_SOURCES))->toHaveKey('craft\stripe\elements\Product');

    // Core’s migration runs first, and doesn’t know the Stripe layouts’ project config paths
    (require __DIR__.'/../../vendor/craftcms/cms/src/Database/Migrations/2026_09_27_000000_migrate_field_layout_element_types.php')->up();
    (require __DIR__.'/../../database/migrations/2026_11_09_000000_migrate_craft5_class_names.php')->up();
    Fields::refreshFields();
    app(ProjectConfig::class)->reset();

    expect(Elements::getElementById($productId))->toBeInstanceOf(Product::class)
        ->and(Fields::getFieldByHandle('plans'))->toBeInstanceOf(Products::class);

    $layout = Fields::getLayoutByType(Product::class, false);
    expect($layout)->not->toBeNull()
        ->and(array_map(fn ($element) => $element::class, $layout->getTabs()[0]->getElements()))
        ->toBe([PricesField::class, CustomField::class]);

    $projectConfig = app(ProjectConfig::class);
    $layoutConfig = $projectConfig->get(FieldLayouts::PATHS[Product::class]);
    expect(array_column(reset($layoutConfig)['tabs'][0]['elements'], 'type'))->toBe([PricesField::class, CustomField::class])
        ->and($projectConfig->get("fields.$field->uid.type"))->toBe(Products::class);

    $sources = $projectConfig->get(ProjectConfig::PATH_ELEMENT_SOURCES);
    expect($sources)->not->toHaveKey('craft\stripe\elements\Product')
        ->and($sources[Product::class][0]['condition']['conditionRules'][0]['class'])->toBe(StripeStatusConditionRule::class);
});
