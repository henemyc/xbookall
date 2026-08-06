<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->default(0); // gym owner id
                $table->unsignedBigInteger('user_id')->nullable(); // staff/admin who performed action
                $table->string('user_type', 30)->nullable();
                $table->string('module', 80);
                $table->string('action', 80);
                $table->string('record_type', 120)->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->text('description')->nullable();
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('ip', 60)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index('parent_id');
                $table->index('user_id');
                $table->index(['module', 'action']);
                $table->index(['record_type', 'record_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
