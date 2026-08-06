<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classes_id');
            $table->string('days'); // e.g., "Mon,Wed,Fri"
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->timestamps();

            $table->index('classes_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
