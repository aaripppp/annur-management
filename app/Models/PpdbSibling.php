<?php

namespace App\Models;

use App\Support\PersonNameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpdbSibling extends Model
{
    protected $table = 'ppdb_siblings';

    protected static function booted(): void
    {
        static::saving(function (PpdbSibling $sibling): void {
            if (array_key_exists('nama_lengkap', $sibling->getDirty())) {
                $raw = $sibling->getRawOriginal('nama_lengkap');
                $current = $sibling->getAttributes()['nama_lengkap'] ?? null;
                if (is_string($current) && is_string($raw) && $current === $raw) {
                    return;
                }
                $sibling->nama_lengkap = PersonNameNormalizer::normalize($sibling->nama_lengkap);
            }
        });
    }

    protected $fillable = [
        'prospective_student_id',
        'sort_order',
        'nama_lengkap',
        'tanggal_lahir',
        'jenis_kelamin',
        'pendidikan_pekerjaan',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
