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
        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('bill_id')
                ->nullable()
                ->after('payment_id')
                ->constrained('student_bills')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedTinyInteger('period_month')->nullable()->change();
            $table->unsignedSmallInteger('period_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_id');
            $table->unsignedTinyInteger('period_month')->change();
            $table->unsignedSmallInteger('period_year')->change();
        });
    }
};
