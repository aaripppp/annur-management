<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function monthlyOptimizationYear(int $startYear, bool $active = false): AcademicYear
{
    return AcademicYear::create([
        'year' => $startYear.'/'.($startYear + 1),
        'is_active' => $active,
        'start_date' => $startYear.'-07-01',
        'end_date' => ($startYear + 1).'-06-30',
    ]);
}

function monthlyOptimizationStudent(
    AcademicYear $academicYear,
    SchoolClass $schoolClass,
    array $studentAttributes = [],
    string $enrollmentStatus = 'active',
): Student {
    $student = Student::factory()->create(array_merge([
        'class_id' => $schoolClass->id,
        'status' => StudentStatus::Active,
    ], $studentAttributes));

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => $enrollmentStatus,
    ]);

    return $student;
}

function monthlyOptimizationType(
    int $classLevel,
    int $amount = 970_000,
    string $name = 'SPP Monthly Optimization',
    BillFrequency $frequency = BillFrequency::Monthly,
    string $effectiveFrom = '2030-07-01',
    ?string $effectiveUntil = null,
): PaymentType {
    $type = makeBillType($name);
    makeBillRate($type, $classLevel, $amount, [
        'billing_frequency' => $frequency,
        'effective_from' => $effectiveFrom,
        'effective_until' => $effectiveUntil,
    ]);
    makeLevelDefault($type, SchoolLevel::fromClassLevel($classLevel), required: false);

    return $type;
}

/** @param list<string> $queries */
function monthlyOptimizationQueryCount(array $queries, string $family): int
{
    $pattern = match ($family) {
        'payment_types' => 'from "payment_types" where "id" in',
        'payment_rates' => 'from "payment_rates"',
        'payment_type_school_levels' => 'from "payment_type_school_levels"',
        'student_payment_settings' => 'select * from "student_payment_settings"',
        'student_bills' => 'from "student_bills"',
    };

    return collect($queries)
        ->filter(fn (string $query): bool => str_starts_with($query, 'select') && str_contains($query, $pattern))
        ->count();
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false]);
    PaymentTypeSchoolLevel::query()->update(['is_active' => false]);
});

it('keeps the academic year start rate fixed from July through June', function () {
    $year = monthlyOptimizationYear(2030, true);
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2030-07-01']);
    $type = monthlyOptimizationType(
        7,
        amount: 970_000,
        name: 'SPP Fixed Academic Year',
        effectiveFrom: '2030-07-01',
        effectiveUntil: '2030-12-31',
    );
    makeBillRate($type, 7, 975_000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2031-01-01',
        'effective_until' => null,
    ]);

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($year);
    $result = $service->generateMonthlyForAcademicYear($year);
    $bills = $student->bills()->where('payment_type_id', $type->id)->get();

    expect($preview['will_create'])->toBe(12)
        ->and(collect($preview['monthly_bills_to_create'])->pluck('amount')->unique()->all())->toBe([970_000.0])
        ->and($result)->toBe(['created' => 12, 'skipped' => 0])
        ->and($bills)->toHaveCount(12)
        ->and($bills->pluck('amount')->map(fn ($amount): float => (float) $amount)->unique()->all())->toBe([970_000.0])
        ->and($bills->firstWhere('period_month', 1)?->period_year)->toBe(2031);
});

