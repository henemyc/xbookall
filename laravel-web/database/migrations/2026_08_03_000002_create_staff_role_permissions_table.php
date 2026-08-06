<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_role_permissions')) {
            Schema::create('staff_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_role_id');
                $table->string('permission_key', 120);
                $table->timestamps();

                $table->unique(['staff_role_id', 'permission_key'], 'staff_role_permissions_role_permission_unique');
                $table->index('staff_role_id');
                $table->index('permission_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_role_permissions');
    }
};
