<?php

namespace App\Models;

use Database\Factories\JemputanDriverSalaryAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JemputanDriverSalaryAllocation extends Model
{
    /** @use HasFactory<JemputanDriverSalaryAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'period_year',
        'period_month',
        'jemputan_driver_id',
        'allocated_amount',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'allocated_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<JemputanDriver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(JemputanDriver::class, 'jemputan_driver_id');
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
