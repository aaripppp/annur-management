<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpdbProfile extends Model
{
    protected $fillable = [
        'prospective_student_id',
        'tempat_lahir',
        'tanggal_lahir',
        'asal_sekolah',
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
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'anak_ke' => 'integer',
            'jumlah_saudara' => 'integer',
            'tinggi_badan' => 'decimal:2',
            'berat_badan' => 'decimal:2',
            'lingkar_kepala' => 'decimal:2',
            'berkebutuhan_khusus' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProspectiveStudent, $this>
     */
    public function prospectiveStudent(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudent::class);
    }
}
