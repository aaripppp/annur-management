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
            // Student identity
            $table->string('nik_siswa', 16)->nullable()->after('tanggal_lahir');
            $table->string('nomor_kk', 16)->nullable()->after('nik_siswa');
            $table->string('nomor_akta_kelahiran', 100)->nullable()->after('nomor_kk');
            $table->unsignedSmallInteger('anak_ke')->nullable()->after('nomor_akta_kelahiran');
            $table->unsignedSmallInteger('jumlah_saudara')->nullable()->after('anak_ke');
            $table->string('status_anak', 30)->nullable()->after('jumlah_saudara');
            $table->string('agama', 50)->nullable()->after('status_anak');
            $table->string('bahasa_sehari_hari', 100)->nullable()->after('agama');

            // Residential address
            $table->string('rt', 3)->nullable()->after('bahasa_sehari_hari');
            $table->string('rw', 3)->nullable()->after('rt');
            $table->string('kelurahan', 150)->nullable()->after('rw');
            $table->string('kecamatan', 150)->nullable()->after('kelurahan');
            $table->string('kabupaten_kota', 150)->nullable()->after('kecamatan');
            $table->string('provinsi', 150)->nullable()->after('kabupaten_kota');
            $table->string('kode_pos', 5)->nullable()->after('provinsi');

            // Physical / health
            $table->decimal('tinggi_badan', 5, 2)->nullable()->after('kode_pos');
            $table->decimal('berat_badan', 5, 2)->nullable()->after('tinggi_badan');
            $table->decimal('lingkar_kepala', 5, 2)->nullable()->after('berat_badan');
            $table->string('golongan_darah', 5)->nullable()->after('lingkar_kepala');
            $table->text('riwayat_penyakit')->nullable()->after('golongan_darah');
            $table->boolean('berkebutuhan_khusus')->nullable()->after('riwayat_penyakit');
            $table->text('keterangan_kebutuhan_khusus')->nullable()->after('berkebutuhan_khusus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'nik_siswa',
                'nomor_kk',
                'nomor_akta_kelahiran',
                'anak_ke',
                'jumlah_saudara',
                'status_anak',
                'agama',
                'bahasa_sehari_hari',
                'rt',
                'rw',
                'kelurahan',
                'kecamatan',
                'kabupaten_kota',
                'provinsi',
                'kode_pos',
                'tinggi_badan',
                'berat_badan',
                'lingkar_kepala',
                'golongan_darah',
                'riwayat_penyakit',
                'berkebutuhan_khusus',
                'keterangan_kebutuhan_khusus',
            ]);
        });
    }
};
