<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Stripe\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the invoice `subscriptionId` from `parent.subscription_details` as well, where Stripe API version
 * `2025-03-31.basil` and later put it.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Table::INVOICEDATA, function (Blueprint $table) {
            $table->dropColumn('subscriptionId');
        });

        Schema::table(Table::INVOICEDATA, function (Blueprint $table) {
            $table->string('subscriptionId')->nullable()->storedAs(match (DB::getDriverName()) {
                'pgsql' => "COALESCE(data#>>'{subscription}', data#>>'{parent,subscription_details,subscription}')",
                'mariadb' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.subscription')), JSON_UNQUOTE(JSON_EXTRACT(data, '$.parent.subscription_details.subscription')))",
                default => "COALESCE(data->>'$.subscription', data->>'$.parent.subscription_details.subscription')",
            });
            $table->index(['subscriptionId']);
        });
    }
};
