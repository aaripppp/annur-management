<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $year
 * @property bool $is_active
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $promotion_processed_at
 */
class AcademicYear extends Model
{
    protected $fillable = [
        'year',
        'is_active',
        'start_date',
        'end_date',
        'promotion_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'promotion_processed_at' => 'datetime',
        ];
    }

    /** @return HasMany<StudentAcademicEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentAcademicEnrollment::class);
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Resolve the first academic year chronologically after the active year.
     *
     * Candidates must start strictly after the active year, ordered by the
     * canonical date field (start_date) ascending. Never falls back to a past
     * year, the latest label, or the newest record.
     */
    public static function next(?self $active = null): ?self
    {
        $active ??= static::active();

        if (! $active) {
            return null;
        }

        return static::query()
            ->where('start_date', '>', $active->start_date)
            ->orderBy('start_date')
            ->first();
    }

    public static function deactivateAll(): void
    {
        static::query()->where('is_active', true)->update(['is_active' => false]);
    }

    public function label(): string
    {
        return $this->year;
    }
}
