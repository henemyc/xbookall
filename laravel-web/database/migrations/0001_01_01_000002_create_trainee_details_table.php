<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainee_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('trainee_id');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->date('dob')->nullable();
            $table->integer('age')->default(0);
            $table->string('document')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('fitness_goal')->nullable();
            $table->unsignedBigInteger('membership_plan')->default(0);
            $table->unsignedBigInteger('trainer_assign')->default(0);
            $table->date('membership_start_date')->nullable();
            $table->date('membership_expiry_date')->nullable();
            $table->unsignedBigInteger('category')->default(0);
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->tinyInteger('status')->default(1); // 1=active, 2=expired, 3=frozen
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
            $table->index('membership_expiry_date');
            $table->index('membership_plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_details');
    }
};
