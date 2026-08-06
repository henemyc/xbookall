<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_app_opened_at')) {
                $table->timestamp('last_app_opened_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'last_app_platform')) {
                $table->string('last_app_platform', 50)->nullable()->after('last_app_opened_at');
            }
            if (!Schema::hasColumn('users', 'last_app_version')) {
                $table->string('last_app_version', 30)->nullable()->after('last_app_platform');
            }
            if (!Schema::hasColumn('users', 'last_app_ip')) {
                $table->string('last_app_ip', 60)->nullable()->after('last_app_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_app_ip', 'last_app_version', 'last_app_platform', 'last_app_opened_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
