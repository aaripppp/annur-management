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
        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('payment_type_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('payment_details')->whereNull('payment_type_id')->exists()) {
            throw new RuntimeException('Tidak dapat mewajibkan payment_type_id selama detail pembayaran manual masih ada.');
        }

        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('payment_type_id')->nullable(false)->change();
        });
    }
};
