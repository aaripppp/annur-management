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
        Schema::create('jemputan_driver_deduction_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('jemputan_driver_id')
                ->constrained('jemputan_drivers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('effective_year');
            $table->unsignedTinyInteger('effective_month');
            $table->decimal('percentage', 5, 2)->unsigned();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['jemputan_driver_id', 'effective_year', 'effective_month'],
                'jddr_driver_effective_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jemputan_driver_deduction_rates');
    }
};
