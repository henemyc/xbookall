<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id'); // Gym-specific invoice number
            $table->unsignedBigInteger('user_id'); // Member ID
            $table->date('invoice_date');
            $table->date('invoice_due_date')->nullable();
            $table->string('status', 20)->default('unpaid'); // paid, partial, unpaid
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
            $table->index('invoice_id');
            $table->index('status');
            $table->index(['parent_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
