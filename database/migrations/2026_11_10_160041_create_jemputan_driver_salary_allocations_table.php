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
        Schema::create('jemputan_driver_salary_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->foreignId('jemputan_driver_id')
                ->constrained('jemputan_drivers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('allocated_amount', 15, 2)->unsigned();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['student_id', 'academic_year_id', 'period_year', 'period_month', 'jemputan_driver_id'],
                'jdsa_student_period_driver_unique'
            );
            $table->index(
                ['jemputan_driver_id', 'period_year', 'period_month'],
                'jdsa_driver_period_idx'
            );
            $table->index(
                ['student_id', 'academic_year_id', 'period_year', 'period_month'],
                'jdsa_student_period_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jemputan_driver_salary_allocations');
    }
};
