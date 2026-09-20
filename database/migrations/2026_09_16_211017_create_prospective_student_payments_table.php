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
        Schema::create('prospective_student_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();

            $table->foreignId('prospective_student_id')
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('bank_id')
                ->constrained('banks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('payment_date');
            $table->decimal('total_amount', 15, 2);
            $table->string('receipt')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospective_student_payments');
    }
};
