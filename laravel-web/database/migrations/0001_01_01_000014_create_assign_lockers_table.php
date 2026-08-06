<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assign_lockers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('locker_id');
            $table->date('assign_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('locker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assign_lockers');
    }
};
