<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D2: reusable diet templates and independent member-specific diet copies.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diet_templates')) {
            Schema::create('diet_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->index();
                $table->unsignedBigInteger('created_by_user_id')->index();
                $table->string('created_by_type', 30)->default('admin');
                $table->string('title');
                $table->string('goal', 120)->nullable();
                $table->string('diet_type', 60)->nullable();
                $table->unsignedInteger('daily_calories')->nullable();
                $table->unsignedInteger('protein_target')->nullable();
                $table->unsignedInteger('water_target')->nullable();
                $table->text('general_instructions')->nullable();
                $table->boolean('is_shared')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['parent_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('diet_template_meals')) {
            Schema::create('diet_template_meals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diet_template_id')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('meal_time', 30)->nullable();
                $table->string('meal_name', 120);
                $table->text('food_items')->nullable();
                $table->string('quantity', 255)->nullable();
                $table->unsignedInteger('calories')->nullable();
                $table->unsignedInteger('protein')->nullable();
                $table->unsignedInteger('carbs')->nullable();
                $table->unsignedInteger('fats')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('diet_template_id')->references('id')->on('diet_templates')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('member_diets')) {
            Schema::create('member_diets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->index();
                $table->unsignedBigInteger('member_id')->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_by_user_id')->index();
                $table->string('assigned_by_type', 30)->default('admin');
                $table->string('title');
                $table->string('goal', 120)->nullable();
                $table->string('diet_type', 60)->nullable();
                $table->unsignedInteger('daily_calories')->nullable();
                $table->unsignedInteger('protein_target')->nullable();
                $table->unsignedInteger('water_target')->nullable();
                $table->text('general_instructions')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('status', 30)->default('active');
                $table->boolean('is_customized')->default(false);
                $table->timestamps();
                $table->index(['member_id', 'status']);
            });
        }

        if (!Schema::hasTable('member_diet_meals')) {
            Schema::create('member_diet_meals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('member_diet_id')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('meal_time', 30)->nullable();
                $table->string('meal_name', 120);
                $table->text('food_items')->nullable();
                $table->string('quantity', 255)->nullable();
                $table->unsignedInteger('calories')->nullable();
                $table->unsignedInteger('protein')->nullable();
                $table->unsignedInteger('carbs')->nullable();
                $table->unsignedInteger('fats')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('member_diet_id')->references('id')->on('member_diets')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_diet_meals');
        Schema::dropIfExists('member_diets');
        Schema::dropIfExists('diet_template_meals');
        Schema::dropIfExists('diet_templates');
    }
};
