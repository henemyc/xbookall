<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('trainer_id');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->date('dob')->nullable();
            $table->string('document')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_details');
    }
};
