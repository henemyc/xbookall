<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->time('checked_in_time');
            $table->time('checked_out_time')->nullable();
            $table->tinyInteger('status')->default(1); // 1=checked_in, 2=checked_out
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('date');
            $table->index('parent_id');
            $table->index(['parent_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
