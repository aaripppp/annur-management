<?php

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverDeductionRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverDeductionRateResolver;
use App\Services\JemputanDriverSalaryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function deductionRateTestDriver(?float $legacyPercentage = null): JemputanDriver
{
    return JemputanDriver::factory()->create([
        'foundation_deduction_percentage' => $legacyPercentage,
    ]);
}

function deductionRateTestRate(
    JemputanDriver $driver,
    int $year,
    int $month,
    float $percentage,
    ?string $notes = null,
): JemputanDriverDeductionRate {
    return JemputanDriverDeductionRate::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => $year,
        'effective_month' => $month,
        'percentage' => $percentage,
        'notes' => $notes,
    ]);
}

function deductionRateTestYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function deductionRateTestStudent(AcademicYear $year, string $name): array
{
    $class = SchoolClass::query()->firstOrCreate(['name' => 'VIII Tarif'], ['level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return [$student, $class];
}

function deductionRateTestBill(
    Student $student,
    PaymentType $type,
    int $month,
    int $year,
    float $amount,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => $amount,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]));
});

it('creates the deduction rate table with driver period uniqueness and relations', function () {
    $creator = User::factory()->create();
    $driver = deductionRateTestDriver();

    expect(Schema::hasTable('jemputan_driver_deduction_rates'))->toBeTrue()
        ->and(Schema::hasColumns('jemputan_driver_deduction_rates', [
            'id',
            'jemputan_driver_id',
            'effective_year',
            'effective_month',
            'percentage',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $rate = deductionRateTestRate($driver, 2026, 7, 7.25, 'Tarif awal');
    $rate->update(['created_by' => $creator->id]);

    expect($rate->refresh()->driver->is($driver))->toBeTrue()
        ->and($rate->createdBy->is($creator))->toBeTrue()
        ->and($rate->percentage)->toBe('7.25');

    expect(fn () => deductionRateTestRate($driver, 2026, 7, 9))
        ->toThrow(QueryException::class);
});

it('supports zero whole and decimal percentage rates', function (float $percentage) {
    $driver = deductionRateTestDriver();
    deductionRateTestRate($driver, 2026, 7, $percentage);

    $rate = app(JemputanDriverDeductionRateResolver::class)->resolve($driver, 2026, 7);

    expect($rate['configured'])->toBeTrue()
        ->and($rate['percentage'])->toBe($percentage)
        ->and($rate['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY);
})->with([0.0, 7.0, 12.0, 15.0, 12.5]);

it('uses the newest effective rate on or before the report month', function (int $year, int $month, float $expected) {
    $driver = deductionRateTestDriver();
    deductionRateTestRate($driver, 2026, 7, 7);
    deductionRateTestRate($driver, 2026, 11, 12);
    deductionRateTestRate($driver, 2027, 2, 15);

    $rate = app(JemputanDriverDeductionRateResolver::class)->resolve($driver, $year, $month);

    expect($rate['percentage'])->toBe($expected)
        ->and($rate['configured'])->toBeTrue()
        ->and($rate['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY);
})->with([
    'juli' => [2026, 7, 7.0],
    'oktober' => [2026, 10, 7.0],
    'november' => [2026, 11, 12.0],
    'desember' => [2026, 12, 12.0],
    'januari' => [2027, 1, 12.0],
    'februari' => [2027, 2, 15.0],
    'maret' => [2027, 3, 15.0],
]);

it('does not apply a future rate to months before the first rate', function () {
    $driver = deductionRateTestDriver();
    deductionRateTestRate($driver, 2026, 11, 12);
    deductionRateTestRate($driver, 2027, 2, 15);

    $rate = app(JemputanDriverDeductionRateResolver::class)->resolve($driver, 2026, 10);

    expect($rate['percentage'])->toBeNull()
        ->and($rate['configured'])->toBeFalse()
        ->and($rate['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_NONE)
        ->and($rate['effective_year'])->toBeNull()
        ->and($rate['effective_month'])->toBeNull();
});

it('keeps previous months unchanged when a newer rate is added', function () {
    $driver = deductionRateTestDriver();
    $resolver = app(JemputanDriverDeductionRateResolver::class);
    deductionRateTestRate($driver, 2026, 7, 7);

    $before = $resolver->resolve($driver, 2026, 10);
    deductionRateTestRate($driver, 2026, 11, 12);
    $after = $resolver->resolve($driver, 2026, 10);

    expect($before['percentage'])->toBe(7.0)
        ->and($after['percentage'])->toBe(7.0)
        ->and($after['effective_year'])->toBe(2026)
        ->and($after['effective_month'])->toBe(7);
});

it('keeps rates isolated between drivers', function () {
    $first = deductionRateTestDriver();
    $second = deductionRateTestDriver();
    deductionRateTestRate($first, 2026, 7, 7);
    deductionRateTestRate($second, 2026, 7, 12);
    $resolver = app(JemputanDriverDeductionRateResolver::class);

    expect($resolver->resolve($first, 2026, 10)['percentage'])->toBe(7.0)
        ->and($resolver->resolve($second, 2026, 10)['percentage'])->toBe(12.0);
});

it('resolves a rate across academic year boundaries', function () {
    $driver = deductionRateTestDriver();
    deductionRateTestRate($driver, 2027, 2, 15);

    $rate = app(JemputanDriverDeductionRateResolver::class)->resolve($driver, 2027, 8);

    expect($rate['percentage'])->toBe(15.0)
        ->and($rate['effective_year'])->toBe(2027)
        ->and($rate['effective_month'])->toBe(2);
});

it('uses the legacy column only as an identifiable provisional fallback', function () {
    $driver = deductionRateTestDriver(9.5);
    $resolver = app(JemputanDriverDeductionRateResolver::class);

    $fallback = $resolver->resolve($driver, 2026, 10);
    $withoutFallback = $resolver->resolve($driver, 2026, 10, allowLegacyFallback: false);

    expect($fallback['percentage'])->toBe(9.5)
        ->and($fallback['configured'])->toBeTrue()
        ->and($fallback['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
        ->and($fallback['effective_year'])->toBeNull()
        ->and($fallback['effective_month'])->toBeNull()
        ->and($withoutFallback['percentage'])->toBeNull()
        ->and($withoutFallback['configured'])->toBeFalse()
        ->and($withoutFallback['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_NONE);
});

it('prefers stored history over the legacy fallback and keeps null separate from zero', function () {
    $driver = deductionRateTestDriver(99);
    deductionRateTestRate($driver, 2026, 7, 0);
    $resolver = app(JemputanDriverDeductionRateResolver::class);

    $zeroRate = $resolver->resolve($driver, 2026, 7);
    $noHistoryDriver = deductionRateTestDriver(null);
    $none = $resolver->resolve($noHistoryDriver, 2026, 7);

    expect($zeroRate['percentage'])->toBe(0.0)
        ->and($zeroRate['configured'])->toBeTrue()
        ->and($zeroRate['source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY)
        ->and($none['percentage'])->toBeNull()
        ->and($none['configured'])->toBeFalse();
});

it('uses the same historical percentage on the salary report and export', function (int $month, float $percentage, int $deduction) {
    $year = deductionRateTestYear();
    $driver = deductionRateTestDriver(99);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    [$student] = deductionRateTestStudent($year, 'Siswa Tarif '.$month);
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-07-01',
        'effective_until' => null,
    ]);
    deductionRateTestRate($driver, 2026, 7, 7);
    deductionRateTestRate($driver, 2026, 11, 12);
    deductionRateTestRate($driver, 2027, 2, 15);
    deductionRateTestBill($student, $type, $month, $month >= 7 ? 2026 : 2027, 1000000);
    $service = app(JemputanDriverSalaryService::class);
    $monthKey = sprintf('%04d-%02d', $month >= 7 ? 2026 : 2027, $month);

    $report = $service->generate($year->id, $driver->id, $monthKey);
    $export = $service->exportData($year->id, $driver->id, $monthKey);

    foreach ([$report, $export] as $result) {
        expect($result['summary']['percentage'])->toBe($percentage)
            ->and($result['summary']['deduction'])->toBe($deduction)
            ->and($result['summary']['net_salary'])->toBe(1000000 - $deduction)
            ->and($result['summary']['percentage_source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY)
            ->and($result['summary']['percentage_is_legacy'])->toBeFalse()
            ->and($result['summary']['percentage_effective_year'])->toBe($month >= 2 && $month <= 6 ? 2027 : 2026)
            ->and($result['summary']['percentage_effective_month'])->toBe($month >= 2 && $month <= 6 ? 2 : ($month >= 11 ? 11 : 7));
    }
})->with([
    'oktober' => [10, 7.0, 70000],
    'desember' => [12, 12.0, 120000],
    'maret' => [3, 15.0, 150000],
]);

it('adds one bounded rate query per report without changing financial data', function () {
    $year = deductionRateTestYear();
    $driver = deductionRateTestDriver(10);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    deductionRateTestRate($driver, 2026, 7, 7);

    foreach (range(1, 25) as $index) {
        [$student] = deductionRateTestStudent($year, 'Siswa Bounded '.$index);
        JemputanDriverAssignment::query()->create([
            'jemputan_driver_id' => $driver->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'effective_from' => '2026-07-01',
            'effective_until' => null,
        ]);
        deductionRateTestBill($student, $type, 7, 2026, 100000);
    }

    $service = app(JemputanDriverSalaryService::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = $service->generate($year->id, $driver->id, '2026-07');
    $rateQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'jemputan_driver_deduction_rates'))
        ->count();
    DB::disableQueryLog();

    expect($report['rows'])->toHaveCount(25)
        ->and($report['summary']['percentage'])->toBe(7.0)
        ->and($rateQueries)->toBe(1)
        ->and(StudentBill::query()->count())->toBe(25)
        ->and(JemputanDriverDeductionRate::query()->count())->toBe(1);
});
