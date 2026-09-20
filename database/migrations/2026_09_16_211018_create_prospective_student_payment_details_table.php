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
        Schema::create('prospective_student_payment_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prospective_student_payment_id')->index('pspd_payment_idx');

            $table->foreign('prospective_student_payment_id', 'pspd_payment_fk')
                ->references('id')
                ->on('prospective_student_payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('prospective_student_bill_id')->index('pspd_bill_idx');

            $table->foreign('prospective_student_bill_id', 'pspd_bill_fk')
                ->references('id')
                ->on('prospective_student_bills')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('payment_type_id')->index('pspd_payment_type_idx');

            $table->foreign('payment_type_id', 'pspd_payment_type_fk')
                ->references('id')
                ->on('payment_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospective_student_payment_details');
    }
};
