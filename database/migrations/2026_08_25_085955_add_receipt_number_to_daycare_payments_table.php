<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daycare_receipt_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->string('receipt_number', 30)->nullable()->after('id');
        });

        $sequences = [];

        DB::table('daycare_payments')
            ->orderBy('id')
            ->eachById(function (object $payment) use (&$sequences): void {
                $year = Carbon::parse($payment->payment_date)->year;
                $sequences[$year] = ($sequences[$year] ?? 0) + 1;

                DB::table('daycare_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'receipt_number' => sprintf('KWT-DC-%d-%06d', $year, $sequences[$year]),
                    ]);
            });

        foreach ($sequences as $year => $lastNumber) {
            DB::table('daycare_receipt_sequences')->insert([
                'year' => $year,
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->unique('receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });

        Schema::dropIfExists('daycare_receipt_sequences');
    }
};
