<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_orders', 'gateway')) {
                $table->string('gateway', 50)->nullable()->after('amount')->index();
            }
            if (!Schema::hasColumn('subscription_orders', 'gateway_order_id')) {
                $table->string('gateway_order_id', 150)->nullable()->after('gateway');
            }
            if (!Schema::hasColumn('subscription_orders', 'gateway_payment_id')) {
                $table->string('gateway_payment_id', 150)->nullable()->after('gateway_order_id');
            }
            if (!Schema::hasColumn('subscription_orders', 'gateway_status')) {
                $table->string('gateway_status', 50)->nullable()->after('gateway_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            foreach (['gateway_status', 'gateway_payment_id', 'gateway_order_id', 'gateway'] as $column) {
                if (Schema::hasColumn('subscription_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
