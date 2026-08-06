<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freeze_membership_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainee_id');
            $table->string('plan')->nullable();
            $table->date('membership_start_date')->nullable();
            $table->date('membership_expiry_date')->nullable();
            $table->date('freeze_start_date');
            $table->date('freeze_end_date');
            $table->integer('freeze_days')->default(0);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('trainee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freeze_membership_logs');
    }
};
