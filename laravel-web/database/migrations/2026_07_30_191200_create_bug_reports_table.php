<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This creates the bug_reports table for the "Report a Bug" feature.
     */
    public function up(): void
    {
        if (!Schema::hasTable('bug_reports')) {
            Schema::create('bug_reports', function (Blueprint $table) {
                $table->id();

                // User & gym context (auto-filled from auth)
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('gym_name')->nullable();
                $table->string('email')->nullable();

                // Bug details
                $table->string('title');
                $table->text('description');

                // Screenshot support
                $table->string('screenshot_path')->nullable();   // just the filename
                $table->boolean('has_screenshot')->default(false);

                // Admin management
                $table->string('status')->default('open');       // open, in_progress, resolved
                $table->text('admin_notes')->nullable();

                $table->timestamps();

                // Performance indexes
                $table->index(['status', 'created_at']);
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
