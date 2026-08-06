<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_assigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classes_id');
            $table->unsignedBigInteger('assign_id'); // user_id
            $table->string('assign_type', 20)->default('member'); // member, trainer
            $table->timestamps();

            $table->index('classes_id');
            $table->index('assign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assigns');
    }
};
