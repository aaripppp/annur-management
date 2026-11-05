<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PpdbDefaultClassMapping extends Model
{
    protected $table = 'ppdb_default_class_mappings';

    protected $fillable = [
        'class_level',
        'school_class_id',
    ];

    protected function casts(): array
    {
        return [
            'class_level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PpdbDefaultClassMapping $mapping) {
            if (! in_array((int) $mapping->class_level, self::validClassLevels(), true)) {
                throw ValidationException::withMessages([
                    'class_level' => 'Tingkat kelas tidak valid.',
                ]);
            }

            $schoolClass = $mapping->relationLoaded('schoolClass')
                && $mapping->schoolClass?->getKey() === (int) $mapping->school_class_id
                    ? $mapping->schoolClass
                    : SchoolClass::query()->find($mapping->school_class_id);

            if (! $schoolClass) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'Kelas tidak valid.',
                ]);
            }

            if ((int) $schoolClass->level !== (int) $mapping->class_level) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'Kelas yang dipilih harus memiliki tingkat yang sama dengan class_level.',
                ]);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public static function validClassLevels(): array
    {
        return [-3, -2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    }
}
