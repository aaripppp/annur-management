<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_rates', function (Blueprint $table) {
            $table->string('billing_frequency', 20)->default('monthly')->after('is_monthly');
        });

        DB::table('payment_rates')
            ->where('is_monthly', false)
            ->update(['billing_frequency' => 'one_time']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_rates', function (Blueprint $table) {
            $table->dropColumn('billing_frequency');
        });
    }
};
