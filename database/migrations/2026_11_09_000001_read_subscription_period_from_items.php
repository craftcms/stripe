<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Stripe\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads `currentPeriodEnd` from the subscription items as well, where Stripe API version `2025-03-31.basil` and
 * later put the billing period.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::SUBSCRIPTIONDATA, function (Blueprint $table) {
            $table->dropColumn('currentPeriodEnd');
        });

        Schema::table(Table::SUBSCRIPTIONDATA, function (Blueprint $table) {
            $table->string('currentPeriodEnd')->nullable()->storedAs(match (DB::getDriverName()) {
                'pgsql' => "COALESCE(data#>>'{current_period_end}', data#>>'{items,data,0,current_period_end}')",
                'mariadb' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.current_period_end')), JSON_UNQUOTE(JSON_EXTRACT(data, '$.items.data[0].current_period_end')))",
                default => "COALESCE(data->>'$.current_period_end', data->>'$.items.data[0].current_period_end')",
            });
        });
    }
};
