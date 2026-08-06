<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->unique();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('order_type', 20); // renew, upgrade
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('CREATED'); // CREATED, ACTIVE, PAID, FAILED, CANCELLED, EXPIRED, USER_DROPPED
            $table->string('link_id', 100)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('cf_order_id', 255)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};
