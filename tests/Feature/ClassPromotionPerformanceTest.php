<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** @return array{0: AcademicYear, 1: AcademicYear} */
function promotionPerformanceYears(int $startYear): array
{
    $source = AcademicYear::firstOrCreate(
        ['year' => $startYear.'/'.($startYear + 1)],
        [
            'is_active' => true,
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
        ],
    );
    $source->update(['is_active' => true, 'promotion_processed_at' => null]);

    $target = AcademicYear::firstOrCreate(
        ['year' => ($startYear + 1).'/'.($startYear + 2)],
        [
            'is_active' => false,
            'start_date' => ($startYear + 1).'-07-01',
            'end_date' => ($startYear + 2).'-06-30',
        ],
    );
    $target->update(['is_active' => false, 'promotion_processed_at' => null]);

    return [$source, $target];
}

/** @return Collection<int, Student> */
function promotionPerformanceRoster(
    AcademicYear $sourceYear,
    SchoolClass $sourceClass,
    int $count,
): Collection {
    return collect(range(1, $count))->map(function () use ($sourceYear, $sourceClass): Student {
        $student = Student::factory()->create(['class_id' => $sourceClass->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $sourceYear->id,
            'school_class_id' => $sourceClass->id,
            'status' => 'active',
        ]);

        return $student;
    });
}

/** @param list<array{sql: string, bindings: array<mixed>}> $queries */
function promotionPerformanceQueryCount(array $queries, string $table): int
{
    return collect($queries)
        ->filter(fn (array $query): bool => str_contains($query['sql'], '"'.$table.'"'))
        ->count();
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false, 'promotion_processed_at' => null]);
    PaymentTypeSchoolLevel::query()->update(['is_active' => false]);
});

it('bounds promotion decision class reload and target enrollment queries by invocation', function () {
    [$oneSourceYear, $oneTargetYear] = promotionPerformanceYears(2030);
    $oneSourceClass = SchoolClass::create(['name' => 'VII Perf One', 'level' => 7]);
    $oneTargetClass = SchoolClass::create(['name' => 'VIII Perf One', 'level' => 8]);
    createPromotionRule($oneSourceClass, 'promote', $oneTargetClass);
    promotionPerformanceRoster($oneSourceYear, $oneSourceClass, 1);

    [$manySourceYear, $manyTargetYear] = promotionPerformanceYears(2032);
    $manySourceClass = SchoolClass::create(['name' => 'VII Perf Many', 'level' => 7]);
    $manyTargetClass = SchoolClass::create(['name' => 'VIII Perf Many', 'level' => 8]);
    createPromotionRule($manySourceClass, 'promote', $manyTargetClass);
    promotionPerformanceRoster($manySourceYear, $manySourceClass, 10);

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->whereKey($oneSourceYear->id)->update(['is_active' => true]);

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = ['sql' => strtolower($query->sql), 'bindings' => $query->bindings];
    });

    app(ClassPromotionService::class)->processPromotion($oneSourceYear, $oneTargetYear);
    $oneStudentQueries = $queries;

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->whereKey($manySourceYear->id)->update(['is_active' => true]);
    $queries = [];
    app(ClassPromotionService::class)->processPromotion($manySourceYear, $manyTargetYear);
    $manyStudentQueries = $queries;

    $oneRuleQueries = promotionPerformanceQueryCount($oneStudentQueries, 'class_promotion_rules');
    $manyRuleQueries = promotionPerformanceQueryCount($manyStudentQueries, 'class_promotion_rules');
    $oneClassQueries = promotionPerformanceQueryCount($oneStudentQueries, 'school_classes');
    $manyClassQueries = promotionPerformanceQueryCount($manyStudentQueries, 'school_classes');
    $studentReloads = collect($manyStudentQueries)->filter(
        fn (array $query): bool => str_contains($query['sql'], 'from "students"')
            && str_contains($query['sql'], 'where "id" = ?')
    );
    $targetEnrollmentLookups = collect($manyStudentQueries)->filter(
        fn (array $query): bool => str_contains($query['sql'], 'select "student_id" from "student_academic_enrollments"')
            && str_contains($query['sql'], '"student_id" in (')
    );

    expect($oneRuleQueries)->toBe(1)
        ->and($manyRuleQueries)->toBe($oneRuleQueries)
        ->and($oneClassQueries)->toBe(2)
        ->and($manyClassQueries)->toBe($oneClassQueries)
        ->and($studentReloads)->toBeEmpty()
        ->and($targetEnrollmentLookups)->toHaveCount(1);
});

