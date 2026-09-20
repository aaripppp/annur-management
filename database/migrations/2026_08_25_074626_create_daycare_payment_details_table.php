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
        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->default(0)->after('payment_date');
            $table->string('proof_path')->nullable()->after('total_amount');
        });

        Schema::create('daycare_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daycare_payment_id')
                ->constrained('daycare_payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('daycare_payment_id');
        });

        DB::table('daycare_payments')
            ->orderBy('id')
            ->eachById(function (object $payment): void {
                DB::table('daycare_payment_details')->insert([
                    'daycare_payment_id' => $payment->id,
                    'description' => $payment->description,
                    'amount' => $payment->amount,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ]);

                DB::table('daycare_payments')
                    ->where('id', $payment->id)
                    ->update(['total_amount' => $payment->amount]);
            });

        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->dropColumn(['description', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->string('description')->nullable()->after('payment_date');
            $table->decimal('amount', 15, 2)->default(0)->after('description');
        });

        DB::table('daycare_payments')
            ->orderBy('id')
            ->eachById(function (object $payment): void {
                $detail = DB::table('daycare_payment_details')
                    ->where('daycare_payment_id', $payment->id)
                    ->orderBy('id')
                    ->first();

                DB::table('daycare_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'description' => $detail?->description,
                        'amount' => $payment->total_amount,
                    ]);
            });

        Schema::dropIfExists('daycare_payment_details');

        Schema::table('daycare_payments', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'proof_path']);
        });
    }
};
