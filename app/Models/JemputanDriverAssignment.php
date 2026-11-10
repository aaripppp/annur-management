<?php

namespace App\Models;

use Database\Factories\JemputanDriverAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JemputanDriverAssignment extends Model
{
    /** @use HasFactory<JemputanDriverAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'jemputan_driver_id',
        'student_id',
        'academic_year_id',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /** @param Builder<JemputanDriverAssignment> $query */
    public function scopeCurrentMembership(Builder $query): Builder
    {
        return $query->whereNull('effective_until');
    }

    /** @return BelongsTo<JemputanDriver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(JemputanDriver::class, 'jemputan_driver_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
