<?php

namespace App\Models;

use App\Enums\ParentIncomeRange;
use App\Support\PersonNameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpdbParent extends Model
{
    protected $table = 'ppdb_parents';

    protected static function booted(): void
    {
        static::saving(function (PpdbParent $parent): void {
            if (array_key_exists('nama', $parent->getDirty())) {
                $parent->nama = PersonNameNormalizer::normalize($parent->nama);
            }
        });
    }

    protected $fillable = [
        'prospective_student_id',
        'role',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'pendidikan_terakhir',
        'email',
        'nama',
        'no_telp',
        'occupation',
        'income_range',
    ];

    protected function casts(): array
    {
        return [
            'income_range' => ParentIncomeRange::class,
            'tanggal_lahir' => 'date',
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