it('bounds billing configuration rate applicability and duplicate lookup queries across students', function () {
    $monthly = makeBillType('SPP Perf Cache');
    $yearly = makeBillType('Buku Perf Cache');
    $oneTime = makeBillType('Pangkal Perf Cache');

    makeBillRate($monthly, 8, 500_000, ['effective_from' => '2020-01-01']);
    makeBillRate($yearly, 8, 700_000, [
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => '2020-01-01',
    ]);
    makeBillRate($oneTime, 8, 1_500_000, [
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2020-01-01',
    ]);

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMP);
    }

    [$oneSourceYear, $oneTargetYear] = promotionPerformanceYears(2034);
    $oneSourceClass = SchoolClass::create(['name' => 'VII Billing One', 'level' => 7]);
    $oneTargetClass = SchoolClass::create(['name' => 'VIII Billing One', 'level' => 8]);
    createPromotionRule($oneSourceClass, 'promote', $oneTargetClass);
    promotionPerformanceRoster($oneSourceYear, $oneSourceClass, 1);

    [$manySourceYear, $manyTargetYear] = promotionPerformanceYears(2036);
    $manySourceClass = SchoolClass::create(['name' => 'VII Billing Many', 'level' => 7]);
    $manyTargetClass = SchoolClass::create(['name' => 'VIII Billing Many', 'level' => 8]);
    createPromotionRule($manySourceClass, 'promote', $manyTargetClass);
    promotionPerformanceRoster($manySourceYear, $manySourceClass, 10);

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->whereKey($oneSourceYear->id)->update(['is_active' => true]);

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = ['sql' => strtolower($query->sql), 'bindings' => $query->bindings];
    });

    app(ClassPromotionService::class)->processPromotion($oneSourceYear, $oneTargetYear);
    $oneStudentQueries = $queries;

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->whereKey($manySourceYear->id)->update(['is_active' => true]);
    $queries = [];
    app(ClassPromotionService::class)->processPromotion($manySourceYear, $manyTargetYear);
    $manyStudentQueries = $queries;

    $oneRateQueries = promotionPerformanceQueryCount($oneStudentQueries, 'payment_rates');
    $manyRateQueries = promotionPerformanceQueryCount($manyStudentQueries, 'payment_rates');
    $oneMappingQueries = promotionPerformanceQueryCount($oneStudentQueries, 'payment_type_school_levels');
    $manyMappingQueries = promotionPerformanceQueryCount($manyStudentQueries, 'payment_type_school_levels');

    expect($oneRateQueries)->toBe(37)
        ->and($manyRateQueries)->toBe($oneRateQueries)
        ->and($oneMappingQueries)->toBe(5)
        ->and($manyMappingQueries)->toBe($oneMappingQueries);

    $oneBillSelects = collect($oneStudentQueries)->filter(
        fn (array $query): bool => str_starts_with($query['sql'], 'select')
            && str_contains($query['sql'], 'from "student_bills"')
    );
    $manyBillSelects = collect($manyStudentQueries)->filter(
        fn (array $query): bool => str_starts_with($query['sql'], 'select')
            && str_contains($query['sql'], 'from "student_bills"')
    );

    expect($oneBillSelects)->toHaveCount(1)
        ->and($manyBillSelects)->toHaveCount(1);
});

