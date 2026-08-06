<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('web_login_tokens')) {
            Schema::create('web_login_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token', 100)->unique();
                $table->string('browser_session_id', 120)->index();
                $table->string('status', 20)->default('pending')->index(); // pending, approved, used, expired
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('expires_at')->index();
                $table->string('browser_ip', 60)->nullable();
                $table->text('browser_user_agent')->nullable();
                $table->string('approved_ip', 60)->nullable();
                $table->text('approved_user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_login_tokens');
    }
};
