<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_exam_requirements', function (Blueprint $table) {
            $table->dropUnique('student_exam_req_exam_level_type_unique');
            $table->unique(
                ['student_exam_id', 'school_level', 'payment_type_id', 'billing_frequency'],
                'student_exam_req_exam_level_type_freq_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasMultiFrequencyRequirements = DB::table('student_exam_requirements')
            ->select('student_exam_id', 'school_level', 'payment_type_id')
            ->groupBy('student_exam_id', 'school_level', 'payment_type_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultiFrequencyRequirements) {
            throw new RuntimeException('Cannot restore the legacy requirement index while multi-frequency requirements exist.');
        }

        Schema::table('student_exam_requirements', function (Blueprint $table) {
            $table->dropUnique('student_exam_req_exam_level_type_freq_unique');
            $table->unique(
                ['student_exam_id', 'school_level', 'payment_type_id'],
                'student_exam_req_exam_level_type_unique'
            );
        });
    }
};
