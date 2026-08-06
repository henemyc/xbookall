<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'staff_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_role_id')->nullable()->after('parent_id')->index();
            });
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            });
        }

        if (!Schema::hasColumn('users', 'last_login_ip')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_login_ip', 60)->nullable()->after('last_login_at');
            });
        }

        if (!Schema::hasColumn('users', 'last_login_user_agent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('last_login_user_agent')->nullable()->after('last_login_ip');
            });
        }

        if (!Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable()->after('last_login_user_agent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'staff_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('staff_role_id');
            });
        }
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_login_at');
            });
        }
        if (Schema::hasColumn('users', 'last_login_ip')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_login_ip');
            });
        }
        if (Schema::hasColumn('users', 'last_login_user_agent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_login_user_agent');
            });
        }
        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};