it('applies October entry only to the first academic year and bills the next year fully', function () {
    $firstYear = monthlyOptimizationYear(2032, true);
    $nextYear = monthlyOptimizationYear(2033);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = monthlyOptimizationStudent($firstYear, $class, ['entry_date' => '2032-10-15']);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $nextYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    $type = monthlyOptimizationType(8, name: 'SPP October Entry', effectiveFrom: '2032-07-01');
    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'is_active' => true,
        'started_at' => '2032-10-01',
    ]);

    $service = app(BillGenerationService::class);
    $firstResult = $service->generateMonthlyForAcademicYear($firstYear);
    $nextResult = $service->generateMonthlyForAcademicYear($nextYear);
    $bills = $student->bills()->where('payment_type_id', $type->id)->get();

    expect($firstResult)->toBe(['created' => 9, 'skipped' => 3])
        ->and($nextResult)->toBe(['created' => 12, 'skipped' => 0])
        ->and($bills)->toHaveCount(21)
        ->and($bills->where('period_year', 2032)->pluck('period_month')->sort()->values()->all())->toBe([10, 11, 12])
        ->and($bills->where('period_year', 2033))->toHaveCount(12)
        ->and($bills->where('period_year', 2034)->pluck('period_month')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('excludes graduated transferred and historical active enrollment alumni before setting creation', function () {
    $year = monthlyOptimizationYear(2035, true);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $active = monthlyOptimizationStudent($year, $class);
    $graduated = monthlyOptimizationStudent($year, $class, ['status' => StudentStatus::Graduated]);
    $transferred = monthlyOptimizationStudent($year, $class, ['status' => StudentStatus::Transferred]);
    $type = monthlyOptimizationType(8, name: 'SPP Eligibility', effectiveFrom: '2035-07-01');

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $result = app(BillGenerationService::class)->generateMonthlyForAcademicYear($year);

    expect($preview['eligible_students'])->toBe(1)
        ->and($result['created'])->toBe(12)
        ->and($active->bills()->count())->toBe(12)
        ->and($graduated->bills()->count())->toBe(0)
        ->and($transferred->bills()->count())->toBe(0)
        ->and($graduated->paymentSettings()->where('payment_type_id', $type->id)->exists())->toBeFalse()
        ->and($transferred->paymentSettings()->where('payment_type_id', $type->id)->exists())->toBeFalse();
});

it('preserves custom amounts windows inactive settings and excludes non monthly types', function () {
    $year = monthlyOptimizationYear(2036, true);
    $class = SchoolClass::factory()->create(['level' => 10]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2036-07-01']);
    $custom = monthlyOptimizationType(10, name: 'Custom Window', effectiveFrom: '2036-07-01');
    $zero = monthlyOptimizationType(10, name: 'Zero Custom', effectiveFrom: '2036-07-01');
    $inactive = monthlyOptimizationType(10, name: 'Inactive Monthly', effectiveFrom: '2036-07-01');
    $yearly = monthlyOptimizationType(10, name: 'Yearly Excluded', frequency: BillFrequency::Yearly, effectiveFrom: '2036-07-01');
    $oneTime = monthlyOptimizationType(10, name: 'One Time Excluded', frequency: BillFrequency::OneTime, effectiveFrom: '2036-07-01');

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $custom->id,
        'is_active' => true,
        'started_at' => '2036-08-01',
        'ended_at' => '2036-10-31',
        'custom_amount' => 123_456,
    ]);
    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $zero->id,
        'is_active' => true,
        'started_at' => '2036-07-01',
        'custom_amount' => 0,
    ]);
    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $inactive->id,
        'is_active' => false,
        'started_at' => '2036-07-01',
    ]);

    $result = app(BillGenerationService::class)->generateMonthlyForAcademicYear($year, includeBreakdown: true);

    expect($student->bills()->where('payment_type_id', $custom->id)->count())->toBe(3)
        ->and($student->bills()->where('payment_type_id', $custom->id)->pluck('amount')->map(fn ($amount): float => (float) $amount)->unique()->all())->toBe([123_456.0])
        ->and($student->bills()->where('payment_type_id', $zero->id)->count())->toBe(12)
        ->and($student->bills()->where('payment_type_id', $zero->id)->pluck('amount')->map(fn ($amount): float => (float) $amount)->unique()->all())->toBe([0.0])
        ->and($student->bills()->where('payment_type_id', $inactive->id)->count())->toBe(0)
        ->and($student->bills()->whereIn('payment_type_id', [$yearly->id, $oneTime->id])->count())->toBe(0)
        ->and($result['breakdown']['outside_period'])->toBe(9)
        ->and($result['breakdown']['inactive_setting'])->toBe(1)
        ->and($result['breakdown']['invalid_configuration'])->toBe(0);
});

it('classifies existing and outside periods while preserving the legacy result shape', function () {
    $year = monthlyOptimizationYear(2037, true);
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2037-07-01']);
    $type = monthlyOptimizationType(7, name: 'SPP Breakdown', effectiveFrom: '2037-07-01');
    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'is_active' => true,
        'started_at' => '2037-08-01',
    ]);
    $existing = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 777_777,
        'period_month' => 8,
        'period_year' => 2037,
        'billing_frequency' => BillFrequency::Monthly,
    ]);

    $service = app(BillGenerationService::class);
    $detailed = $service->generateMonthlyForAcademicYear($year, includeBreakdown: true);
    $legacy = $service->generateMonthlyForAcademicYear($year);

    expect($detailed)->toBe([
        'created' => 10,
        'skipped' => 2,
        'breakdown' => [
            'already_existing' => 1,
            'outside_period' => 1,
            'rejected_during_creation' => 0,
            'inactive_setting' => 0,
            'invalid_configuration' => 0,
        ],
    ])->and($existing->fresh()->id)->toBe($existing->id)
        ->and((float) $existing->fresh()->amount)->toBe(777_777.0)
        ->and($student->bills()->where('payment_type_id', $type->id)->count())->toBe(11)
        ->and($legacy)->toBe(['created' => 0, 'skipped' => 12]);
});

