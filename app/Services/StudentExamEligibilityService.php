<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentExam;
use App\Models\StudentExamRequirement;
use App\Models\StudentPaymentSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentExamEligibilityService
{
    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_MISSING_BILL = 'missing_bill';

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, mixed>>
     */
    public function summaries(StudentExam $exam, Collection $students): array
    {
        $exam->loadMissing(['academicYear', 'requirements.paymentType', 'requirements.pooledPaymentTypes']);

        return $this->evaluateStudents($exam->academicYear, $exam->requirements, $students, false, $exam->name);
    }

    /** @return array<string, mixed> */
    public function evaluate(StudentExam $exam, Student $student): array
    {
        $exam->loadMissing(['academicYear', 'requirements.paymentType', 'requirements.pooledPaymentTypes']);

        return $this->evaluateStudents($exam->academicYear, $exam->requirements, collect([$student]), true, $exam->name)[$student->id];
    }

    /**
     * @param  Collection<int, StudentExamRequirement>  $requirements
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, mixed>>
     */
    public function criteriaSummaries(AcademicYear $academicYear, Collection $requirements, Collection $students): array
    {
        $this->loadRequirementRelations($requirements);

        return $this->evaluateStudents($academicYear, $requirements, $students, false, 'Kriteria Kelayakan Aktif');
    }

    /**
     * @param  Collection<int, StudentExamRequirement>  $requirements
     * @return array<string, mixed>
     */
    public function evaluateCriteria(AcademicYear $academicYear, Collection $requirements, Student $student): array
    {
        $this->loadRequirementRelations($requirements);

        return $this->evaluateStudents($academicYear, $requirements, collect([$student]), true, 'Kriteria Kelayakan Aktif')[$student->id];
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, mixed>>
     */
    private function evaluateStudents(
        AcademicYear $academicYear,
        Collection $requirements,
        Collection $students,
        bool $includeDetails,
        string $contextName,
    ): array {
        $studentIds = $students->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        if ($studentIds === []) {
            return [];
        }

        $pooledMemberKeys = $requirements
            ->filter(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() > 1)
            ->flatMap(fn (StudentExamRequirement $requirement): Collection => $requirement->pooledPaymentTypes->map(
                fn (PaymentType $paymentType): string => $requirement->school_level->value.'|'.$requirement->billing_frequency->value.'|'.$paymentType->id
            ));
        $requirements = $requirements
            ->where('is_active', true)
            ->reject(
                fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() < 2
                    && $pooledMemberKeys->containsStrict($requirement->school_level->value.'|'.$requirement->billing_frequency->value.'|'.$requirement->payment_type_id)
            )->values();
        $paymentTypeIds = $requirements
            ->flatMap(fn (StudentExamRequirement $requirement): Collection => $this->requirementPaymentTypes($requirement)->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $enrollments = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('student_id', $studentIds)
            ->with('schoolClass')
            ->get()
            ->keyBy('student_id');
        $levels = $requirements->pluck('school_level')->map(
            fn (SchoolLevel $level): string => $level->value
        )->unique()->values()->all();
        $mappings = PaymentTypeSchoolLevel::query()
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->whereIn('school_level', $levels)
            ->get()
            ->keyBy(fn (PaymentTypeSchoolLevel $mapping): string => $this->mappingKey($mapping->school_level, $mapping->payment_type_id));
        $settings = StudentPaymentSetting::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->get()
            ->keyBy(fn (StudentPaymentSetting $setting): string => $this->studentTypeKey($setting->student_id, $setting->payment_type_id));
        $classLevels = $enrollments->map(function (StudentAcademicEnrollment $enrollment): ?int {
            $schoolClass = $enrollment->getRelation('schoolClass');

            return $schoolClass instanceof SchoolClass ? (int) $schoolClass->level : null;
        })->filter(fn (?int $level): bool => $level !== null)->unique()->values()->all();
        $rates = [];

        foreach (PaymentRate::query()
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->whereIn('class_level', $classLevels)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get() as $rate) {
            $rateKey = $rate->payment_type_id.'|'.$rate->class_level.'|'.$rate->billing_frequency->value;
            $rates[$rateKey] ??= collect();
            $rates[$rateKey]->push($rate);
        }
        $bills = $this->loadBills($academicYear, $studentIds, $requirements);
        $requirementsByLevel = $requirements->groupBy(
            fn (StudentExamRequirement $requirement): string => $requirement->school_level->value
        );
        $results = [];

        foreach ($students as $student) {
            $enrollment = $enrollments->get($student->id);
            $schoolClass = $enrollment instanceof StudentAcademicEnrollment
                ? $enrollment->getRelation('schoolClass')
                : null;
            $schoolLevel = $schoolClass instanceof SchoolClass ? $schoolClass->school_level : null;
            $studentRequirements = $schoolLevel instanceof SchoolLevel
                ? $requirementsByLevel->get($schoolLevel->value, collect())
                : collect();
            $results[$student->id] = $this->evaluateStudent(
                $academicYear,
                $contextName,
                $student,
                $schoolClass instanceof SchoolClass ? $schoolClass : null,
                $schoolLevel,
                $studentRequirements,
                $mappings,
                $settings,
                $rates,
                $bills,
                $includeDetails,
            );
        }

        return $results;
    }

    /**
     * @param  Collection<int, StudentExamRequirement>  $requirements
     * @param  Collection<string, PaymentTypeSchoolLevel>  $mappings
     * @param  Collection<string, StudentPaymentSetting>  $settings
     * @param  array<string, Collection<int, PaymentRate>>  $rates
     * @param  Collection<string, StudentBill>  $bills
     * @return array<string, mixed>
     */
    private function evaluateStudent(
        AcademicYear $academicYear,
        string $contextName,
        Student $student,
        ?SchoolClass $schoolClass,
        ?SchoolLevel $schoolLevel,
        Collection $requirements,
        Collection $mappings,
        Collection $settings,
        array $rates,
        Collection $bills,
        bool $includeDetails,
    ): array {
        $evaluations = [];

        if ($schoolClass !== null && $schoolLevel !== null) {
            foreach ($requirements as $requirement) {
                foreach ($this->requirementPeriods($academicYear, $requirement) as $period) {
                    $evaluations[] = $this->evaluateRequirement(
                        $academicYear,
                        $student,
                        $schoolClass,
                        $schoolLevel,
                        $requirement,
                        $period,
                        $mappings,
                        $settings,
                        $rates,
                        $bills,
                    );
                }
            }
        }

        $applicable = array_values(array_filter(
            $evaluations,
            fn (array $evaluation): bool => $evaluation['status'] !== self::STATUS_NOT_APPLICABLE
        ));
        $satisfiedCount = count(array_filter(
            $applicable,
            fn (array $evaluation): bool => $evaluation['status'] === self::STATUS_PASS
        ));
        $applicableCount = count($applicable);
        $hasConfiguration = $schoolClass !== null && $schoolLevel !== null && $requirements->isNotEmpty();
        $isEligible = $hasConfiguration && $satisfiedCount === $applicableCount;
        $progress = $applicableCount > 0 ? round(($satisfiedCount / $applicableCount) * 100, 1) : 100.0;
        $unmetReasons = array_values(array_filter(array_map(
            fn (array $evaluation): ?string => in_array($evaluation['status'], [self::STATUS_FAIL, self::STATUS_MISSING_BILL], true)
                ? $evaluation['reason']
                : null,
            $evaluations
        )));

        if (! $hasConfiguration) {
            $progress = 0.0;
            $isEligible = false;
            $unmetReasons[] = $schoolClass === null
                ? 'Data kelas siswa untuk tahun ajaran ujian belum tersedia.'
                : 'Syarat kartu ujian untuk jenjang '.$schoolLevel?->value.' belum dikonfigurasi.';
        }

        $result = [
            'student_id' => (int) $student->id,
            'student_name' => (string) $student->nama_lengkap,
            'nis' => (string) $student->nis,
            'exam_name' => $contextName,
            'academic_year' => $academicYear->year,
            'class_id' => $schoolClass?->id,
            'class_name' => $schoolClass instanceof SchoolClass ? $schoolClass->name : '—',
            'school_level' => $schoolLevel?->value,
            'satisfied_count' => $satisfiedCount,
            'applicable_count' => $applicableCount,
            'progress_percentage' => $progress,
            'progress_label' => $this->percentageLabel($progress),
            'is_eligible' => $isEligible,
            'status' => $isEligible ? 'eligible' : 'not_eligible',
            'status_label' => $isEligible ? 'Memenuhi' : 'Belum Memenuhi',
        ];

        if ($includeDetails) {
            $result['requirements'] = $evaluations;
            $result['monthly_periods'] = $this->monthlyPeriods($evaluations);
            $result['monthly_rows'] = $this->monthlyRows($evaluations);
            $result['non_monthly_requirements'] = array_values(array_filter(
                $evaluations,
                fn (array $evaluation): bool => $evaluation['billing_frequency'] !== BillFrequency::Monthly->value
            ));
            $result['unmet_reasons'] = $unmetReasons;
        }

        return $result;
    }

    /**
     * @param  array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}  $period
     * @param  Collection<string, PaymentTypeSchoolLevel>  $mappings
     * @param  Collection<string, StudentPaymentSetting>  $settings
     * @param  array<string, Collection<int, PaymentRate>>  $rates
     * @param  Collection<string, StudentBill>  $bills
     * @return array<string, mixed>
     */
    private function evaluateRequirement(
        AcademicYear $academicYear,
        Student $student,
        SchoolClass $schoolClass,
        SchoolLevel $schoolLevel,
        StudentExamRequirement $requirement,
        array $period,
        Collection $mappings,
        Collection $settings,
        array $rates,
        Collection $bills,
    ): array {
        $paymentTypes = $this->requirementPaymentTypes($requirement);

        if ($paymentTypes->count() > 1) {
            return $this->pooledFinancialResult(
                $academicYear,
                $student,
                $schoolClass,
                $schoolLevel,
                $requirement,
                $paymentTypes,
                $period,
                $mappings,
                $settings,
                $rates,
                $bills,
            );
        }

        $frequency = $requirement->billing_frequency;
        $billKey = $this->billKey(
            $student->id,
            $requirement->payment_type_id,
            $frequency,
            $period['month'],
            $period['year'],
            (string) $academicYear->year,
        );
        $bill = $bills->get($billKey);

        if ($bill instanceof StudentBill) {
            return $this->financialResult($requirement, $period, $bill);
        }

        $mapping = $mappings->get($this->mappingKey($schoolLevel, $requirement->payment_type_id));
        $setting = $settings->get($this->studentTypeKey($student->id, $requirement->payment_type_id));
        $rate = $this->applicableRate(
            $rates,
            (int) $requirement->payment_type_id,
            $frequency,
            (int) $schoolClass->level,
            $period['date'],
            $frequency === BillFrequency::Monthly
                ? $period['date']
                : CarbonImmutable::parse($academicYear->end_date)->endOfDay(),
            $setting instanceof StudentPaymentSetting ? $setting : null,
        );
        $isMapped = $mapping instanceof PaymentTypeSchoolLevel && $mapping->is_active;
        $isSettingApplicable = $setting instanceof StudentPaymentSetting
            && $setting->is_active
            && $this->isWithinSettingWindow($student, $setting, $frequency, $period['date'], $academicYear);

        if (! $isMapped || $rate === null || ($setting instanceof StudentPaymentSetting && ! $isSettingApplicable)) {
            return $this->statusResult(
                $requirement,
                $period,
                self::STATUS_NOT_APPLICABLE,
                0.0,
                0.0,
                0.0,
                'Tidak berlaku untuk siswa pada periode ini.',
            );
        }

        if (! $setting instanceof StudentPaymentSetting && ! $mapping->is_required) {
            return $this->statusResult(
                $requirement,
                $period,
                self::STATUS_NOT_APPLICABLE,
                0.0,
                0.0,
                0.0,
                'Tidak berlaku untuk siswa pada periode ini.',
            );
        }

        return $this->statusResult(
            $requirement,
            $period,
            self::STATUS_MISSING_BILL,
            0.0,
            0.0,
            0.0,
            'Tagihan '.$this->paymentTypeName($requirement).' '.$period['label'].' seharusnya berlaku tetapi StudentBill belum tersedia.',
        );
    }

    /**
     * @param  Collection<int, PaymentType>  $paymentTypes
     * @param  array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}  $period
     * @param  Collection<string, PaymentTypeSchoolLevel>  $mappings
     * @param  Collection<string, StudentPaymentSetting>  $settings
     * @param  array<string, Collection<int, PaymentRate>>  $rates
     * @param  Collection<string, StudentBill>  $bills
     * @return array<string, mixed>
     */
    private function pooledFinancialResult(
        AcademicYear $academicYear,
        Student $student,
        SchoolClass $schoolClass,
        SchoolLevel $schoolLevel,
        StudentExamRequirement $requirement,
        Collection $paymentTypes,
        array $period,
        Collection $mappings,
        Collection $settings,
        array $rates,
        Collection $bills,
    ): array {
        $memberResults = $paymentTypes->map(fn (PaymentType $paymentType): array => $this->evaluatePooledMember(
            $academicYear,
            $student,
            $schoolClass,
            $schoolLevel,
            $requirement,
            $paymentType,
            $period,
            $mappings,
            $settings,
            $rates,
            $bills,
        ))->values();
        $applicableMembers = $memberResults->where('status', '!=', self::STATUS_NOT_APPLICABLE);
        $targetCents = $applicableMembers->sum(fn (array $member): int => $this->moneyToCents($member['target']));
        $paidCents = $applicableMembers->sum(fn (array $member): int => $this->moneyToCents($member['paid']));
        $requiredBasisPoints = (int) round((float) $requirement->required_percentage * 100);
        $requiredCents = (int) ceil(($targetCents * $requiredBasisPoints) / 10_000);
        $target = $targetCents / 100;
        $paid = $paidCents / 100;
        $percentage = $targetCents > 0 ? ($paidCents / $targetCents) * 100 : 100.0;
        $missingMembers = $memberResults->where('status', self::STATUS_MISSING_BILL);

        if ($missingMembers->isNotEmpty()) {
            $status = self::STATUS_MISSING_BILL;
            $reason = 'Tagihan '.implode(', ', $missingMembers->pluck('payment_type_name')->all()).' '.$period['label'].' seharusnya berlaku tetapi StudentBill belum tersedia.';
        } elseif ($applicableMembers->isEmpty()) {
            $status = self::STATUS_NOT_APPLICABLE;
            $reason = 'Tidak berlaku untuk siswa pada periode ini.';
        } else {
            $status = $paidCents >= $requiredCents ? self::STATUS_PASS : self::STATUS_FAIL;
            $reason = $status === self::STATUS_FAIL
                ? 'Syarat gabungan '.$this->paymentTypeName($requirement).' baru '.$this->percentageLabel($percentage).', syarat minimal '.$this->percentageLabel((float) $requirement->required_percentage).'.'
                : null;
        }

        return $this->statusResult(
            $requirement,
            $period,
            $status,
            $target,
            $paid,
            $percentage,
            $reason,
            null,
            $memberResults->all(),
            $requiredCents / 100,
        );
    }

    /**
     * @param  array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}  $period
     * @param  Collection<string, PaymentTypeSchoolLevel>  $mappings
     * @param  Collection<string, StudentPaymentSetting>  $settings
     * @param  array<string, Collection<int, PaymentRate>>  $rates
     * @param  Collection<string, StudentBill>  $bills
     * @return array<string, mixed>
     */
    private function evaluatePooledMember(
        AcademicYear $academicYear,
        Student $student,
        SchoolClass $schoolClass,
        SchoolLevel $schoolLevel,
        StudentExamRequirement $requirement,
        PaymentType $paymentType,
        array $period,
        Collection $mappings,
        Collection $settings,
        array $rates,
        Collection $bills,
    ): array {
        $frequency = $requirement->billing_frequency;
        $bill = $bills->get($this->billKey(
            $student->id,
            $paymentType->id,
            $frequency,
            $period['month'],
            $period['year'],
            (string) $academicYear->year,
        ));

        if ($bill instanceof StudentBill) {
            $target = max(0.0, round((float) $bill->amount + (float) ($bill->exam_adjustments_total ?? 0), 2));
            $paid = min($target, max(0.0, round((float) ($bill->exam_paid_total ?? 0), 2)));

            return [
                'payment_type_id' => (int) $paymentType->id,
                'payment_type_name' => $paymentType->name,
                'bill_id' => (int) $bill->id,
                'target' => $target,
                'paid' => $paid,
                'percentage' => $target > 0 ? round(($paid / $target) * 100, 2) : 100.0,
                'status' => 'applicable',
            ];
        }

        $mapping = $mappings->get($this->mappingKey($schoolLevel, $paymentType->id));
        $setting = $settings->get($this->studentTypeKey($student->id, $paymentType->id));
        $rate = $this->applicableRate(
            $rates,
            $paymentType->id,
            $frequency,
            (int) $schoolClass->level,
            $period['date'],
            $frequency === BillFrequency::Monthly
                ? $period['date']
                : CarbonImmutable::parse($academicYear->end_date)->endOfDay(),
            $setting instanceof StudentPaymentSetting ? $setting : null,
        );
        $isMapped = $mapping instanceof PaymentTypeSchoolLevel && $mapping->is_active;
        $isSettingApplicable = $setting instanceof StudentPaymentSetting
            && $setting->is_active
            && $this->isWithinSettingWindow($student, $setting, $frequency, $period['date'], $academicYear);
        $isNotApplicable = ! $isMapped
            || $rate === null
            || ($setting instanceof StudentPaymentSetting && ! $isSettingApplicable)
            || (! $setting instanceof StudentPaymentSetting && ! $mapping?->is_required);

        return [
            'payment_type_id' => (int) $paymentType->id,
            'payment_type_name' => $paymentType->name,
            'bill_id' => null,
            'target' => 0.0,
            'paid' => 0.0,
            'percentage' => 0.0,
            'status' => $isNotApplicable ? self::STATUS_NOT_APPLICABLE : self::STATUS_MISSING_BILL,
        ];
    }

    /**
     * @param  array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}  $period
     * @return array<string, mixed>
     */
    private function financialResult(StudentExamRequirement $requirement, array $period, StudentBill $bill): array
    {
        $target = max(0.0, round((float) $bill->amount + (float) ($bill->exam_adjustments_total ?? 0), 2));
        $allocated = max(0.0, round((float) ($bill->exam_paid_total ?? 0), 2));
        $paid = min($target, $allocated);
        $percentage = $target > 0 ? ($paid / $target) * 100 : 100.0;
        $requiredPercentage = (float) $requirement->required_percentage;
        $status = $percentage + 0.000001 >= $requiredPercentage ? self::STATUS_PASS : self::STATUS_FAIL;
        $reason = $status === self::STATUS_FAIL
            ? $this->paymentTypeName($requirement).' '.$period['label'].' baru '.$this->percentageLabel($percentage).', syarat minimal '.$this->percentageLabel($requiredPercentage).'.'
            : null;

        return $this->statusResult($requirement, $period, $status, $target, $paid, $percentage, $reason, $bill->id);
    }

    /**
     * @param  array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}  $period
     * @return array<string, mixed>
     */
    private function statusResult(
        StudentExamRequirement $requirement,
        array $period,
        string $status,
        float $target,
        float $paid,
        float $percentage,
        ?string $reason,
        ?int $billId = null,
        array $memberResults = [],
        ?float $requiredAmount = null,
    ): array {
        $paymentTypes = $this->requirementPaymentTypes($requirement);

        return [
            'requirement_id' => (int) $requirement->id,
            'payment_type_id' => (int) $requirement->payment_type_id,
            'payment_type_name' => $this->paymentTypeName($requirement),
            'billing_frequency' => $requirement->billing_frequency->value,
            'period_key' => $period['key'],
            'period_label' => $period['label'],
            'bill_id' => $billId,
            'target' => $target,
            'paid' => $paid,
            'percentage' => round($percentage, 2),
            'percentage_label' => $this->percentageLabel($percentage),
            'required_percentage' => (float) $requirement->required_percentage,
            'required_percentage_label' => $this->percentageLabel((float) $requirement->required_percentage),
            'required_amount' => $requiredAmount ?? round($target * (float) $requirement->required_percentage / 100, 2),
            'is_pooled' => $paymentTypes->count() > 1,
            'member_payment_type_ids' => $paymentTypes->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'member_payment_type_names' => $paymentTypes->pluck('name')->values()->all(),
            'member_results' => $memberResults,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'reason' => $reason,
        ];
    }

    /**
     * @return list<array{key: string, label: string, date: CarbonImmutable, month: ?int, year: ?int}>
     */
    private function requirementPeriods(AcademicYear $academicYear, StudentExamRequirement $requirement): array
    {
        if ($requirement->billing_frequency !== BillFrequency::Monthly) {
            $date = CarbonImmutable::parse($academicYear->start_date)->startOfDay();

            return [[
                'key' => $requirement->billing_frequency->value.'|'.$academicYear->year,
                'label' => 'Tahun Ajaran '.$academicYear->year,
                'date' => $date,
                'month' => null,
                'year' => null,
            ]];
        }

        if ($requirement->start_month === null || $requirement->end_month === null) {
            return [];
        }

        $periods = [];
        $cursor = CarbonImmutable::parse($requirement->start_month)->startOfMonth();
        $end = CarbonImmutable::parse($requirement->end_month)->startOfMonth();

        while ($cursor->lte($end)) {
            $periods[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->settings(['locale' => 'id'])->translatedFormat('F Y'),
                'date' => $cursor,
                'month' => (int) $cursor->format('n'),
                'year' => (int) $cursor->format('Y'),
            ];
            $cursor = $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  Collection<int, StudentExamRequirement>  $requirements
     * @return Collection<string, StudentBill>
     */
    private function loadBills(AcademicYear $academicYear, array $studentIds, Collection $requirements): Collection
    {
        $monthlyPeriods = [];

        foreach ($requirements->where('billing_frequency', BillFrequency::Monthly) as $requirement) {
            foreach ($this->requirementPeriods($academicYear, $requirement) as $period) {
                $monthlyPeriods[$period['key']] = ['month' => $period['month'], 'year' => $period['year']];
            }
        }

        $bills = StudentBill::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('payment_type_id', $requirements
                ->flatMap(fn (StudentExamRequirement $requirement): Collection => $this->requirementPaymentTypes($requirement)->pluck('id'))
                ->unique()
                ->all())
            ->where(function (Builder $query) use ($academicYear, $monthlyPeriods): void {
                if ($monthlyPeriods !== []) {
                    $query->where(function (Builder $monthlyQuery) use ($monthlyPeriods): void {
                        $monthlyQuery->where('billing_frequency', BillFrequency::Monthly->value)
                            ->where(function (Builder $periodQuery) use ($monthlyPeriods): void {
                                foreach ($monthlyPeriods as $period) {
                                    $periodQuery->orWhere(function (Builder $pairQuery) use ($period): void {
                                        $pairQuery->where('period_month', $period['month'])
                                            ->where('period_year', $period['year']);
                                    });
                                }
                            });
                    });
                }

                $method = $monthlyPeriods === [] ? 'where' : 'orWhere';
                $query->{$method}(function (Builder $yearlyQuery) use ($academicYear): void {
                    $yearlyQuery->where('billing_frequency', BillFrequency::Yearly->value)
                        ->where('academic_year', $academicYear->year);
                })->orWhere('billing_frequency', BillFrequency::OneTime->value);
            })
            ->withSum('adjustments as exam_adjustments_total', 'amount')
            ->withSum([
                'paymentDetails as exam_paid_total' => fn ($query) => $query->whereHas(
                    'payment',
                    fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_ACTIVE)
                ),
            ], 'amount')
            ->orderBy('id')
            ->get();

        return $bills->keyBy(function (StudentBill $bill) use ($academicYear): string {
            return $this->billKey(
                $bill->student_id,
                $bill->payment_type_id,
                BillFrequency::from($bill->billing_frequency),
                $bill->period_month,
                $bill->period_year,
                (string) $academicYear->year,
            );
        });
    }

    /** @param array<string, Collection<int, PaymentRate>> $rates */
    private function applicableRate(
        array $rates,
        int $paymentTypeId,
        BillFrequency $frequency,
        int $classLevel,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?StudentPaymentSetting $setting,
    ): ?PaymentRate {
        if ($frequency !== BillFrequency::Monthly && $setting instanceof StudentPaymentSetting) {
            if ($setting->started_at !== null) {
                $startDate = $startDate->max(CarbonImmutable::parse($setting->started_at)->startOfDay());
            }

            if ($setting->ended_at !== null) {
                $endDate = $endDate->min(CarbonImmutable::parse($setting->ended_at)->endOfDay());
            }

            if ($startDate->gt($endDate)) {
                return null;
            }
        }

        return ($rates[$paymentTypeId.'|'.$classLevel.'|'.$frequency->value] ?? collect())
            ->first(fn (PaymentRate $rate): bool => $rate->effective_from->lte($endDate)
                && ($rate->effective_until === null || $rate->effective_until->gte($startDate)));
    }

    private function isWithinSettingWindow(
        Student $student,
        StudentPaymentSetting $setting,
        BillFrequency $frequency,
        CarbonImmutable $date,
        AcademicYear $academicYear,
    ): bool {
        if ($frequency === BillFrequency::Monthly) {
            $period = $date->startOfMonth();
            $studentStart = $student->entry_date ?? $student->created_at;

            if ($studentStart !== null && $period->lt(CarbonImmutable::parse($studentStart)->startOfMonth())) {
                return false;
            }

            if ($setting->started_at !== null && $period->lt(CarbonImmutable::parse($setting->started_at)->startOfMonth())) {
                return false;
            }

            return $setting->ended_at === null || ! $period->gt(CarbonImmutable::parse($setting->ended_at)->startOfMonth());
        }

        $academicStart = CarbonImmutable::parse($academicYear->start_date)->startOfDay();
        $academicEnd = CarbonImmutable::parse($academicYear->end_date)->endOfDay();

        return ($setting->started_at === null || ! CarbonImmutable::parse($setting->started_at)->gt($academicEnd))
            && ($setting->ended_at === null || ! CarbonImmutable::parse($setting->ended_at)->lt($academicStart));
    }

    private function billKey(
        int $studentId,
        int $paymentTypeId,
        BillFrequency $frequency,
        ?int $month,
        ?int $year,
        string $academicYear,
    ): string {
        return match ($frequency) {
            BillFrequency::Monthly => $studentId.'|'.$paymentTypeId.'|monthly|'.$year.'-'.$month,
            BillFrequency::Yearly => $studentId.'|'.$paymentTypeId.'|yearly|'.$academicYear,
            BillFrequency::OneTime => $studentId.'|'.$paymentTypeId.'|one_time',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $evaluations
     * @return list<array{key: string, label: string}>
     */
    private function monthlyPeriods(array $evaluations): array
    {
        $periods = [];

        foreach ($evaluations as $evaluation) {
            if ($evaluation['billing_frequency'] !== BillFrequency::Monthly->value) {
                continue;
            }

            $key = (string) $evaluation['period_key'];
            $periods[$key] = ['key' => $key, 'label' => (string) $evaluation['period_label']];
        }

        ksort($periods);

        return array_values($periods);
    }

    /**
     * @param  list<array<string, mixed>>  $evaluations
     * @return list<array{payment_type_id: int, payment_type_name: string, periods: array<string, array<string, mixed>>}>
     */
    private function monthlyRows(array $evaluations): array
    {
        $rows = [];

        foreach ($evaluations as $evaluation) {
            if ($evaluation['billing_frequency'] !== BillFrequency::Monthly->value) {
                continue;
            }

            $paymentTypeId = (int) $evaluation['payment_type_id'];
            $rows[$paymentTypeId] ??= [
                'payment_type_id' => $paymentTypeId,
                'payment_type_name' => (string) $evaluation['payment_type_name'],
                'periods' => [],
            ];
            $rows[$paymentTypeId]['periods'][(string) $evaluation['period_key']] = $evaluation;
        }

        return array_values($rows);
    }

    private function mappingKey(SchoolLevel $level, int $paymentTypeId): string
    {
        return $level->value.'|'.$paymentTypeId;
    }

    private function studentTypeKey(int $studentId, int $paymentTypeId): string
    {
        return $studentId.'|'.$paymentTypeId;
    }

    private function paymentTypeName(StudentExamRequirement $requirement): string
    {
        $names = $this->requirementPaymentTypes($requirement)->pluck('name');

        return $names->isNotEmpty() ? $names->implode(' + ') : 'Jenis pembayaran';
    }

    /** @param Collection<int, StudentExamRequirement> $requirements */
    private function loadRequirementRelations(Collection $requirements): void
    {
        foreach ($requirements as $requirement) {
            $requirement->loadMissing(['paymentType', 'pooledPaymentTypes']);
        }
    }

    /** @return Collection<int, PaymentType> */
    private function requirementPaymentTypes(StudentExamRequirement $requirement): Collection
    {
        $pooledPaymentTypes = $requirement->relationLoaded('pooledPaymentTypes')
            ? $requirement->getRelation('pooledPaymentTypes')
            : collect();

        if ($pooledPaymentTypes instanceof Collection && $pooledPaymentTypes->isNotEmpty()) {
            return $pooledPaymentTypes->sortBy('name')->values();
        }

        $paymentType = $requirement->getRelation('paymentType');

        return $paymentType instanceof PaymentType ? collect([$paymentType]) : collect();
    }

    private function moneyToCents(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function percentageLabel(float $percentage): string
    {
        return rtrim(rtrim(number_format(round($percentage, 2), 2, ',', ''), '0'), ',').'%';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PASS => 'Memenuhi',
            self::STATUS_FAIL => 'Belum Memenuhi',
            self::STATUS_NOT_APPLICABLE => 'Tidak Berlaku',
            self::STATUS_MISSING_BILL => 'Tagihan Belum Tersedia',
            default => $status,
        };
    }
}
