<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->string('billing_frequency', 20)->nullable()->after('academic_year');
        });

        // Backfill: derive billing_frequency from existing data.
        // Monthly bills have period_month and period_year set.
        DB::table('student_bills')
            ->whereNotNull('period_month')
            ->whereNotNull('period_year')
            ->update(['billing_frequency' => 'monthly']);

        // Yearly bills have academic_year set (and no period_month/period_year).
        DB::table('student_bills')
            ->whereNull('period_month')
            ->whereNull('period_year')
            ->whereNotNull('academic_year')
            ->update(['billing_frequency' => 'yearly']);

        // Remaining bills (period_month/period_year null, academic_year null)
        // are one-time bills. Also backfill their academic_year from created_at.
        $oneTimeBills = DB::table('student_bills')
            ->whereNull('period_month')
            ->whereNull('period_year')
            ->whereNull('academic_year')
            ->whereNull('billing_frequency')
            ->get();

        $startMonth = 7; // July

        foreach ($oneTimeBills as $bill) {
            $createdAt = $bill->created_at;
            $date = $createdAt ? Carbon::parse($createdAt) : now();
            $academicYear = $date->month >= $startMonth
                ? $date->year.'/'.($date->year + 1)
                : ($date->year - 1).'/'.$date->year;

            DB::table('student_bills')
                ->where('id', $bill->id)
                ->update([
                    'billing_frequency' => 'one_time',
                    'academic_year' => $academicYear,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->dropColumn('billing_frequency');
        });
    }
};
