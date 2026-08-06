<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id'); // gym owner user id
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->tinyInteger('status')->default(1); // 1 active, 0 inactive
                $table->timestamps();

                $table->unique(['parent_id', 'name']);
                $table->index('parent_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
    }
};
