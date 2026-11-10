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
        Schema::table('ppdb_profiles', function (Blueprint $table) {
            $table->string('jarak_tempuh_kategori')->nullable();
            $table->decimal('jarak_tempuh_detail', 6, 2)->nullable();
            $table->string('moda_transportasi')->nullable();
            $table->string('moda_transportasi_lainnya', 255)->nullable();
            $table->string('hobi', 255)->nullable();
            $table->string('cita_cita', 255)->nullable();
            $table->string('nisn', 10)->nullable();
        });

        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->string('status_hidup')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->dropColumn('status_hidup');
        });

        Schema::table('ppdb_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'jarak_tempuh_kategori',
                'jarak_tempuh_detail',
                'moda_transportasi',
                'moda_transportasi_lainnya',
                'hobi',
                'cita_cita',
                'nisn',
            ]);
        });
    }
};
