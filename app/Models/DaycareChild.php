<?php

namespace App\Models;

use Database\Factories\DaycareChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama_lengkap
 * @property string $nama_panggilan
 * @property string $tempat_lahir
 * @property Carbon $tanggal_lahir
 * @property string $jenis_kelamin
 * @property string $alamat
 * @property string $kelas
 * @property string|null $nama_ayah
 * @property string|null $no_telp_ayah
 * @property string|null $nama_ibu
 * @property string|null $no_telp_ibu
 * @property bool $is_active
 */
class DaycareChild extends Model
{
    /** @use HasFactory<DaycareChildFactory> */
    use HasFactory;

    protected $fillable = [
        'nama_lengkap',
        'nama_panggilan',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'kelas',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DaycarePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DaycarePayment::class);
    }
}
