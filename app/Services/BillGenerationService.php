<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear as AcademicYearModel;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Support\AcademicYear;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BillGenerationService
{
    /**
     * Generate buku tagihan siswa untuk satu siklus penagihan.
     *
     * Siklus dimulai dari bulan awal yang dipilih admin dan selalu berakhir
     * pada bulan Juni. Idempotent: bill yang sudah ada dipakai ulang dan
     * nominal hasil edit admin TIDAK ditimpa.
     *
     * Isi buku tagihan:
     * - Bulanan: hanya jenis dengan PaymentTypeSchoolLevel aktif dan rate
     *   bulanan efektif untuk kelas siswa.
     * - Tahunan: satu bill per tahun ajaran untuk setiap jenis tahunan aktif.
     * - Sekali bayar: satu bill per jenis selama masa hidup record siswa.
     *
     * @return array<int, StudentBill> tagihan yang baru dibuat
     */
    public function generateBillbook(
        Student $student,
        CarbonInterface $startDate,
        ?CarbonInterface $monthlyStartDate = null,
    ): array {
        $level = $student->schoolClass?->level;

        if ($level === null) {
            return [];
        }

        $start = Carbon::parse($startDate->toDateString())->startOfMonth();
        $monthlyStart = Carbon::parse(($monthlyStartDate ?? $startDate)->toDateString())->startOfMonth();

        $this->ensureLevelDefaultSettings($student, $start);

        $settings = $student->paymentSettings()
            ->where('is_active', true)
            ->with('paymentType')
            ->get();

        $defaultTypeIds = $this->levelDefaultTypeIds($student);

        $created = [];

        foreach (BillbookPeriod::months($monthlyStart) as $month) {
            foreach ($settings as $setting) {
                $type = $setting->paymentType;

                if (! $defaultTypeIds->contains($type->id)) {
                    continue;
                }

                $rate = $this->resolveRate($type, $level, $month);

                if (! $rate || $rate->billing_frequency !== BillFrequency::Monthly) {
                    continue;
                }

                if (! $this->isPeriodWithinWindow($student, $setting, $month)) {
                    continue;
                }

                $bill = $this->createBill($student, $type, $rate, $month, $this->resolvedAmountFor($setting, $rate));

                if ($bill) {
                    $created[] = $bill;
                }
            }
        }

        $academicYear = AcademicYear::fromDate($start);
        $representative = $academicYear->startDate()->addMonth();

        foreach ($settings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $representative);

            if (! $rate || $rate->billing_frequency !== BillFrequency::Yearly) {
                continue;
            }

            if (! $this->isYearlyPeriodWithinWindow($setting, $representative)) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $representative, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        foreach ($settings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $start);

            if (! $rate || $rate->billing_frequency !== BillFrequency::OneTime) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $start, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Pastikan setting default jenjang tersedia untuk siswa (backfill
     * idempotent). Dipakai untuk siswa lama yang belum memiliki setting.
     *
     * Mapping aktif berlaku untuk semua frequency yang memiliki rate efektif
     * pada kelas siswa dalam siklus target.
     */
    protected function ensureLevelDefaultSettings(Student $student, CarbonInterface $start): void
    {
        $level = $student->schoolLevel;

        if ($level === null) {
            return;
        }

        $classLevel = $student->schoolClass?->level;

        if ($classLevel === null) {
            return;
        }

        $startDate = Carbon::parse($start->toDateString())->startOfDay();
        $endDate = BillbookPeriod::endDateFor($startDate)->endOfMonth();

        $defaultTypeIds = PaymentTypeSchoolLevel::query()
            ->where('school_level', $level)
            ->where('is_active', true)
            ->whereHas('paymentType', fn ($query) => $query
                ->where('is_active', true)
                ->where('audience', PaymentTypeAudience::Student)
                ->whereHas('rates', fn ($rateQuery) => $rateQuery
                    ->where('class_level', $classLevel)
                    ->whereDate('effective_from', '<=', $endDate->toDateString())
                    ->where(function ($effectiveQuery) use ($startDate): void {
                        $effectiveQuery->whereNull('effective_until')
                            ->orWhereDate('effective_until', '>=', $startDate->toDateString());
                    })))
            ->pluck('payment_type_id');

        foreach ($defaultTypeIds as $typeId) {
            $student->paymentSettings()->firstOrCreate(
                ['payment_type_id' => $typeId],
                ['is_active' => true, 'started_at' => $start->toDateString()]
            );
        }
    }

    /**
     * Jenis pembayaran default untuk jenjang siswa.
     *
     * @return Collection<int, int>
     */
    protected function levelDefaultTypeIds(Student $student): Collection
    {
        $level = $student->schoolLevel;

        if ($level === null) {
            return collect();
        }

        return $this->levelDefaultTypeIdsForLevel($level);
    }

    /**
     * Jenis pembayaran default berdasarkan jenjang (SchoolLevel).
     *
     * @return Collection<int, int>
     */
    protected function levelDefaultTypeIdsForLevel(SchoolLevel $level): Collection
    {
        return PaymentTypeSchoolLevel::query()
            ->where('school_level', $level)
            ->where('is_active', true)
            ->whereHas('paymentType', fn ($query) => $query
                ->where('is_active', true)
                ->where('audience', PaymentTypeAudience::Student))
            ->pluck('payment_type_id');
    }

    /**
     * Generate tagihan untuk satu siswa pada periode target.
     *
     * Idempotent: tidak membuat duplicate bill untuk periode yang sama.
     *
     * @param  bool  $includeYearly  false untuk mencegah pembuatan bill tahunan
     *                               (dipakai generateUntil yang menangani tahun
     *                               ajaran secara terpisah)
     * @return array<int, StudentBill> tagihan yang baru dibuat
     */
    public function generateForStudent(Student $student, ?CarbonInterface $targetDate = null, bool $includeYearly = true): array
    {
        $targetDate ??= Carbon::now();

        $level = $student->schoolClass?->level;

        if ($level === null) {
            return [];
        }

        $activeSettings = $student->paymentSettings()
            ->where('is_active', true)
            ->with('paymentType')
            ->get();

        $created = [];

        foreach ($activeSettings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $targetDate);

            if (! $rate) {
                continue;
            }

            if ($rate->billing_frequency === BillFrequency::Yearly) {
                if (! $includeYearly || ! $this->isYearlyPeriodWithinWindow($setting, $targetDate)) {
                    continue;
                }
            } elseif ($rate->is_monthly && ! $this->isPeriodWithinWindow($student, $setting, $targetDate)) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $targetDate, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Generate tagihan untuk setiap periode bulanan dari periode berjalan
     * sampai periode tujuan (inclusive).
     *
     * Idempotent: bill yang sudah ada untuk sebuah periode akan dipakai ulang,
     * hanya bill yang belum ada yang dibuat. Tipe non-monthly (mis. Uang
     * Pangkal) hanya dibuat sekali selama masih ada bill relevan yang belum
     * lunas.
     *
     * Tagihan tahunan ditangani terpisah: satu bill per tahun ajaran yang
     * "berlaku" dalam rentang. Tahun ajaran baru dianggap layak mulai bulan
     * setelah bulan awal tahun ajaran (mis. target Agustus 2027 -> 2027/2028;
     * target Juli 2027 -> masih 2026/2027).
     *
     * @return array<int, StudentBill> tagihan yang baru dibuat
     */
    public function generateUntil(Student $student, ?CarbonInterface $untilDate = null): array
    {
        $until = Carbon::parse(($untilDate ?? Carbon::now())->toDateString())->startOfMonth();
        $current = Carbon::now()->startOfMonth();

        $start = $current->lessThanOrEqualTo($until) ? $current->copy() : $until->copy();
        $end = $current->lessThanOrEqualTo($until) ? $until->copy() : $current->copy();

        $created = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            foreach ($this->generateForStudent($student, $cursor->copy(), includeYearly: false) as $bill) {
                $created[] = $bill;
            }
        }

        $created = array_merge($created, $this->generateYearlyBillsForRange($student, $start, $end));

        return $created;
    }

    /**
     * Buat tagihan tahunan untuk setiap tahun ajaran yang berlaku dalam
     * rentang [start, end].
     *
     * Tahun ajaran dihitung dari tanggal cursor; ketika cursor tepat berada di
     * bulan awal tahun ajaran (Juli), tahun ajaran yang berlaku adalah yang
     * berjalan sebelumnya (mis. Juli 2027 -> 2026/2027). Tahun ajaran baru baru
     * layak mulai bulan berikutnya (mis. Agustus 2027 -> 2027/2028).
     *
     * @return array<int, StudentBill>
     */
    protected function generateYearlyBillsForRange(Student $student, CarbonInterface $start, CarbonInterface $end): array
    {
        $academicYears = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $academicYear = AcademicYear::fromDate($cursor);
            if ($cursor->month === AcademicYear::START_MONTH) {
                $academicYear = new AcademicYear($academicYear->startYear() - 1);
            }

            $academicYears[$academicYear->label()] = $academicYear;
        }

        $created = [];

        foreach ($academicYears as $academicYear) {
            foreach ($this->generateYearlyBillForAcademicYear($student, $academicYear) as $bill) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Buat (atau pakai ulang) bill tahunan untuk satu tahun ajaran.
     *
     * Tanggal representatif = satu bulan setelah awal tahun ajaran (mis.
     * 2026-08-01 untuk 2026/2027), dipakai sebagai acuan resolusi rate dan
     * jendela eligibility — berada di dalam tahun ajaran dan rate seeder sudah
     * berlaku.
     *
     * @return array<int, StudentBill>
     */
    protected function generateYearlyBillForAcademicYear(Student $student, AcademicYear $academicYear): array
    {
        $level = $student->schoolClass?->level;

        if ($level === null) {
            return [];
        }

        $representative = $academicYear->startDate()->addMonth();

        $activeSettings = $student->paymentSettings()
            ->where('is_active', true)
            ->with('paymentType')
            ->get();

        $created = [];

        foreach ($activeSettings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $representative);

            if (! $rate || $rate->billing_frequency !== BillFrequency::Yearly) {
                continue;
            }

            if (! $this->isYearlyPeriodWithinWindow($setting, $representative)) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $representative, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Preview data untuk generate tagihan bulanan secara manual.
     *
     * Tidak melakukan mutasi database — hanya menghitung siswa yang layak,
     * tagihan yang akan dibuat, tagihan yang sudah ada (skip), dan tarif
     * master yang berlaku.
     *
     * @return array{
     *     academic_year: string,
     *     eligible_students: int,
     *     will_create: int,
     *     already_existing: int,
     *     monthly_bills_to_create: array<int, array{student_id: int, student_name: string, payment_type: string, class_level: int, amount: float, period_month: int, period_year: int}>,
     *     monthly_bills_existing: array<int, array{student_id: int, student_name: string, payment_type: string, period_month: int, period_year: int}>,
     *     tariffs: array<int, array{payment_type: string, class_level: int, amount: float}>,
     * }
     */
    public function getMonthlyGenerationPreview(AcademicYearModel $academicYear): array
    {
        $enrollments = $this->getEligibleEnrollmentsForGeneration($academicYear);
        $months = $this->getMonthlyPeriods($academicYear);

        $billsToCreate = [];
        $billsExisting = [];
        $tariffs = [];
        $tariffKeyed = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $enrollmentLevel = $enrollment->schoolClass?->level;

            if ($enrollmentLevel === null) {
                continue;
            }

            $schoolLevel = $enrollment->schoolClass->school_level;

            if ($schoolLevel === null) {
                continue;
            }

            $defaultTypeIds = $this->levelDefaultTypeIdsForLevel($schoolLevel);

            foreach ($defaultTypeIds as $typeId) {
                $type = PaymentType::find($typeId);

                if (! $type) {
                    continue;
                }

                $rate = $this->resolveRate($type, $enrollmentLevel, $academicYear->start_date);

                if (! $rate || $rate->billing_frequency !== BillFrequency::Monthly) {
                    continue;
                }

                $rateKey = $type->id.'_'.$enrollmentLevel;

                if (! isset($tariffKeyed[$rateKey])) {
                    $tariffKeyed[$rateKey] = true;
                    $tariffs[] = [
                        'payment_type' => $type->name,
                        'class_level' => $enrollmentLevel,
                        'amount' => (float) $rate->amount,
                    ];
                }

                $setting = $student->paymentSettings()
                    ->where('payment_type_id', $typeId)
                    ->first();

                if ($setting && ! $setting->is_active) {
                    continue;
                }

                $setting ??= new StudentPaymentSetting([
                    'student_id' => $student->id,
                    'payment_type_id' => $typeId,
                    'is_active' => true,
                    'started_at' => $academicYear->start_date,
                ]);

                foreach ($months as $month) {
                    if (! $this->isPeriodWithinWindow($student, $setting, $month)) {
                        continue;
                    }

                    $exists = StudentBill::query()
                        ->where('student_id', $student->id)
                        ->where('payment_type_id', $typeId)
                        ->where('period_month', $month->month)
                        ->where('period_year', $month->year)
                        ->exists();

                    $amount = $this->resolvedAmountFor($setting, $rate);

                    if ($exists) {
                        $billsExisting[] = [
                            'student_id' => $student->id,
                            'student_name' => $student->nama_lengkap,
                            'payment_type' => $type->name,
                            'period_month' => $month->month,
                            'period_year' => $month->year,
                        ];
                    } else {
                        $billsToCreate[] = [
                            'student_id' => $student->id,
                            'student_name' => $student->nama_lengkap,
                            'payment_type' => $type->name,
                            'class_level' => $enrollmentLevel,
                            'amount' => $amount,
                            'period_month' => $month->month,
                            'period_year' => $month->year,
                        ];
                    }
                }
            }
        }

        return [
            'academic_year' => $academicYear->year,
            'eligible_students' => $enrollments->count(),
            'will_create' => count($billsToCreate),
            'already_existing' => count($billsExisting),
            'monthly_bills_to_create' => $billsToCreate,
            'monthly_bills_existing' => $billsExisting,
            'tariffs' => $tariffs,
        ];
    }

    /**
     * Generate tagihan bulanan secara manual untuk satu tahun ajaran.
     *
     * Hanya membuat tagihan yang belum ada (idempotent). Menggunakan tarif
     * master (PaymentRate) terkini sebagai snapshot ke StudentBill.amount.
     * Tagihan yang sudah ada TIDAK ditimpa.
     *
     * @return array{created: int, skipped: int}
     */
    public function generateMonthlyForAcademicYear(AcademicYearModel $academicYear): array
    {
        $enrollments = $this->getEligibleEnrollmentsForGeneration($academicYear);
        $months = $this->getMonthlyPeriods($academicYear);

        $created = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $enrollmentLevel = $enrollment->schoolClass?->level;

            if ($enrollmentLevel === null) {
                continue;
            }

            $schoolLevel = $enrollment->schoolClass->school_level;

            if ($schoolLevel === null) {
                continue;
            }

            $defaultTypeIds = $this->levelDefaultTypeIdsForLevel($schoolLevel);

            foreach ($defaultTypeIds as $typeId) {
                $type = PaymentType::find($typeId);

                if (! $type) {
                    continue;
                }

                $rate = $this->resolveRate($type, $enrollmentLevel, $academicYear->start_date);

                if (! $rate || $rate->billing_frequency !== BillFrequency::Monthly) {
                    continue;
                }

                $setting = $student->paymentSettings()
                    ->where('payment_type_id', $typeId)
                    ->first();

                if ($setting && ! $setting->is_active) {
                    continue;
                }

                $setting ??= $student->paymentSettings()->firstOrCreate(
                    ['payment_type_id' => $typeId],
                    ['is_active' => true, 'started_at' => $academicYear->start_date->toDateString()]
                );

                foreach ($months as $month) {
                    if (! $this->isPeriodWithinWindow($student, $setting, $month)) {
                        $skipped++;

                        continue;
                    }

                    $bill = $this->createBill(
                        $student,
                        $type,
                        $rate,
                        $month,
                        $this->resolvedAmountFor($setting, $rate),
                        $schoolLevel,
                    );

                    if ($bill) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Generate hanya tagihan yearly + one-time (tanpa monthly).
     *
     * Dipakai sebagai bagian dari inisialisasi siswa masa depan. Tagihan
     * bulanan selain periode Juli digenerate secara manual oleh admin melalui
     * alur "Generate Tagihan Bulanan".
     *
     * @return array<int, StudentBill> tagihan yang baru dibuat
     */
    public function generateYearlyAndOneTimeOnly(Student $student, CarbonInterface $startDate): array
    {
        $level = $student->schoolClass?->level;

        if ($level === null) {
            return [];
        }

        $start = Carbon::parse($startDate->toDateString())->startOfMonth();

        $this->ensureLevelDefaultSettings($student, $start);

        $settings = $student->paymentSettings()
            ->where('is_active', true)
            ->with('paymentType')
            ->get();

        $academicYear = AcademicYear::fromDate($start);
        $representative = $academicYear->startDate()->addMonth();

        $created = [];

        // Yearly bills
        foreach ($settings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $representative);

            if (! $rate || $rate->billing_frequency !== BillFrequency::Yearly) {
                continue;
            }

            if (! $this->isYearlyPeriodWithinWindow($setting, $representative)) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $representative, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        // One-time bills
        foreach ($settings as $setting) {
            $type = $setting->paymentType;

            $rate = $this->resolveRate($type, $level, $start);

            if (! $rate || $rate->billing_frequency !== BillFrequency::OneTime) {
                continue;
            }

            $bill = $this->createBill($student, $type, $rate, $start, $this->resolvedAmountFor($setting, $rate));

            if ($bill) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Generate tagihan awal calon siswa untuk tahun ajaran yang dipilih.
     *
     * Tagihan tahunan dan sekali bayar tetap mengikuti alur yang sudah ada,
     * kemudian ditambah semua komponen bulanan yang berlaku untuk bulan Juli
     * pada tahun mulai akademik. Kelas enrollment dipakai agar tarif mengikuti
     * konteks tahun ajaran tersebut, bukan kelas siswa yang dapat berubah.
     *
     * @return array<int, StudentBill> tagihan yang baru dibuat
     */
    public function generateInitialAcademicYearBills(Student $student, AcademicYearModel $academicYear): array
    {
        $created = $this->generateYearlyAndOneTimeOnly($student, $academicYear->start_date);
        $enrollment = StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->with('schoolClass')
            ->first();
        $schoolClass = $enrollment?->schoolClass;
        $schoolLevel = $schoolClass?->school_level;
        $classLevel = $schoolClass?->level;

        if ($schoolLevel === null || $classLevel === null) {
            return $created;
        }

        $defaultTypeIds = $this->levelDefaultTypeIdsForLevel($schoolLevel);
        $settings = $student->paymentSettings()
            ->whereIn('payment_type_id', $defaultTypeIds)
            ->where('is_active', true)
            ->with('paymentType')
            ->get();
        $july = Carbon::create((int) $academicYear->start_date->year, AcademicYear::START_MONTH, 1)->startOfMonth();

        foreach ($settings as $setting) {
            $type = $setting->paymentType;
            $rate = $this->resolveRate($type, (int) $classLevel, $july);

            if ($rate === null || $rate->billing_frequency !== BillFrequency::Monthly) {
                continue;
            }

            $bill = $this->createBill(
                $student,
                $type,
                $rate,
                $july,
                $this->resolvedAmountFor($setting, $rate),
                $schoolLevel,
            );

            if ($bill !== null) {
                $created[] = $bill;
            }
        }

        return $created;
    }

    /**
     * Dapatkan enrollment yang layak mendapat tagihan bulanan manual.
     *
     * Enrollment layak jika status aktif di tahun ajaran target DAN
     * siswanya bukan lulusan. Mengembalikan collection StudentAcademicEnrollment
     * dengan eager-load student.schoolClass dan schoolClass.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    protected function getEligibleEnrollmentsForGeneration(AcademicYearModel $academicYear): Collection
    {
        return StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->with(['student.schoolClass', 'schoolClass'])
            ->get()
            ->filter(fn (StudentAcademicEnrollment $e) => $e->student !== null)
            ->values();
    }

    /**
     * Daftar bulan (awal bulan) dalam satu tahun ajaran, dari start_date
     * sampai end_date (inklusif).
     *
     * @return array<int, Carbon>
     */
    protected function getMonthlyPeriods(AcademicYearModel $academicYear): array
    {
        $start = $academicYear->start_date->copy()->startOfMonth();
        $end = $academicYear->end_date->copy()->startOfMonth();

        $months = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor = $cursor->addMonth()) {
            $months[] = $cursor->copy();
        }

        return $months;
    }

    /**
     * Cek apakah periode target berada dalam jendela mulai/selesai setting.
     *
     * Hanya untuk bill bulanan; bill non-monthly (mis. Uang Pangkal) tidak
     * dibatasi. started_at/ended_at dibandingkan per bulan, sehingga bill
     * untuk bulan yang sama dengan mulai/selesai tetap diizinkan.
     */
    protected function isPeriodWithinWindow(
        Student $student,
        StudentPaymentSetting $setting,
        CarbonInterface $targetDate,
    ): bool {
        $targetPeriod = Carbon::parse($targetDate->toDateString())->startOfMonth();
        $studentEffectiveDate = $student->entry_date ?? $student->created_at;
        $studentEffectiveStart = $studentEffectiveDate
            ? Carbon::parse($studentEffectiveDate)->startOfMonth()
            : null;

        if ($studentEffectiveStart !== null && $targetPeriod->lessThan($studentEffectiveStart)) {
            return false;
        }

        if ($setting->started_at !== null
            && $targetPeriod->lessThan($setting->started_at->copy()->startOfMonth())) {
            return false;
        }

        if ($setting->ended_at !== null
            && $targetPeriod->greaterThan($setting->ended_at->copy()->startOfMonth())) {
            return false;
        }

        return true;
    }

    /**
     * Cek kelayakan bill tahunan: jendela aktif setting (started_at/ended_at)
     * harus tumpang-tindih (overlap) dengan tahun ajaran target.
     *
     * - started_at lebih lambat dari akhir tahun ajaran -> belum layak.
     * - ended_at lebih awal dari awal tahun ajaran -> sudah tidak layak.
     */
    protected function isYearlyPeriodWithinWindow(StudentPaymentSetting $setting, CarbonInterface $targetDate): bool
    {
        $academicYear = AcademicYear::fromDate($targetDate);

        if ($setting->started_at !== null
            && $setting->started_at->startOfDay()->greaterThan($academicYear->endDate())) {
            return false;
        }

        if ($setting->ended_at !== null
            && $setting->ended_at->endOfDay()->lessThan($academicYear->startDate())) {
            return false;
        }

        return true;
    }

    /**
     * Pilih rate yang berlaku pada tanggal target.
     *
     * - effective_from harus <= tanggal target.
     * - effective_until NULL berarti masih berlaku; jika terisi tidak boleh
     *   melewati tanggal target.
     * - Jika ada lebih dari satu rate cocok untuk class_level yang sama, pilih
     *   rate dengan effective_from terbaru.
     */
    public function resolveRate(PaymentType $type, int $level, CarbonInterface $targetDate): ?PaymentRate
    {
        return PaymentRate::query()
            ->where('payment_type_id', $type->id)
            ->where('class_level', $level)
            ->whereDate('effective_from', '<=', $targetDate->toDateString())
            ->where(function ($query) use ($targetDate) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $targetDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Nominal akhir untuk satu tagihan: custom_amount siswa jika diisi,
     * selain itu nominal dari payment rate default.
     */
    protected function resolvedAmountFor(StudentPaymentSetting $setting, PaymentRate $rate): float
    {
        return $setting->custom_amount !== null ? (float) $setting->custom_amount : (float) $rate->amount;
    }

    /**
     * Jenis pembayaran otomatis harus aktif dan memiliki mapping aktif untuk
     * jenjang yang sedang digenerate.
     */
    protected function isBillbookType(PaymentType $type, Student $student, ?SchoolLevel $targetSchoolLevel = null): bool
    {
        if (! $type->is_active || $type->audience !== PaymentTypeAudience::Student) {
            return false;
        }

        $schoolLevel = $targetSchoolLevel ?? $student->schoolLevel;

        return $schoolLevel !== null
            && PaymentTypeSchoolLevel::query()
                ->where('payment_type_id', $type->id)
                ->where('school_level', $schoolLevel)
                ->where('is_active', true)
                ->exists();
    }

    /**
     * Buat bill jika belum ada (application-level duplicate detection).
     *
     * $amount adalah snapshot nominal yang berlaku saat bill dibuat (dari
     * custom_amount siswa atau rate default). Mengubah custom_amount di
     * kemudian hari TIDAK mengubah bill yang sudah dibuat.
     *
     * - Monthly (is_monthly): kunci (student_id, payment_type_id, period_month,
     *   period_year).
     * - Yearly: kunci (student_id, payment_type_id, academic_year). Satu bill
     *   per tahun ajaran; period_month/period_year tetap NULL.
     * - One-time: kunci (student_id, payment_type_id) selama masih ada bill
     *   relevan (belum lunas). academic_year diisi dari target academic year.
     */
    protected function createBill(
        Student $student,
        PaymentType $type,
        PaymentRate $rate,
        CarbonInterface $targetDate,
        float $amount,
        ?SchoolLevel $targetSchoolLevel = null,
    ): ?StudentBill {
        // Guard tunggal: semua jalur pembuatan bill (bulanan, tahunan, sekali
        // bayar) melewati metode ini. Tipe opsional tidak pernah dibuat
        // otomatis — hanya tipe yang dikonfigurasi untuk jenjang target.
        if (! $this->isBillbookType($type, $student, $targetSchoolLevel)) {
            return null;
        }

        if ($rate->billing_frequency === BillFrequency::Yearly) {
            $academicYear = AcademicYear::fromDate($targetDate)->label();

            $exists = StudentBill::query()
                ->where('student_id', $student->id)
                ->where('payment_type_id', $type->id)
                ->where('academic_year', $academicYear)
                ->exists();

            if ($exists) {
                return null;
            }

            return StudentBill::create([
                'student_id' => $student->id,
                'payment_type_id' => $type->id,
                'amount' => $amount,
                'period_month' => null,
                'period_year' => null,
                'academic_year' => $academicYear,
                'billing_frequency' => BillFrequency::Yearly->value,
                'due_date' => null,
            ]);
        }

        if ($rate->is_monthly) {
            $exists = StudentBill::query()
                ->where('student_id', $student->id)
                ->where('payment_type_id', $type->id)
                ->where('period_month', $targetDate->month)
                ->where('period_year', $targetDate->year)
                ->exists();

            if ($exists) {
                return null;
            }

            return StudentBill::create([
                'student_id' => $student->id,
                'payment_type_id' => $type->id,
                'amount' => $amount,
                'period_month' => $targetDate->month,
                'period_year' => $targetDate->year,
                'billing_frequency' => BillFrequency::Monthly->value,
                'due_date' => null,
            ]);
        }

        // One-time bill: lifetime dedup by student + type. A single bill per
        // student + payment_type is enough forever — settled or not.
        $academicYear = AcademicYear::fromDate($targetDate)->label();

        $exists = StudentBill::query()
            ->where('student_id', $student->id)
            ->where('payment_type_id', $type->id)
            ->where('billing_frequency', BillFrequency::OneTime->value)
            ->exists();

        if ($exists) {
            return null;
        }

        return StudentBill::create([
            'student_id' => $student->id,
            'payment_type_id' => $type->id,
            'amount' => $amount,
            'period_month' => null,
            'period_year' => null,
            'academic_year' => $academicYear,
            'billing_frequency' => BillFrequency::OneTime->value,
            'due_date' => null,
        ]);
    }
}
