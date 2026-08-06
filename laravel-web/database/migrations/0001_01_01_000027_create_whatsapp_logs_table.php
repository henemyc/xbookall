<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->string('to_number', 20);
            $table->string('template_name', 100)->nullable();
            $table->text('message')->nullable();
            $table->string('message_id', 100)->nullable();
            $table->string('status', 30)->default('sent');
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('to_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
