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
        Schema::create('daycare_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daycare_child_id')
                ->constrained('daycare_children')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('bank_id')
                ->constrained('banks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('payment_date');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['daycare_child_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daycare_payments');
    }
};
