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
        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('role');
            $table->string('tempat_lahir', 150)->nullable()->after('nik');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('pendidikan_terakhir', 100)->nullable()->after('tanggal_lahir');
            $table->string('email', 254)->nullable()->after('pendidikan_terakhir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->dropColumn([
                'nik',
                'tempat_lahir',
                'tanggal_lahir',
                'pendidikan_terakhir',
                'email',
            ]);
        });
    }
};
