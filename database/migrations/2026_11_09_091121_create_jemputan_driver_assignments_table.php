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
        Schema::create('jemputan_driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jemputan_driver_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->foreign('jemputan_driver_id', 'jda_driver_fk')->references('id')->on('jemputan_drivers')->restrictOnDelete();
            $table->foreign('student_id', 'jda_student_fk')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('academic_year_id', 'jda_year_fk')->references('id')->on('academic_years')->restrictOnDelete();

            $table->index(['student_id', 'academic_year_id', 'effective_from', 'effective_until'], 'jda_student_period_idx');
            $table->index(['jemputan_driver_id', 'academic_year_id', 'effective_from'], 'jda_driver_year_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jemputan_driver_assignments');
    }
};
