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
        Schema::table('jemputan_drivers', function (Blueprint $table) {
            $table->decimal('foundation_deduction_percentage', 5, 2)->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jemputan_drivers', function (Blueprint $table) {
            $table->dropColumn('foundation_deduction_percentage');
        });
    }
};