it('preserves exact monthly yearly one-time custom amount and duplicate outputs', function () {
    $monthly = makeBillType('SPP Output Equivalence');
    $yearly = makeBillType('Buku Output Equivalence');
    $oneTime = makeBillType('Pangkal Output Equivalence');
    makeBillRate($monthly, 8, 500_000, ['effective_from' => '2020-01-01']);
    makeBillRate($yearly, 8, 700_000, [
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => '2020-01-01',
    ]);
    makeBillRate($oneTime, 8, 1_500_000, [
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2020-01-01',
    ]);

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMP);
    }

    [$sourceYear, $targetYear] = promotionPerformanceYears(2038);
    $sourceClass = SchoolClass::create(['name' => 'VII Output', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Output', 'level' => 8]);
    createPromotionRule($sourceClass, 'promote', $targetClass);
    $student = promotionPerformanceRoster($sourceYear, $sourceClass, 1)->sole();

    $student->paymentSettings()
        ->where('payment_type_id', $monthly->id)
        ->update(['custom_amount' => 123_456]);
    $student->paymentSettings()
        ->where('payment_type_id', $yearly->id)
        ->delete();

    StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $monthly->id,
        'amount' => 111_111,
        'period_month' => 7,
        'period_year' => 2039,
        'billing_frequency' => BillFrequency::Monthly->value,
        'due_date' => null,
    ]);

    app(ClassPromotionService::class)->processPromotion($sourceYear, $targetYear);

    $student->refresh();
    $bills = $student->bills()
        ->whereIn('payment_type_id', [$monthly->id, $yearly->id, $oneTime->id])
        ->get();
    $monthlyBills = $bills->where('billing_frequency', BillFrequency::Monthly->value)
        ->sortBy(fn (StudentBill $bill): string => sprintf('%04d-%02d', $bill->period_year, $bill->period_month))
        ->values();
    $yearlyBill = $bills->where('billing_frequency', BillFrequency::Yearly->value)->sole();
    $oneTimeBill = $bills->where('billing_frequency', BillFrequency::OneTime->value)->sole();
    $targetEnrollment = $student->enrollments()->where('academic_year_id', $targetYear->id)->sole();

    expect($student->class_id)->toBe($targetClass->id)
        ->and($student->status)->toBe(StudentStatus::Active)
        ->and($targetEnrollment->school_class_id)->toBe($targetClass->id)
        ->and($targetEnrollment->status)->toBe('active')
        ->and($monthlyBills)->toHaveCount(12)
        ->and((float) $monthlyBills->first()->amount)->toBe(111_111.0)
        ->and($monthlyBills->skip(1)->every(fn (StudentBill $bill): bool => (float) $bill->amount === 123_456.0))->toBeTrue()
        ->and($monthlyBills->map(fn (StudentBill $bill): array => [$bill->period_month, $bill->period_year])->all())->toBe([
            [7, 2039], [8, 2039], [9, 2039], [10, 2039], [11, 2039], [12, 2039],
            [1, 2040], [2, 2040], [3, 2040], [4, 2040], [5, 2040], [6, 2040],
        ])
        ->and((float) $yearlyBill->amount)->toBe(700_000.0)
        ->and($yearlyBill->academic_year)->toBe('2039/2040')
        ->and($yearlyBill->period_month)->toBeNull()
        ->and($yearlyBill->period_year)->toBeNull()
        ->and((float) $oneTimeBill->amount)->toBe(1_500_000.0)
        ->and($oneTimeBill->academic_year)->toBe('2039/2040')
        ->and($oneTimeBill->period_month)->toBeNull()
        ->and($oneTimeBill->period_year)->toBeNull()
        ->and(StudentPaymentSetting::query()
            ->where('student_id', $student->id)
            ->where('payment_type_id', $yearly->id)
            ->whereDate('started_at', '2039-07-01')
            ->exists())->toBeTrue();
});

it('preserves graduation without target bills or target class reassignment', function () {
    [$sourceYear, $targetYear] = promotionPerformanceYears(2040);
    $finalClass = SchoolClass::create(['name' => 'IX Graduation Perf', 'level' => 9]);
    createPromotionRule($finalClass, 'graduate');
    $student = promotionPerformanceRoster($sourceYear, $finalClass, 1)->sole();

    $result = app(ClassPromotionService::class)->processPromotion($sourceYear, $targetYear);
    $targetEnrollment = $student->enrollments()->where('academic_year_id', $targetYear->id)->sole();

    expect($result)->toBe(['promoted' => 0, 'graduated' => 1, 'blocked' => 0])
        ->and($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and($student->fresh()->class_id)->toBe($finalClass->id)
        ->and($targetEnrollment->school_class_id)->toBe($finalClass->id)
        ->and($targetEnrollment->status)->toBe('lulus')
        ->and($student->bills()->count())->toBe(0);
});

it('rolls back every promotion mutation when billing fails', function () {
    [$sourceYear, $targetYear] = promotionPerformanceYears(2042);
    $sourceClass = SchoolClass::create(['name' => 'VII Rollback Perf', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Rollback Perf', 'level' => 8]);
    createPromotionRule($sourceClass, 'promote', $targetClass);
    $student = promotionPerformanceRoster($sourceYear, $sourceClass, 1)->sole();

    $failingBilling = new class extends BillGenerationService
    {
        public function generateBillbook(
            Student $student,
            CarbonInterface $startDate,
            ?CarbonInterface $monthlyStartDate = null,
        ): array {
            throw new RuntimeException('Forced billing failure.');
        }
    };

    expect(fn () => (new ClassPromotionService($failingBilling))->processPromotion($sourceYear, $targetYear))
        ->toThrow(RuntimeException::class, 'Forced billing failure.');

    expect($student->fresh()->class_id)->toBe($sourceClass->id)
        ->and($student->fresh()->status)->toBe(StudentStatus::Active)
        ->and($student->enrollments()->where('academic_year_id', $targetYear->id)->exists())->toBeFalse()
        ->and($student->bills()->count())->toBe(0)
        ->and($sourceYear->fresh()->is_active)->toBeTrue()
        ->and($targetYear->fresh()->is_active)->toBeFalse()
        ->and($targetYear->fresh()->promotion_processed_at)->toBeNull();
});
