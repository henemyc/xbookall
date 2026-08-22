<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_documents')) {
            return;
        }
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // aadhaar_front | aadhaar_back (extensible for more document types)
            $table->string('doc_type', 40);
            $table->string('file_path');
            $table->timestamps();

            $table->index(['user_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_documents');
    }
};
