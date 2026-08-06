<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('type', 20)->default('trainee'); // admin, owner, trainer, trainee
            $table->string('password');
            $table->string('profile')->nullable();
            $table->string('lang', 10)->nullable();
            $table->unsignedBigInteger('subscription')->nullable();
            $table->date('subscription_expire_date')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('remember_token', 100)->nullable();
            $table->string('twofa_secret')->nullable();
            $table->string('api_token', 100)->nullable()->unique();
            $table->string('email_verification_token', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
