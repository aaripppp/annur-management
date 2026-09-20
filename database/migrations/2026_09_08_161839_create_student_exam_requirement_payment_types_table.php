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
        Schema::create('student_exam_requirement_payment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_requirement_id');
            $table->foreignId('payment_type_id');
            $table->timestamps();

            $table->foreign('student_exam_requirement_id', 'student_exam_req_type_req_fk')
                ->references('id')
                ->on('student_exam_requirements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('payment_type_id', 'student_exam_req_type_payment_fk')
                ->references('id')
                ->on('payment_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unique(
                ['student_exam_requirement_id', 'payment_type_id'],
                'student_exam_req_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_exam_requirement_payment_types');
    }
};
