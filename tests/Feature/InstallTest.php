<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Stripe\Database\Table;
use Illuminate\Support\Facades\Schema;

it('installs the plugin and its tables', function () {
    expect(Plugins::isPluginInstalled('stripe'))->toBeTrue()
        ->and(Schema::hasTable(Table::PRODUCTDATA))->toBeTrue();
});
