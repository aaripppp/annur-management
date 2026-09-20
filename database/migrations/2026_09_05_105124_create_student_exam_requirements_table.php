<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_id')->constrained('student_exams')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('school_level', 8);
            $table->foreignId('payment_type_id')->constrained('payment_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('billing_frequency', 20);
            $table->date('start_month')->nullable();
            $table->date('end_month')->nullable();
            $table->decimal('required_percentage', 5, 2)->default(100);
            $table->timestamps();

            $table->unique(['student_exam_id', 'school_level', 'payment_type_id'], 'student_exam_req_exam_level_type_unique');
            $table->index(['student_exam_id', 'school_level'], 'student_exam_req_exam_level_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_requirements');
    }
};
