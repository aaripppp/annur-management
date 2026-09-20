<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Membuat siswa baru dan membangun buku tagihan awal secara otomatis.
 *
 * Alur: buat siswa -> pastikan setting berdasarkan konfigurasi jenjang
 * -> generate buku tagihan dari bulan awal
 * terkonfigurasi sampai Juni. Seluruh proses berjalan dalam satu transaksi
 * database.
 *
 * Siswa dapat memiliki tahun ajaran masuk yang berbeda dari tahun ajaran
 * aktif saat ini (siswa masa depan). Dalam hal ini, buku tagihan dibuat
 * untuk tahun ajaran masuk siswa, bukan tahun ajaran aktif.
 *
 * Dipakai oleh alur normal Student Management dan siap dipakai ulang oleh
 * alur import Excel di masa depan.
 */
class StudentCreationService
{
    public function __construct(
        private readonly BillGenerationService $billGenerationService,
        private readonly StudentNisConflictService $studentNisConflictService,
    ) {}

    /**
     * @param  array<string, mixed>  $data  atribut siswa yang sudah divalidasi.
     *                                      Kunci opsional: entry_academic_year_id
     *                                      (int) — ID tahun ajaran masuk siswa.
     *                                      Jika tidak disediakan, tahun ajaran
     *                                      aktif saat ini dipakai sebagai default.
     */
    public function create(array $data): Student
    {
        $entryAcademicYearId = $data['entry_academic_year_id'] ?? null;
        unset($data['entry_academic_year_id']);

        $hasEntryDate = filled($data['entry_date'] ?? null);

        if (! $hasEntryDate) {
            $data['entry_date'] = now()->toDateString();
        }

        return DB::transaction(function () use ($data, $entryAcademicYearId, $hasEntryDate): Student {
            $entryYear = $entryAcademicYearId
                ? AcademicYear::query()->findOrFail((int) $entryAcademicYearId)
                : null;
            $targetAcademicYear = $entryYear ?? AcademicYear::active();
            $schoolClass = isset($data['class_id']) ? SchoolClass::query()->findOrFail((int) $data['class_id']) : null;
            $nis = is_string($data['nis'] ?? null) ? trim($data['nis']) : '';

            if ($targetAcademicYear && $schoolClass && $nis !== '') {
                $this->studentNisConflictService->ensureNoConflict(
                    $nis,
                    $targetAcademicYear,
                    $schoolClass,
                );
            }

            $student = Student::create($data);

            $activeYear = AcademicYear::active();
            $isFutureStudent = $entryYear && $activeYear && $entryYear->id !== $activeYear->id;

            if ($isFutureStudent) {
                $start = $entryYear->start_date;
                $monthlyStart = $hasEntryDate
                    ? Carbon::parse($student->entry_date)->startOfMonth()
                    : $start->copy()->startOfMonth();

                if ($monthlyStart->lessThan($start)) {
                    $monthlyStart = $start->copy()->startOfMonth();
                }
            } else {
                $start = BillbookPeriod::startDate();
                $creationMonth = $student->created_at->copy()->startOfMonth();

                if ($start->lessThan($creationMonth)) {
                    $start = $creationMonth;
                }

                $monthlyStart = $hasEntryDate
                    ? Carbon::parse($student->entry_date)->startOfMonth()
                    : $start->copy();
                $minMonthlyStart = Carbon::create(
                    $hasEntryDate ? $student->entry_date->year : $monthlyStart->year,
                    6,
                    1,
                );

                if ($monthlyStart->lessThan($minMonthlyStart)) {
                    $monthlyStart = $minMonthlyStart->copy();
                }
            }

            $this->initializeAcademicEntry($student, $entryYear, $start, $isFutureStudent, true, $monthlyStart);

            return $student;
        });
    }

    /**
     * Apply the production enrollment, payment-setting, and initial billing
     * flow to a student that has already been persisted.
     */
    public function initializeAcademicEntry(
        Student $student,
        ?AcademicYear $entryYear,
        CarbonInterface $start,
        bool $yearlyAndOneTimeOnly,
        bool $adjustSettingsStart = false,
        ?CarbonInterface $monthlyStart = null,
    ): void {
        $this->createEnrollment($student, $entryYear);

        if ($adjustSettingsStart) {
            $this->adjustSettingsStart($student, $start);

            if ($monthlyStart !== null) {
                $this->adjustMonthlySettingsStart($student, $monthlyStart);
            }
        }

        if ($yearlyAndOneTimeOnly && $entryYear !== null) {
            $this->billGenerationService->generateInitialAcademicYearBills($student, $entryYear);
        } elseif ($yearlyAndOneTimeOnly) {
            $this->billGenerationService->generateYearlyAndOneTimeOnly($student, $start);
        } else {
            $this->billGenerationService->generateBillbook($student, $start, $monthlyStart);
        }
    }

    /**
     * Buat enrollment siswa untuk tahun ajaran masuk.
     *
     * Jika tahun ajaran masuk ditentukan, buat enrollment untuk tahun
     * tersebut. Jika tidak, buat enrollment untuk tahun ajaran aktif
     * saat ini.
     */
    protected function createEnrollment(Student $student, ?AcademicYear $entryYear): void
    {
        $year = $entryYear ?? AcademicYear::active();

        if ($year === null) {
            return;
        }

        StudentAcademicEnrollment::firstOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
            ],
            [
                'school_class_id' => $student->class_id,
                'status' => 'active',
            ]
        );
    }

    /**
     * Update semua setting pembayaran siswa agar started_at sesuai dengan
     * tahun ajaran masuk. Ini mencegah pembuatan tagihan untuk tahun
     * ajaran yang salah jika generateForStudent dipanggil sebelum tahun
     * ajaran masuk dimulai.
     */
    protected function adjustSettingsStart(Student $student, CarbonInterface $start): void
    {
        $student->paymentSettings()
            ->update(['started_at' => $start]);
    }

    protected function adjustMonthlySettingsStart(Student $student, CarbonInterface $start): void
    {
        $level = $student->schoolClass?->level;

        if ($level === null) {
            return;
        }

        $student->paymentSettings()
            ->whereHas('paymentType.rates', fn ($query) => $query
                ->where('class_level', $level)
                ->where('billing_frequency', BillFrequency::Monthly))
            ->update(['started_at' => $start]);
    }
}
