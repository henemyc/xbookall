<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('installation_id', 80)->nullable()->after('token_hash');
            $table->index(['user_id', 'installation_id'], 'device_tokens_user_installation_index');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex('device_tokens_user_installation_index');
            $table->dropColumn('installation_id');
        });
    }
};
