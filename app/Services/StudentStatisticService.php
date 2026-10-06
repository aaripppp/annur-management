<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hitung kartu statistik Data Siswa berdasarkan status akademik pada satu
 * tahun ajaran terpilih, dengan jenjang/kelas scoping lewat konteks enrollment.
 *
 * Semua hitungan memakai correlated whereHas/whereDoesntHave (dirender sebagai
 * WHERE EXISTS/NOT EXISTS), sehingga jumlah query konstan berapa pun banyak
 * baris siswa dan tidak pernah memanggil Student::academicStatus() per siswa.
 */
class StudentStatisticService
{
    private const ENROLLMENT_ACTIVE = 'active';

    private const ENROLLMENT_GRADUATED = 'lulus';

    /**
     * Hitung empat kartu statistik untuk satu tahun ajaran.
     *
     * Total bersifat mutually exclusive (jumlah dari tiga kartu status) sehingga
     * deterministik: aktif + calon + lulus.
     *
     * @return array{total: int, active: int, prospective: int, graduated: int}
     */
    public function counts(
        AcademicYear $year,
        ?SchoolLevel $level = null,
        ?int $classId = null,
    ): array {
        $active = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->where(function (Builder $query) use ($year, $level, $classId): void {
                $this->applyActivePredicate($query, $year, $level, $classId);
            })
            ->count();

        $prospective = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('academic_year_id', $year->id)
                ->where('status', self::ENROLLMENT_ACTIVE))
            ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('academic_year_id', $year->id)
                ->where('status', self::ENROLLMENT_GRADUATED))
            ->where(function (Builder $query) use ($year, $level, $classId): void {
                $this->applyProspectivePredicate($query, $year, $level, $classId);
            })
            ->count();

        $graduated = Student::query()
            ->where(function (Builder $query) use ($year, $level, $classId): void {
                $this->applyGraduatedPredicate($query, $year, $level, $classId);
            })
            ->count();

        return [
            'total' => $active + $prospective + $graduated,
            'active' => $active,
            'prospective' => $prospective,
            'graduated' => $graduated,
        ];
    }

    /**
     * Siswa Aktif: enrollment aktif di tahun terpilih, atau fallback data lama
     * yang hanya berlaku bila tahun terpilih adalah tahun ajaran aktif saat ini.
     *
     * Fallback hanya untuk siswa tanpa enrollment apa pun di tahun terpilih dan
     * tanpa enrollment aktif di masa depan, sehingga tidak pernah tumpang tindih
     * dengan kartu Calon Siswa maupun Lulus.
     */
    private function applyActivePredicate(Builder $query, AcademicYear $year, ?SchoolLevel $level, ?int $classId): void
    {
        $query->where(function (Builder $query) use ($year, $level, $classId): void {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($year, $level, $classId): void {
                $enrollments
                    ->where('academic_year_id', $year->id)
                    ->where('status', self::ENROLLMENT_ACTIVE);

                $this->scopeByEnrollmentClass($enrollments, $level, $classId);
            });

            if ($year->is_active) {
                $query->orWhere(function (Builder $legacy) use ($year, $level, $classId): void {
                    $legacy
                        ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments
                            ->where('academic_year_id', $year->id))
                        ->whereDoesntHave('enrollments', function (Builder $enrollments) use ($year): void {
                            $enrollments
                                ->where('status', self::ENROLLMENT_ACTIVE)
                                ->whereHas('academicYear', fn (Builder $academicYear) => $academicYear
                                    ->whereDate('start_date', '>', $year->start_date));
                        });

                    $this->scopeByStoredClass($legacy, $level, $classId);
                });
            }
        });
    }

    /**
     * Calon Siswa: status aktif, tanpa enrollment aktif/lulus di tahun terpilih,
     * tetapi punya enrollment AKTIF di tahun ajaran yang dimulai setelahnya.
     *
     * Enrollment terpilih wajib berstatus active untuk selaras dengan kategori
     * Calon Siswa pada TransactionHistoryService.
     */
    private function applyProspectivePredicate(Builder $query, AcademicYear $year, ?SchoolLevel $level, ?int $classId): void
    {
        $query->whereHas('enrollments', function (Builder $enrollments) use ($year, $level, $classId): void {
            $enrollments
                ->where('status', self::ENROLLMENT_ACTIVE)
                ->whereHas('academicYear', fn (Builder $academicYear) => $academicYear
                    ->whereDate('start_date', '>', $year->start_date));

            $this->scopeByEnrollmentClass($enrollments, $level, $classId);
        });
    }

    /**
     * Lulus: enrollment berstatus lulus di tahun terpilih, atau fallback status
     * stored yang hanya berlaku bila tahun terpilih adalah tahun ajaran aktif.
     */
    private function applyGraduatedPredicate(Builder $query, AcademicYear $year, ?SchoolLevel $level, ?int $classId): void
    {
        $query->where(function (Builder $query) use ($year, $level, $classId): void {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($year, $level, $classId): void {
                $enrollments
                    ->where('academic_year_id', $year->id)
                    ->where('status', self::ENROLLMENT_GRADUATED);

                $this->scopeByEnrollmentClass($enrollments, $level, $classId);
            });

            if ($year->is_active) {
                $query->orWhere(function (Builder $legacy) use ($year, $level, $classId): void {
                    $legacy
                        ->where('status', StudentStatus::Graduated->value)
                        ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments
                            ->where('academic_year_id', $year->id)
                            ->where('status', self::ENROLLMENT_GRADUATED));

                    $this->scopeByStoredClass($legacy, $level, $classId);
                });
            }
        });
    }

    /**
     * Scope jenjang/kelas lewat kelas pada baris enrollment yang sedang ditelusuri.
     */
    private function scopeByEnrollmentClass(Builder $enrollments, ?SchoolLevel $level, ?int $classId): void
    {
        if ($level !== null) {
            $enrollments->whereHas('schoolClass', fn (Builder $schoolClass) => $schoolClass
                ->whereIn('level', $level->classLevels()));
        }

        if ($classId !== null) {
            $enrollments->where('school_class_id', $classId);
        }
    }

    /**
     * Scope jenjang/kelas memakai kelas stored pada siswa untuk fallback data lama
     * yang tidak memiliki baris enrollment (satu-satunya konteks kelas yang ada).
     */
    private function scopeByStoredClass(Builder $students, ?SchoolLevel $level, ?int $classId): void
    {
        if ($level !== null) {
            $students->whereHas('schoolClass', fn (Builder $schoolClass) => $schoolClass
                ->whereIn('level', $level->classLevels()));
        }

        if ($classId !== null) {
            $students->where('class_id', $classId);
        }
    }
}
