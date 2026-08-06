<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('gym_name')->nullable();
            $table->string('email')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('screenshot_path')->nullable();
            $table->boolean('has_screenshot')->default(false);
            $table->string('status')->default('open'); // open, in_progress, resolved
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
