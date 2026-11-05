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
        Schema::table('ppdb_siblings', function (Blueprint $table) {
            $table->date('tanggal_lahir')->nullable()->after('nama_lengkap');
            $table->string('jenis_kelamin', 1)->nullable()->after('tanggal_lahir');
            $table->string('pendidikan_pekerjaan', 255)->nullable()->after('jenis_kelamin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_siblings', function (Blueprint $table) {
            $table->dropColumn(['tanggal_lahir', 'jenis_kelamin', 'pendidikan_pekerjaan']);
        });
    }
};
