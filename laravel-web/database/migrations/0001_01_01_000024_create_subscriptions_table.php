<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('package_amount', 10, 2)->default(0);
            $table->string('interval', 50)->default('monthly'); // monthly, quarterly, half-yearly, yearly, weekly
            $table->integer('user_limit')->default(0); // 0 = unlimited
            $table->integer('trainer_limit')->default(0);
            $table->integer('trainee_limit')->default(0);
            $table->boolean('enabled_logged_history')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
