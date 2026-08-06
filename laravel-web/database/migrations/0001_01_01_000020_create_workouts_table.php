<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->string('assign_to', 20)->default('member');
            $table->unsignedBigInteger('assign_id'); // user_id
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('workout_history')->nullable(); // JSON workout plan
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->timestamps();

            $table->index('assign_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
