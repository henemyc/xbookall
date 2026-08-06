<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('package', 50)->nullable(); // monthly, quarterly, half-yearly, yearly
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('classes_id')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
