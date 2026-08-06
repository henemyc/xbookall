<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_details', function (Blueprint $table) {
            if (!Schema::hasColumn('trainer_details', 'specialization')) {
                $table->string('specialization')->nullable()->after('qualification');
            }
            if (!Schema::hasColumn('trainer_details', 'experience_years')) {
                $table->integer('experience_years')->default(0)->after('specialization');
            }
            if (!Schema::hasColumn('trainer_details', 'joining_date')) {
                $table->date('joining_date')->nullable()->after('experience_years');
            }
            if (!Schema::hasColumn('trainer_details', 'salary')) {
                $table->decimal('salary', 10, 2)->default(0)->after('joining_date');
            }
            if (!Schema::hasColumn('trainer_details', 'bio')) {
                $table->text('bio')->nullable()->after('salary');
            }
            if (!Schema::hasColumn('trainer_details', 'emergency_contact')) {
                $table->string('emergency_contact', 30)->nullable()->after('bio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainer_details', function (Blueprint $table) {
            foreach (['emergency_contact', 'bio', 'salary', 'joining_date', 'experience_years', 'specialization'] as $column) {
                if (Schema::hasColumn('trainer_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
