<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 20); // phone 10 digits
            $table->string('otp_hash');
            $table->string('otp_plain', 10)->nullable();
            $table->string('channel', 20)->default('whatsapp');
            $table->dateTime('expires_at');
            $table->boolean('verified')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('identifier');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