it('remembers bills inserted earlier in the same invocation', function () {
    $year = monthlyOptimizationYear(2038, true);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2038-07-01']);
    $type = monthlyOptimizationType(8, name: 'SPP Remembered', effectiveFrom: '2038-07-01');
    $service = new class extends BillGenerationService
    {
        protected function getMonthlyPeriods(AcademicYear $academicYear): array
        {
            $july = $academicYear->start_date->copy()->startOfMonth();

            return [$july->copy(), $july->copy()];
        }
    };

    $result = $service->generateMonthlyForAcademicYear($year, includeBreakdown: true);

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['breakdown']['already_existing'])->toBe(1)
        ->and($student->bills()->where('payment_type_id', $type->id)->count())->toBe(1);
});

it('rolls back a newly created setting and bill after a later failure', function () {
    $year = monthlyOptimizationYear(2039, true);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2039-07-01']);
    $type = monthlyOptimizationType(8, name: 'SPP Rollback', effectiveFrom: '2039-07-01');
    $unrelatedType = makeBillType('Existing Unrelated Rollback');
    $existing = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $unrelatedType->id,
        'amount' => 456_789,
        'period_month' => 6,
        'period_year' => 2039,
        'billing_frequency' => BillFrequency::Monthly,
    ]);
    $studentBefore = $student->fresh()->getAttributes();
    $enrollment = $student->enrollments()->where('academic_year_id', $year->id)->firstOrFail();
    $enrollmentBefore = $enrollment->fresh()->getAttributes();
    $yearBefore = $year->fresh()->getAttributes();
    $existingBefore = $existing->fresh()->getAttributes();
    $service = new class extends BillGenerationService
    {
        private int $createAttempts = 0;

        protected function createBill(
            Student $student,
            PaymentType $type,
            PaymentRate $rate,
            CarbonInterface $targetDate,
            float $amount,
            ?SchoolLevel $targetSchoolLevel = null,
        ): ?StudentBill {
            $this->createAttempts++;

            if ($this->createAttempts === 2) {
                throw new RuntimeException('Forced monthly generation failure');
            }

            return parent::createBill($student, $type, $rate, $targetDate, $amount, $targetSchoolLevel);
        }
    };

    expect(fn () => $service->generateMonthlyForAcademicYear($year))
        ->toThrow(RuntimeException::class, 'Forced monthly generation failure');

    expect(StudentPaymentSetting::query()->where('student_id', $student->id)->where('payment_type_id', $type->id)->exists())->toBeFalse()
        ->and(StudentBill::query()->where('student_id', $student->id)->where('payment_type_id', $type->id)->exists())->toBeFalse()
        ->and($existing->fresh()->getAttributes())->toBe($existingBefore)
        ->and($student->fresh()->getAttributes())->toBe($studentBefore)
        ->and($enrollment->fresh()->getAttributes())->toBe($enrollmentBefore)
        ->and($year->fresh()->getAttributes())->toBe($yearBefore);
});

it('rejects concurrent backend execution before any writes and reports lock contention in Livewire', function () {
    $year = monthlyOptimizationYear(2040, true);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = monthlyOptimizationStudent($year, $class, ['entry_date' => '2040-07-01']);
    $type = monthlyOptimizationType(8, name: 'SPP Locked', effectiveFrom: '2040-07-01');
    $lock = Cache::lock('academic-year-monthly-generation', 60);

    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(BillGenerationService::class)->generateMonthlyForAcademicYear($year))
            ->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    app()->instance(BillGenerationService::class, new class extends BillGenerationService
    {
        public function generateMonthlyForAcademicYear(AcademicYear $academicYear, bool $includeBreakdown = false): array
        {
            throw new LockTimeoutException;
        }
    });

    Livewire::test(AcademicYearManagement::class)
        ->set('monthlyTargetYearId', $year->id)
        ->set('isMonthlyConfirmOpen', true)
        ->call('executeMonthlyGeneration')
        ->assertSet('isMonthlyConfirmOpen', false)
        ->assertSee('Generate tagihan bulanan sedang dijalankan. Silakan tunggu sampai proses sebelumnya selesai.');

    expect($student->bills()->where('payment_type_id', $type->id)->exists())->toBeFalse()
        ->and($student->paymentSettings()->where('payment_type_id', $type->id)->exists())->toBeFalse();
});

