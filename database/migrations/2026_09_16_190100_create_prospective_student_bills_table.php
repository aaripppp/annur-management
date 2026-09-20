<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospective_student_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('payment_type_id')
                ->constrained('payment_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('billing_frequency', 20)->default('one_time');
            $table->string('academic_year', 9);
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['prospective_student_id', 'payment_type_id', 'academic_year'],
                'prospective_bill_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospective_student_bills');
    }
};