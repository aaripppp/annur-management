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
        Schema::table('students', function (Blueprint $table) {
            $table->string('nama_ayah')->nullable();
            $table->string('no_telp_ayah', 50)->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('no_telp_ibu', 50)->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('foto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'nama_ayah',
                'no_telp_ayah',
                'nama_ibu',
                'no_telp_ibu',
                'tempat_lahir',
                'tanggal_lahir',
                'foto',
            ]);
        });
    }
};