it('keeps preview read only and consistent with execution', function () {
    $year = monthlyOptimizationYear(2041, true);
    $class = SchoolClass::factory()->create(['level' => 7]);
    monthlyOptimizationStudent($year, $class, ['entry_date' => '2041-07-01']);
    monthlyOptimizationType(7, name: 'SPP Preview Purity', effectiveFrom: '2041-07-01');
    $countsBefore = [
        'student_payment_settings' => StudentPaymentSetting::query()->count(),
        'student_bills' => StudentBill::query()->count(),
        'students' => Student::query()->count(),
        'student_academic_enrollments' => StudentAcademicEnrollment::query()->count(),
        'academic_years' => AcademicYear::query()->count(),
    ];

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($year);
    $countsAfter = [
        'student_payment_settings' => StudentPaymentSetting::query()->count(),
        'student_bills' => StudentBill::query()->count(),
        'students' => Student::query()->count(),
        'student_academic_enrollments' => StudentAcademicEnrollment::query()->count(),
        'academic_years' => AcademicYear::query()->count(),
    ];
    $result = $service->generateMonthlyForAcademicYear($year, includeBreakdown: true);

    expect($countsAfter)->toBe($countsBefore)
        ->and($preview['will_create'])->toBe(12)
        ->and($preview['already_existing'])->toBe(0)
        ->and($result['created'])->toBe($preview['will_create'])
        ->and($result['breakdown']['already_existing'])->toBe($preview['already_existing']);
});

it('bounds preview and execution select families as equivalent students increase', function () {
    $type = monthlyOptimizationType(8, name: 'SPP Query Scaling', effectiveFrom: '2042-07-01');
    $onePreviewYear = monthlyOptimizationYear(2042, true);
    $manyPreviewYear = monthlyOptimizationYear(2043);
    $oneExecutionYear = monthlyOptimizationYear(2044);
    $manyExecutionYear = monthlyOptimizationYear(2045);
    $class = SchoolClass::factory()->create(['level' => 8]);

    foreach ([[$onePreviewYear, 1], [$manyPreviewYear, 10], [$oneExecutionYear, 1], [$manyExecutionYear, 10]] as [$year, $count]) {
        foreach (range(1, $count) as $unused) {
            monthlyOptimizationStudent($year, $class, ['entry_date' => $year->start_date]);
        }
    }

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    app(BillGenerationService::class)->getMonthlyGenerationPreview($onePreviewYear);
    $onePreviewQueries = $queries;
    $queries = [];
    app(BillGenerationService::class)->getMonthlyGenerationPreview($manyPreviewYear);
    $manyPreviewQueries = $queries;
    $queries = [];
    app(BillGenerationService::class)->generateMonthlyForAcademicYear($oneExecutionYear);
    $oneExecutionQueries = $queries;
    $queries = [];
    app(BillGenerationService::class)->generateMonthlyForAcademicYear($manyExecutionYear);
    $manyExecutionQueries = $queries;

    foreach (['payment_types', 'payment_rates', 'student_payment_settings', 'student_bills'] as $family) {
        expect(monthlyOptimizationQueryCount($manyPreviewQueries, $family))
            ->toBe(monthlyOptimizationQueryCount($onePreviewQueries, $family));
        expect(monthlyOptimizationQueryCount($manyExecutionQueries, $family))
            ->toBe(monthlyOptimizationQueryCount($oneExecutionQueries, $family));
    }

    expect(monthlyOptimizationQueryCount($manyPreviewQueries, 'payment_type_school_levels'))
        ->toBe(monthlyOptimizationQueryCount($onePreviewQueries, 'payment_type_school_levels'))
        ->and(monthlyOptimizationQueryCount($manyExecutionQueries, 'payment_type_school_levels'))
        ->toBe(monthlyOptimizationQueryCount($oneExecutionQueries, 'payment_type_school_levels'))
        ->and(monthlyOptimizationQueryCount($onePreviewQueries, 'payment_types'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($onePreviewQueries, 'payment_rates'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($onePreviewQueries, 'student_payment_settings'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($onePreviewQueries, 'payment_type_school_levels'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($oneExecutionQueries, 'payment_types'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($oneExecutionQueries, 'payment_rates'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($oneExecutionQueries, 'student_payment_settings'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($oneExecutionQueries, 'payment_type_school_levels'))->toBe(2)
        ->and(monthlyOptimizationQueryCount($manyPreviewQueries, 'student_bills'))->toBe(1)
        ->and(monthlyOptimizationQueryCount($manyExecutionQueries, 'student_bills'))->toBe(1)
        ->and(StudentBill::query()->whereIn('student_id', $manyExecutionYear->enrollments()->pluck('student_id'))->where('payment_type_id', $type->id)->count())->toBe(120);
});
