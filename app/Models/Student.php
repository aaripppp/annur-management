<?php

namespace App\Models;

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'nis',
        'nama_lengkap',
        'nama_panggilan',
        'class_id',
        'jenis_kelamin',
        'alamat',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
        'tempat_lahir',
        'tanggal_lahir',
        'entry_date',
        'foto',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'tanggal_lahir' => 'date',
            'entry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Student $student) {
            $level = $student->schoolLevel;

            if ($level === null) {
                return;
            }

            $defaultTypeIds = PaymentTypeSchoolLevel::query()
                ->where('school_level', $level)
                ->where('is_active', true)
                ->pluck('payment_type_id');

            if ($defaultTypeIds->isEmpty()) {
                return;
            }

            $activeTypeIds = PaymentType::query()
                ->whereIn('id', $defaultTypeIds)
                ->where('is_active', true)
                ->where('audience', PaymentTypeAudience::Student)
                ->pluck('id');

            foreach ($activeTypeIds as $paymentTypeId) {
                $student->paymentSettings()->firstOrCreate(
                    ['payment_type_id' => $paymentTypeId],
                    ['is_active' => true, 'started_at' => now()->toDateString()]
                );
            }
        });
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function getSchoolLevelAttribute(): ?SchoolLevel
    {
        return $this->schoolClass?->schoolLevel;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    /**
     * Derive academic status from enrollment history.
     *
     * Priority:
     * 1. Aktif — active enrollment in the currently active AcademicYear
     * 2. Calon Siswa — enrollment exists in a future AcademicYear
     * 3. Lulus — graduation is the latest available academic context
     *
     * Does NOT add a new column; derives purely from StudentAcademicEnrollment
     * and AcademicYear records.
     */
    public function academicStatus(): string
    {
        $activeYear = AcademicYear::active();

        if ($activeYear !== null) {
            // Check active enrollment in the current active year
            $hasActiveEnrollment = $this->enrollments()
                ->where('academic_year_id', $activeYear->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveEnrollment) {
                return StudentStatus::Active->value;
            }

            // Check enrollment in an academic year after the active year.
            $hasFutureEnrollment = $this->enrollments()
                ->whereHas('academicYear', function ($query) use ($activeYear) {
                    $query->whereDate('start_date', '>', $activeYear->start_date);
                })
                ->exists();

            if ($hasFutureEnrollment) {
                return 'calon_siswa';
            }
        }

        $hasLulus = $this->enrollments()
            ->where('status', 'lulus')
            ->exists();

        if ($hasLulus) {
            return StudentStatus::Graduated->value;
        }

        // No active year or no matching enrollment — default to aktif for legacy students
        return StudentStatus::Active->value;
    }

    /**
     * Human-readable label for the derived academic status.
     */
    public function getAcademicStatusLabelAttribute(): string
    {
        return match ($this->academicStatus()) {
            StudentStatus::Active->value => 'Aktif',
            StudentStatus::Graduated->value => 'Lulus',
            'calon_siswa' => 'Calon Siswa',
            default => 'Aktif',
        };
    }

    /**
     * Academic class label: for active students show current class, for
     * graduated show last class, for future students show planned entry class.
     */
    public function academicClassLabel(): string
    {
        $status = $this->academicStatus();
        $activeYear = AcademicYear::active();

        if ($status === 'calon_siswa' && $activeYear !== null) {
            $futureEnrollment = $this->enrollments()
                ->with('schoolClass')
                ->whereHas('academicYear', function ($query) use ($activeYear) {
                    $query->whereDate('start_date', '>', $activeYear->start_date);
                })
                ->orderBy(
                    AcademicYear::query()
                        ->select('start_date')
                        ->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id')
                        ->limit(1)
                )
                ->first();

            return $futureEnrollment?->schoolClass?->name ?? $this->schoolClass?->name ?? '-';
        }

        return $this->schoolClass?->name ?? '-';
    }

    /**
     * For future students: get the planned entry academic year label.
     */
    public function entryYearLabel(): ?string
    {
        $activeYear = AcademicYear::active();

        if ($activeYear === null) {
            return null;
        }

        $futureEnrollment = $this->enrollments()
            ->with('academicYear')
            ->whereHas('academicYear', function ($query) use ($activeYear) {
                $query->whereDate('start_date', '>', $activeYear->start_date);
            })
            ->orderBy(
                AcademicYear::query()
                    ->select('start_date')
                    ->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id')
                    ->limit(1)
            )
            ->first();

        return $futureEnrollment?->academicYear?->year;
    }

    public function academicYearContextLabel(): ?string
    {
        /** @var Collection<int, StudentAcademicEnrollment> $enrollments */
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->with('academicYear')->get();
        $activeYear = AcademicYear::active();

        if ($activeYear && $enrollments->contains(
            fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academic_year_id === $activeYear->id
                && $enrollment->status === 'active'
        )) {
            return $activeYear->year;
        }

        if ($activeYear) {
            $futureEnrollment = $enrollments
                ->filter(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academicYear?->start_date?->gt($activeYear->start_date) ?? false)
                ->sortBy(fn (StudentAcademicEnrollment $enrollment): string => $enrollment->academicYear?->start_date?->toDateString() ?? '')
                ->first();

            if ($futureEnrollment) {
                return $futureEnrollment->academicYear?->year;
            }
        }

        return $enrollments
            ->sortByDesc(fn (StudentAcademicEnrollment $enrollment): string => $enrollment->academicYear?->start_date?->toDateString() ?? '')
            ->first()?->academicYear?->year;
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<StudentPaymentSetting, $this>
     */
    public function paymentSettings(): HasMany
    {
        return $this->hasMany(StudentPaymentSetting::class);
    }

    /**
     * @return HasMany<StudentBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(StudentBill::class);
    }

    /**
     * @return HasMany<StudentAcademicEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentAcademicEnrollment::class);
    }

    /**
     * Canonical final-enrollment boundary: the academic year label of the
     * student's latest attended (active) enrollment, by academic year start date.
     *
     * Graduation writes a `lulus` marker enrollment into the year AFTER the
     * student's last active year, so the boundary must be derived from the
     * latest `active` enrollment to reflect actual attendance. Falls back to the
     * latest enrollment overall for legacy marker-only students (no active
     * record). Academic years after this label are outside the student's actual
     * school period and must not show one-time obligations. Returns null for
     * legacy students without any enrollment history.
     */
    public function finalEnrollmentYearLabel(): ?string
    {
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->with('academicYear')->get();

        $dated = $enrollments->filter(
            fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academicYear !== null
        );

        $active = $dated->filter(
            fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->status === 'active'
        );

        $boundarySource = $active->isNotEmpty() ? $active : $dated;

        return $boundarySource
            ->sortByDesc(fn (StudentAcademicEnrollment $enrollment): string => $enrollment->academicYear->start_date->toDateString())
            ->first()
            ?->academicYear?->year;
    }
}
