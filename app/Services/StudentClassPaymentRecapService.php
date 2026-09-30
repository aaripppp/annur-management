<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Support\StudentBillbook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudentClassPaymentRecapService
{
    /**
     * Payment types reported in their own tables instead of the main recap.
     *
     * Jemputan is billed per month and Adm Jemputan once per student, so neither
     * belongs in the wide main table. Names are matched exactly on purpose: a
     * pattern match would also capture renamed or duplicated rows, and
     * payment_types.name carries no unique constraint.
     */
    private const SEGREGATED_MONTHLY_TYPE_NAME = 'Jemputan';

    private const SEGREGATED_ONE_TIME_TYPE_NAME = 'Adm Jemputan';

    /**
     * @return array{
     *     academic_year_id: int,
     *     academic_year: string,
     *     school_level: string,
     *     school_class_id: int,
     *     school_class: string,
     *     months: list<array{key: string, month: int, year: int, label: string}>,
     *     payment_types: array{
     *         monthly: list<array{id: int, name: string}>,
     *         yearly: list<array{id: int, name: string}>,
     *         one_time: list<array{id: int, name: string}>
     *     },
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     student_count: int,
     *     segregated: array{
     *         title: string,
     *         rows: list<array<string, mixed>>,
     *         month_totals: array<string, array{target: float, paid: float, remaining: float}>,
     *         jemputan_totals: array{target: float, paid: float, remaining: float},
     *         adm_jemputan_totals: array{target: float, paid: float, remaining: float},
     *         student_count: int
     *     }
     * }
     */
    public function generate(int $academicYearId, SchoolLevel $schoolLevel, int $schoolClassId): array
    {
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $schoolClass = SchoolClass::query()->findOrFail($schoolClassId);

        if ($schoolClass->school_level !== $schoolLevel) {
            throw new InvalidArgumentException('Kelas tidak sesuai dengan jenjang yang dipilih.');
        }

        $periods = $this->academicYearPeriods($academicYear);
        $configuredTypes = $this->configuredPaymentTypes($schoolLevel, (int) $schoolClass->level);
        $paymentTypes = $this->withoutSegregatedTypes([
            'monthly' => $configuredTypes['monthly'],
            'yearly' => $configuredTypes['yearly'],
            'one_time' => $configuredTypes['one_time'],
        ]);
        $monthlyTypeIds = array_column($paymentTypes['monthly'], 'id');
        $yearlyTypeIds = array_column($paymentTypes['yearly'], 'id');
        $oneTimeTypeIds = array_column($paymentTypes['one_time'], 'id');

        // Pembatas audience untuk kolom one_time. Tipe prospective (mis. Formulir
        // Pendaftaran) ditagihkan di sisi calon siswa dan tidak pernah disalin ke
        // student_bills, jadi tagihannya dibaca dari prospective_student_bills.
        // Kedua partisi saling lepas, sehingga StudentBill dan
        // ProspectiveStudentBill untuk payment type yang sama mustahil dijumlahkan.
        $prospectiveOneTimeTypeIds = array_values(array_intersect(
            $configuredTypes['prospective_type_ids'],
            $oneTimeTypeIds,
        ));
        $oneTimeStudentTypeIds = array_values(array_diff($oneTimeTypeIds, $prospectiveOneTimeTypeIds));

        $enrollments = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('status', 'active')
            ->with(['student.enrollments.academicYear'])
            ->get()
            ->filter(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->student instanceof Student)
            ->sortBy(fn (StudentAcademicEnrollment $enrollment): string => Str::lower($enrollment->student->nama_lengkap))
            ->values();

        $studentIds = $enrollments->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
        $billsByStudent = $this->billsForStudents($studentIds, $academicYear, $periods)->groupBy('student_id');
        $prospectiveBillsByStudent = $this->prospectiveBillsForStudents(
            $studentIds,
            $prospectiveOneTimeTypeIds,
            $academicYear,
        )->groupBy('recap_student_id');
        $rows = [];
        $segregatedRows = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student instanceof Student) {
                continue;
            }

            $row = $this->emptyRow($student, $periods, $monthlyTypeIds, $yearlyTypeIds, $oneTimeTypeIds);
            $segregatedRow = $this->emptySegregatedRow($student);
            $enrollmentBoundaryYear = $student->finalEnrollmentYearLabel();

            foreach ($billsByStudent->get($student->id, collect()) as $bill) {
                $this->applyBill(
                    $row,
                    $bill,
                    $academicYear,
                    $enrollmentBoundaryYear,
                    $monthlyTypeIds,
                    $yearlyTypeIds,
                    $oneTimeStudentTypeIds,
                );
                $this->applySegregatedBill(
                    $segregatedRow,
                    $bill,
                    $academicYear,
                    $enrollmentBoundaryYear,
                );
            }

            foreach ($prospectiveBillsByStudent->get($student->id, collect()) as $prospectiveBill) {
                $this->applyProspectiveBill(
                    $row,
                    $prospectiveBill,
                    $academicYear->year,
                    $enrollmentBoundaryYear,
                    $prospectiveOneTimeTypeIds,
                );
            }

            foreach ($monthlyTypeIds as $typeId) {
                // Ringkasan is one expected monthly amount, taken from the latest persisted bill period.
                foreach ($row['monthly_targets'] as $targets) {
                    if ($targets[$typeId] !== null) {
                        $row['monthly_summary'][$typeId] = $targets[$typeId];
                    }
                }
            }

            $row['monthly_summary']['total'] = array_sum($row['monthly_summary']);
            unset($row['monthly_targets']);
            $rows[] = $row;
            $segregatedRows[] = $segregatedRow;
        }

        return [
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->year,
            'school_level' => $schoolLevel->value,
            'school_class_id' => $schoolClass->id,
            'school_class' => $schoolClass->name,
            'months' => $periods,
            'payment_types' => $paymentTypes,
            'rows' => $rows,
            'totals' => $this->totals($rows, $periods, $monthlyTypeIds, $yearlyTypeIds, $oneTimeTypeIds),
            'student_count' => count($rows),
            'segregated' => $this->segregatedRecap($segregatedRows, $periods),
        ];
    }

    /**
     * Payment type columns come from the active jenjang applicability
     * (payment_type_school_levels) plus the billing frequency each type is
     * configured with through its PaymentRate rows. No payment type is
     * identified by name.
     *
     * Rates are scoped to the class level of the class being recapped, because one
     * type can be billed yearly on a jenjang and monthly on another (Ekskul yearly
     * on TK, monthly on class 7). Reading every rate of the type would push it into
     * both groups on every jenjang.
     *
     * @return array{
     *     monthly: list<array{id: int, name: string}>,
     *     yearly: list<array{id: int, name: string}>,
     *     one_time: list<array{id: int, name: string}>,
     *     prospective_type_ids: list<int>
     * }
     */
    private function configuredPaymentTypes(SchoolLevel $schoolLevel, int $classLevel): array
    {
        $types = PaymentTypeSchoolLevel::query()
            ->where('school_level', $schoolLevel)
            ->where('is_active', true)
            ->with(['paymentType' => fn ($query) => $query
                ->with(['rates' => fn ($rateQuery) => $rateQuery
                    ->where('class_level', $classLevel)])])
            ->get()
            ->pluck('paymentType')
            ->filter(fn ($type): bool => $type instanceof PaymentType)
            ->unique('id')
            ->sortBy('id')
            ->values();

        $groups = ['monthly' => [], 'yearly' => [], 'one_time' => [], 'prospective_type_ids' => []];

        foreach ($types as $type) {
            $frequencies = $type->rates
                ->map(fn (PaymentRate $rate): ?string => $rate->billing_frequency?->value)
                ->filter()
                ->unique()
                ->values();

            foreach ($frequencies as $frequency) {
                $groups[$frequency][] = ['id' => (int) $type->id, 'name' => $type->name];
            }

            if ($type->audience === PaymentTypeAudience::ProspectiveStudent) {
                $groups['prospective_type_ids'][] = (int) $type->id;
            }
        }

        return $groups;
    }

    /**
     * Drop the segregated types from the main recap groups.
     *
     * Matching on the exact name keeps the main table free of Jemputan columns
     * without an extra lookup query, because configuredPaymentTypes() already
     * loads the applicable payment types with their names. Every count, header,
     * body cell, total and colspan in the main table is derived from the
     * remaining collections, so the wide table stays aligned without any
     * hardcoded column number.
     *
     * @param  array{monthly: list<array{id: int, name: string}>, yearly: list<array{id: int, name: string}>, one_time: list<array{id: int, name: string}>}  $paymentTypes
     * @return array{monthly: list<array{id: int, name: string}>, yearly: list<array{id: int, name: string}>, one_time: list<array{id: int, name: string}>}
     */
    private function withoutSegregatedTypes(array $paymentTypes): array
    {
        $segregatedNames = [self::SEGREGATED_MONTHLY_TYPE_NAME, self::SEGREGATED_ONE_TIME_TYPE_NAME];

        foreach ($paymentTypes as $group => $types) {
            $paymentTypes[$group] = array_values(array_filter(
                $types,
                fn (array $type): bool => ! in_array($type['name'], $segregatedNames, true),
            ));
        }

        return $paymentTypes;
    }

    /**
     * @return array{
     *     student_id: int,
     *     student_name: string,
     *     jemputan: array<string, mixed>,
     *     adm_jemputan: array<string, mixed>
     * }
     */
    private function emptySegregatedRow(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->nama_lengkap,
            'jemputan' => ['months' => [], ...$this->emptyBalance(), 'bill_count' => 0],
            'adm_jemputan' => [...$this->emptyBalance(), 'bill_count' => 0],
        ];
    }

    /**
     * Accumulate a bill into the combined Rekap Jemputan table.
     *
     * The bill is already loaded by billsForStudents() together with its
     * adjustment and paid aggregates and the joined payment type name, so this
     * stays free of extra queries.
     *
     * @param  array<string, mixed>  $row
     */
    private function applySegregatedBill(
        array &$row,
        StudentBill $bill,
        AcademicYear $academicYear,
        ?string $enrollmentBoundaryYear,
    ): void {
        $typeName = $bill->payment_type_name;
        $frequency = BillFrequency::tryFrom((string) $bill->billing_frequency);
        $target = max(0.0, round((float) $bill->amount + (float) ($bill->recap_adjustments_total ?? 0), 2));
        $allocated = max(0.0, round((float) ($bill->recap_paid_total ?? 0), 2));
        $paid = min($target, $allocated);

        if ($typeName === self::SEGREGATED_MONTHLY_TYPE_NAME && $frequency === BillFrequency::Monthly) {
            $periodKey = sprintf('%04d-%02d', $bill->period_year, $bill->period_month);

            if (! isset($row['jemputan']['months'][$periodKey])) {
                $row['jemputan']['months'][$periodKey] = $this->emptyBalance();
            }

            $this->addBalance($row['jemputan']['months'][$periodKey], $target, $paid);
            $this->addBalance($row['jemputan'], $target, $paid);
            $row['jemputan']['bill_count']++;

            return;
        }

        if (
            $typeName === self::SEGREGATED_ONE_TIME_TYPE_NAME
            && $frequency === BillFrequency::OneTime
            && StudentBillbook::oneTimeBillVisibleForAcademicYear($bill, $academicYear->year, $enrollmentBoundaryYear)
        ) {
            $this->addBalance($row['adm_jemputan'], $target, $paid);
            $row['adm_jemputan']['bill_count']++;
        }
    }

    /**
     * Build the single combined Rekap Jemputan table: the monthly Jemputan
     * matrix plus the one-time Adm Jemputan balance, side by side on one row
     * per student. A student is included when they have a bill of either type,
     * and is never added or removed based on StudentPaymentSetting, so historic
     * bills stay visible.
     *
     * @param  list<array<string, mixed>>  $segregatedRows
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @return array{
     *     title: string,
     *     rows: list<array<string, mixed>>,
     *     month_totals: array<string, array{target: float, paid: float, remaining: float}>,
     *     jemputan_totals: array{target: float, paid: float, remaining: float},
     *     adm_jemputan_totals: array{target: float, paid: float, remaining: float},
     *     total_tagihan: float,
     *     student_count: int
     * }
     */
    private function segregatedRecap(array $segregatedRows, array $periods): array
    {
        $rows = [];
        $jemputanTotals = $this->emptyBalance();
        $admJemputanTotals = $this->emptyBalance();

        foreach ($segregatedRows as $segregatedRow) {
            $jemputan = $segregatedRow['jemputan'];
            $admJemputan = $segregatedRow['adm_jemputan'];
            $hasJemputan = ($jemputan['bill_count'] ?? 0) > 0;
            $hasAdmJemputan = ($admJemputan['bill_count'] ?? 0) > 0;

            if (! $hasJemputan && ! $hasAdmJemputan) {
                continue;
            }

            $rows[] = [
                'student_id' => $segregatedRow['student_id'],
                'student_name' => $segregatedRow['student_name'],
                'months' => $jemputan['months'],
                'jemputan' => $hasJemputan ? [
                    'target' => $jemputan['target'],
                    'paid' => $jemputan['paid'],
                    'remaining' => $jemputan['remaining'],
                    'bill_count' => $jemputan['bill_count'],
                ] : null,
                'adm_jemputan' => $hasAdmJemputan ? [
                    'target' => $admJemputan['target'],
                    'paid' => $admJemputan['paid'],
                    'remaining' => $admJemputan['remaining'],
                    'bill_count' => $admJemputan['bill_count'],
                    'status' => $admJemputan['remaining'] <= 0 ? StudentBill::STATUS_PAID : StudentBill::STATUS_UNPAID,
                ] : null,
                'bill_count' => $jemputan['bill_count'] + $admJemputan['bill_count'],
                'total_tagihan' => $jemputan['target'] + $admJemputan['target'],
            ];

            if ($hasJemputan) {
                $this->addToTotals($jemputanTotals, $jemputan);
            }

            if ($hasAdmJemputan) {
                $this->addToTotals($admJemputanTotals, $admJemputan);
            }
        }

        $monthTotals = [];

        foreach ($periods as $period) {
            $monthTotals[$period['key']] = $this->emptyBalance();
        }

        foreach ($rows as $row) {
            foreach ($row['months'] as $periodKey => $balance) {
                if (isset($monthTotals[$periodKey])) {
                    $this->addToTotals($monthTotals[$periodKey], $balance);
                }
            }
        }

        return [
            'title' => 'Rekap Jemputan',
            'rows' => $rows,
            'month_totals' => $monthTotals,
            'jemputan_totals' => $jemputanTotals,
            'adm_jemputan_totals' => $admJemputanTotals,
            'total_tagihan' => $jemputanTotals['target'] + $admJemputanTotals['target'],
            'student_count' => count($rows),
        ];
    }

    /**
     * @param  array{target: float, paid: float, remaining: float}  $totals
     * @param  array{target: float, paid: float, remaining: float}  $balance
     */
    private function addToTotals(array &$totals, array $balance): void
    {
        $totals['target'] += $balance['target'];
        $totals['paid'] += $balance['paid'];
        $totals['remaining'] += $balance['remaining'];
    }

    /** @return list<array{key: string, month: int, year: int, label: string}> */
    private function academicYearPeriods(AcademicYear $academicYear): array
    {
        $periods = [];
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $date = $start->addMonths($offset);
            $periods[] = [
                'key' => $date->format('Y-m'),
                'month' => (int) $date->format('n'),
                'year' => (int) $date->format('Y'),
                'label' => $date->settings(['locale' => 'id'])->translatedFormat('F'),
            ];
        }

        return $periods;
    }

    /**
     * Load every bill the recapped class may display, with the aggregates the
     * recap needs.
     *
     * The payment type name is joined onto the same query so the segregated
     * Jemputan tables can identify their bills without a second round trip.
     * A left join keeps bills whose payment type row is missing.
     *
     * @param  list<int>  $studentIds
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @return Collection<int, StudentBill>
     */
    private function billsForStudents(array $studentIds, AcademicYear $academicYear, array $periods): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return StudentBill::query()
            ->leftJoin('payment_types', 'payment_types.id', '=', 'student_bills.payment_type_id')
            ->addSelect('student_bills.*')
            ->addSelect('payment_types.name as payment_type_name')
            ->whereIn('student_bills.student_id', $studentIds)
            ->where(function (Builder $query) use ($academicYear, $periods): void {
                $query->where(function (Builder $monthlyQuery) use ($periods): void {
                    $monthlyQuery->where('student_bills.billing_frequency', BillFrequency::Monthly->value)
                        ->where(function (Builder $periodQuery) use ($periods): void {
                            foreach ($periods as $period) {
                                $periodQuery->orWhere(function (Builder $monthQuery) use ($period): void {
                                    $monthQuery->where('student_bills.period_month', $period['month'])
                                        ->where('student_bills.period_year', $period['year']);
                                });
                            }
                        });
                })->orWhere(function (Builder $yearlyQuery) use ($academicYear): void {
                    $yearlyQuery->where('student_bills.billing_frequency', BillFrequency::Yearly->value)
                        ->where('student_bills.academic_year', $academicYear->year);
                })->orWhere('student_bills.billing_frequency', BillFrequency::OneTime->value);
            })
            ->withSum('adjustments as recap_adjustments_total', 'amount')
            ->withSum([
                'paymentDetails as recap_paid_total' => fn (Builder $query) => $query->whereHas(
                    'payment',
                    fn (Builder $paymentQuery) => $paymentQuery->where('status', Payment::STATUS_ACTIVE)
                ),
            ], 'amount')
            ->orderBy('student_bills.student_id')
            ->orderBy('student_bills.id')
            ->get();
    }

    /**
     * Tagihan one_time sisi calon siswa untuk siswa yang sudah dikonversi.
     *
     * Tipe pembayaran prospective (mis. Formulir Pendaftaran) ditagihkan di
     * prospective_student_bills dan tidak pernah disalin ke student_bills saat
     * konversi, sehingga satu-satunya cara menampilkannya di rekap per kelas
     * adalah membaca riwayat prospective lewat prospective_students.converted_student_id.
     *
     * Satu query terkunci untuk seluruh siswa yang direkap, lalu dikelompokkan di
     * memori lewat alias recap_student_id. Pembayaran batal tidak ikut dihitung
     * karena withSum hanya menjumlahkan detail dari pembayaran berstatus active,
     * sama seperti semantic accessor ProspectiveStudentBill::paid_amount dan
     * seperti billsForStudents() pada sisi siswa.
     *
     * @param  list<int>  $studentIds
     * @param  list<int>  $typeIds  Tipe one_time ber-audience prospective_student
     * @return Collection<int, ProspectiveStudentBill>
     */
    private function prospectiveBillsForStudents(array $studentIds, array $typeIds, AcademicYear $academicYear): Collection
    {
        if ($studentIds === [] || $typeIds === []) {
            return collect();
        }

        return ProspectiveStudentBill::query()
            ->join('prospective_students', 'prospective_students.id', '=', 'prospective_student_bills.prospective_student_id')
            ->addSelect('prospective_student_bills.*')
            ->addSelect('prospective_students.converted_student_id as recap_student_id')
            ->whereIn('prospective_students.converted_student_id', $studentIds)
            ->whereIn('prospective_student_bills.payment_type_id', $typeIds)
            ->where('prospective_student_bills.billing_frequency', BillFrequency::OneTime->value)
            // Satu tagihan one_time tetap terbawa ke tahun akademik berikutnya,
            // sama seperti aturan tagihan one_time sisi siswa.
            ->where('prospective_student_bills.academic_year', '<=', $academicYear->year)
            ->withSum([
                'paymentDetails as recap_paid_total' => fn (Builder $query) => $query->whereHas(
                    'payment',
                    fn (Builder $paymentQuery) => $paymentQuery->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
                ),
            ], 'amount')
            ->orderBy('prospective_students.converted_student_id')
            ->orderBy('prospective_student_bills.id')
            ->get();
    }

    /**
     * Menulis tagihan one_time prospective ke balance kolom rekap.
     *
     * prospective_student_bills tidak punya tabel adjustment seperti
     * student_bills, jadi target diambil apa adanya dari amount bill.
     *
     * @param  array<string, mixed>  $row
     * @param  list<int>  $prospectiveTypeIds
     */
    private function applyProspectiveBill(
        array &$row,
        ProspectiveStudentBill $bill,
        string $selectedAcademicYear,
        ?string $enrollmentBoundaryYear,
        array $prospectiveTypeIds,
    ): void {
        $typeId = (int) $bill->payment_type_id;

        if (! in_array($typeId, $prospectiveTypeIds, true)) {
            return;
        }

        if (! $this->prospectiveOneTimeBillVisibleForAcademicYear(
            (string) $bill->academic_year,
            $selectedAcademicYear,
            $enrollmentBoundaryYear,
        )) {
            return;
        }

        $target = max(0.0, round((float) $bill->amount, 2));
        $allocated = max(0.0, round((float) ($bill->recap_paid_total ?? 0), 2));
        $paid = min($target, $allocated);

        $this->addBalance($row['one_time'][$typeId], $target, $paid);
    }

    /**
     * Aturan visibilitas tagihan one_time prospective memakai pola yang sama
     * dengan StudentBillbook::oneTimeBillVisibleForAcademicYear(): tagihan yang
     * dibuat pada tahun lebih lama tetap terlihat, tetapi tidak melewati tahun
     * enrollmen terakhir siswa.
     */
    private function prospectiveOneTimeBillVisibleForAcademicYear(
        string $billAcademicYear,
        string $selectedAcademicYear,
        ?string $enrollmentBoundaryYear,
    ): bool {
        if ($billAcademicYear === '') {
            return $enrollmentBoundaryYear === null || $selectedAcademicYear <= $enrollmentBoundaryYear;
        }

        return $selectedAcademicYear >= $billAcademicYear
            && ($enrollmentBoundaryYear === null || $selectedAcademicYear <= $enrollmentBoundaryYear);
    }

    /**
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeTypeIds
     * @return array<string, mixed>
     */
    private function emptyRow(Student $student, array $periods, array $monthlyTypeIds, array $yearlyTypeIds, array $oneTimeTypeIds): array
    {
        $monthlyAmounts = [];
        $monthlyTargets = [];

        foreach ($periods as $period) {
            $monthlyAmounts[$period['key']] = array_fill_keys($monthlyTypeIds, 0.0);
            $monthlyTargets[$period['key']] = array_fill_keys($monthlyTypeIds, null);
        }

        $yearly = [];
        foreach ($yearlyTypeIds as $typeId) {
            $yearly[$typeId] = $this->emptyBalance();
        }

        $oneTime = [];
        foreach ($oneTimeTypeIds as $typeId) {
            $oneTime[$typeId] = $this->emptyBalance();
        }

        $monthlySummary = array_fill_keys($monthlyTypeIds, 0.0);
        $monthlySummary['total'] = 0.0;

        return [
            'student_id' => $student->id,
            'student_name' => $student->nama_lengkap,
            'monthly_summary' => $monthlySummary,
            'monthly_targets' => $monthlyTargets,
            'monthly_paid' => $monthlyAmounts,
            'yearly' => $yearly,
            'one_time' => $oneTime,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeStudentTypeIds  Hanya tipe one_time ber-audience student
     */
    private function applyBill(
        array &$row,
        StudentBill $bill,
        AcademicYear $academicYear,
        ?string $enrollmentBoundaryYear,
        array $monthlyTypeIds,
        array $yearlyTypeIds,
        array $oneTimeStudentTypeIds,
    ): void {
        $target = max(0.0, round((float) $bill->amount + (float) ($bill->recap_adjustments_total ?? 0), 2));
        $allocated = max(0.0, round((float) ($bill->recap_paid_total ?? 0), 2));
        $paid = min($target, $allocated);
        $frequency = BillFrequency::tryFrom((string) $bill->billing_frequency);
        $typeId = (int) $bill->payment_type_id;

        if ($frequency === BillFrequency::Monthly) {
            $periodKey = sprintf('%04d-%02d', $bill->period_year, $bill->period_month);

            if (in_array($typeId, $monthlyTypeIds, true) && isset($row['monthly_paid'][$periodKey])) {
                $row['monthly_targets'][$periodKey][$typeId] = ($row['monthly_targets'][$periodKey][$typeId] ?? 0.0) + $target;
                $row['monthly_paid'][$periodKey][$typeId] += $paid;
            }

            return;
        }

        if ($frequency === BillFrequency::Yearly) {
            if (in_array($typeId, $yearlyTypeIds, true) && $bill->academic_year === $academicYear->year) {
                $this->addBalance($row['yearly'][$typeId], $target, $paid);
            }

            return;
        }

        // Tipe one_time ber-audience prospective sengaja tidak bisa lewat sini:
        // kolomnya diisi dari applyProspectiveBill(), sehingga StudentBill dan
        // prospective bill untuk payment type yang sama tidak pernah dijumlahkan.
        if ($frequency === BillFrequency::OneTime
            && in_array($typeId, $oneTimeStudentTypeIds, true)
            && StudentBillbook::oneTimeBillVisibleForAcademicYear($bill, $academicYear->year, $enrollmentBoundaryYear)) {
            $this->addBalance($row['one_time'][$typeId], $target, $paid);
        }
    }

    /** @return array{target: float, paid: float, remaining: float} */
    private function emptyBalance(): array
    {
        return ['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];
    }

    /** @param array{target: float, paid: float, remaining: float} $balance */
    private function addBalance(array &$balance, float $target, float $paid): void
    {
        $balance['target'] += $target;
        $balance['paid'] += $paid;
        $balance['remaining'] += max(0.0, round($target - $paid, 2));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeTypeIds
     * @return array<string, mixed>
     */
    private function totals(array $rows, array $periods, array $monthlyTypeIds, array $yearlyTypeIds, array $oneTimeTypeIds): array
    {
        $monthlySummary = array_fill_keys($monthlyTypeIds, 0.0);
        $monthlySummary['total'] = 0.0;

        $totals = [
            'monthly_summary' => $monthlySummary,
            'monthly_paid' => [],
            'yearly' => [],
            'one_time' => [],
        ];

        foreach ($periods as $period) {
            $totals['monthly_paid'][$period['key']] = array_fill_keys($monthlyTypeIds, 0.0);
        }

        foreach ($yearlyTypeIds as $typeId) {
            $totals['yearly'][$typeId] = $this->emptyBalance();
        }

        foreach ($oneTimeTypeIds as $typeId) {
            $totals['one_time'][$typeId] = $this->emptyBalance();
        }

        foreach ($rows as $row) {
            foreach ($totals['monthly_summary'] as $key => $amount) {
                $totals['monthly_summary'][$key] = $amount + $row['monthly_summary'][$key];
            }

            foreach ($totals['monthly_paid'] as $periodKey => $types) {
                foreach ($types as $typeId => $amount) {
                    $totals['monthly_paid'][$periodKey][$typeId] = $amount + $row['monthly_paid'][$periodKey][$typeId];
                }
            }

            foreach ($yearlyTypeIds as $typeId) {
                foreach ($totals['yearly'][$typeId] as $key => $amount) {
                    $totals['yearly'][$typeId][$key] = $amount + $row['yearly'][$typeId][$key];
                }
            }

            foreach ($oneTimeTypeIds as $typeId) {
                foreach ($totals['one_time'][$typeId] as $key => $amount) {
                    $totals['one_time'][$typeId][$key] = $amount + $row['one_time'][$typeId][$key];
                }
            }
        }

        return $totals;
    }
}
