<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type', 50)->default('info'); // info, warning, error, success
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
