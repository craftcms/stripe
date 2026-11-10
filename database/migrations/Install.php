<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Database\Migrations;

use CraftCms\Cms\Database\Expressions\JsonExtract;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Stripe\Database\Table;
use CraftCms\Stripe\Elements\Price;
use CraftCms\Stripe\Elements\Product;
use CraftCms\Stripe\Elements\Subscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the same tables as Stripe 1.x, so a 1.x install upgrades without a schema change.
 *
 * @since 2.0.0
 */
class Install extends Migration
{
    public function up(): void
    {
        $this->createElementTable(Table::PRODUCTS);
        $this->createDataTable(Table::PRODUCTDATA, function (Blueprint $table) {
            $table->integer('productId');
            $table->string('stripeId');
            $table->string('stripeStatus');
            $table->unique(['stripeId']);
            $table->foreign('productId')->references('id')->on(Table::PRODUCTS)->cascadeOnDelete()->cascadeOnUpdate();
        });

        $this->createElementTable(Table::PRICES, function (Blueprint $table) {
            $table->integer('primaryOwnerId')->nullable();
            $table->foreign('primaryOwnerId')->references('id')->on(Table::PRODUCTS)->cascadeOnDelete()->cascadeOnUpdate();
        });
        $this->createDataTable(Table::PRICEDATA, function (Blueprint $table) {
            $table->integer('priceId');
            $table->string('stripeId')->nullable();
            $table->string('stripeStatus');
            $table->text('currencies')->nullable();
            $table->float('unitAmount')->nullable();
            $this->generated($table, 'type', 'type');
            $this->generated($table, 'productId', 'product');
            $this->generated($table, 'primaryCurrency', 'currency');
            $table->unique(['stripeId']);
            $table->foreign('priceId')->references('id')->on(Table::PRICES)->cascadeOnDelete()->cascadeOnUpdate();
        });

        $this->createElementTable(Table::SUBSCRIPTIONS);
        $this->createDataTable(Table::SUBSCRIPTIONDATA, function (Blueprint $table) {
            $table->integer('subscriptionId');
            $table->string('stripeId');
            $table->string('stripeStatus');
            $table->text('prices')->nullable();
            $this->generated($table, 'canceledAt', 'canceled_at');
            $table->string('currentPeriodEnd')->nullable()->storedAs(self::currentPeriodEndExpression());
            $this->generated($table, 'customerId', 'customer');
            $this->generated($table, 'latestInvoiceId', 'latest_invoice');
            $this->generated($table, 'startDate', 'start_date');
            $this->generated($table, 'trialStart', 'trial_start');
            $this->generated($table, 'trialEnd', 'trial_end');
            $table->unique(['stripeId']);
            $table->index(['customerId']);
            $table->index(['latestInvoiceId']);
            $table->foreign('subscriptionId')->references('id')->on(Table::SUBSCRIPTIONS)->cascadeOnDelete()->cascadeOnUpdate();
        });

        $this->createDataTable(Table::PAYMENTMETHODDATA, function (Blueprint $table) {
            $table->string('stripeId');
            $this->generated($table, 'customerId', 'customer');
            $table->unique(['stripeId']);
            $table->index(['customerId']);
        });

        $this->createDataTable(Table::CUSTOMERDATA, function (Blueprint $table) {
            $table->string('stripeId');
            $table->string('email');
            $table->dateTime('stripeCreated');
            $table->unique(['stripeId']);
            $table->index(['email']);
        });

        $this->createDataTable(Table::INVOICEDATA, function (Blueprint $table) {
            $table->string('stripeId');
            $this->generated($table, 'created', 'created');
            $this->generated($table, 'customerEmail', 'customer_email');
            $this->generated($table, 'number', 'number');
            $this->generated($table, 'subscriptionId', 'subscription');
            $table->unique(['stripeId']);
            $table->index(['customerEmail']);
            $table->index(['number']);
            $table->index(['subscriptionId']);
        });

        $this->archiveTableIfExists(Table::WEBHOOKS);
        Schema::create(Table::WEBHOOKS, function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('webhookSigningSecret');
            $table->string('webhookId');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->string('uid')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            Table::WEBHOOKS,
            Table::INVOICEDATA,
            Table::CUSTOMERDATA,
            Table::PAYMENTMETHODDATA,
            Table::SUBSCRIPTIONDATA,
            Table::SUBSCRIPTIONS,
            Table::PRICEDATA,
            Table::PRICES,
            Table::PRODUCTDATA,
            Table::PRODUCTS,
        ] as $table) {
            Schema::dropIfExists($table);
        }

        DB::table(CraftTable::FIELDLAYOUTS)
            ->whereIn('type', [Product::class, Price::class, Subscription::class])
            ->delete();
    }

    /**
     * An element table: its ID is the element’s ID.
     */
    private function createElementTable(string $name, ?callable $callback = null): void
    {
        $this->archiveTableIfExists($name);
        Schema::create($name, function (Blueprint $table) use ($callback) {
            $table->integer('id')->primary();
            $table->string('stripeId')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $callback && $callback($table);
            $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * A table of Stripe API objects, with the object’s JSON in `data`.
     */
    private function createDataTable(string $name, callable $callback): void
    {
        $this->archiveTableIfExists($name);
        Schema::create($name, function (Blueprint $table) use ($callback) {
            $table->integer('id', true);
            $table->jsonb('data')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->string('uid')->nullable();
            $callback($table);
        });
    }

    /**
     * A column generated from a top-level key of `data`, which element queries filter and sort by.
     */
    /**
     * Returns the expression for the `currentPeriodEnd` column.
     *
     * Stripe API version `2025-03-31.basil` and later put the billing period on the subscription items. Data synced
     * with Stripe 1.x still has it on the subscription until it’s synced again.
     */
    private static function currentPeriodEndExpression(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "COALESCE(data#>>'{current_period_end}', data#>>'{items,data,0,current_period_end}')",
            'mariadb' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.current_period_end')), JSON_UNQUOTE(JSON_EXTRACT(data, '$.items.data[0].current_period_end')))",
            default => "COALESCE(data->>'$.current_period_end', data->>'$.items.data[0].current_period_end')",
        };
    }

    private function generated(Blueprint $table, string $column, string $key): void
    {
        $table->string($column)->nullable()->storedAs(new JsonExtract('data', $key));
    }
}
