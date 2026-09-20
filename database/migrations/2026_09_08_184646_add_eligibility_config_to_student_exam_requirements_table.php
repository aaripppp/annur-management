<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_exam_requirements', function (Blueprint $table) {
            $table->foreignId('student_eligibility_config_id')
                ->nullable()
                ->after('student_exam_id');
            $table->foreignId('source_student_exam_requirement_id')
                ->nullable()
                ->after('student_eligibility_config_id');
            $table->foreignId('student_exam_id')->nullable()->change();

            $table->foreign('student_eligibility_config_id', 'student_exam_req_eligibility_config_fk')
                ->references('id')
                ->on('student_eligibility_configs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('source_student_exam_requirement_id', 'student_exam_req_source_fk')
                ->references('id')
                ->on('student_exam_requirements')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unique('source_student_exam_requirement_id', 'student_exam_req_source_unique');
            $table->unique(
                ['student_eligibility_config_id', 'payment_type_id', 'billing_frequency'],
                'student_exam_req_config_type_freq_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exam_requirements', function (Blueprint $table) {
            $table->dropUnique('student_exam_req_config_type_freq_unique');
            $table->dropUnique('student_exam_req_source_unique');
            $table->dropForeign('student_exam_req_source_fk');
            $table->dropForeign('student_exam_req_eligibility_config_fk');
            $table->dropColumn(['source_student_exam_requirement_id', 'student_eligibility_config_id']);
            $table->foreignId('student_exam_id')->nullable(false)->change();
        });
    }
};
