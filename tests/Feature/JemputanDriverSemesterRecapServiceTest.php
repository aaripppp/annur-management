<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\BillAdjustment;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverDeductionRate;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverSemesterRecapService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function semesterRecapUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function semesterRecapYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function semesterRecapStudent(AcademicYear $year, string $name, int $level = 8): array
{
    $class = SchoolClass::factory()->create(['name' => $name.' Class', 'level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return [$student, $class];
}

function semesterRecapAssign(JemputanDriver $driver, Student $student, AcademicYear $year): void
{
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-07-01',
        'effective_until' => null,
    ]);
}

function semesterRecapBill(
    Student $student,
    PaymentType $type,
    int $year,
    int $month,
    float $amount,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'period_year' => $year,
        'period_month' => $month,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);
}

function semesterRecapRate(JemputanDriver $driver, int $year, int $month, float $percentage): void
{
    JemputanDriverDeductionRate::query()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => $year,
        'effective_month' => $month,
        'percentage' => $percentage,
    ]);
}

function semesterRecapAllocation(
    Student $student,
    AcademicYear $year,
    JemputanDriver $driver,
    int $periodYear,
    int $periodMonth,
    float $amount,
): void {
    JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'period_year' => $periodYear,
        'period_month' => $periodMonth,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

/** @return array<string, mixed> */
function semesterRecapReport(AcademicYear $year, ?SchoolLevel $level = null, int $semester = 1): array
{
    return app(JemputanDriverSemesterRecapService::class)->generate($year->id, $level, $semester);
}

beforeEach(function () {
    $this->actingAs(semesterRecapUser());
});

it('uses July through December for semester one and January through June for semester two', function () {
    $year = semesterRecapYear();

    $first = semesterRecapReport($year, null, 1);
    $second = semesterRecapReport($year, null, 2);

    expect(array_column($first['months'], 'key'))->toBe([
        '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12',
    ])->and(array_column($second['months'], 'key'))->toBe([
        '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06',
    ]);
});

it('isolates the selected academic year and school level', function () {
    $year = semesterRecapYear();
    $otherYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $smpDriver = JemputanDriver::factory()->create(['name' => 'Sopir SMP']);
    $sdDriver = JemputanDriver::factory()->create(['name' => 'Sopir SD']);
    $otherDriver = JemputanDriver::factory()->create(['name' => 'Sopir Tahun Lain']);
    [$smpStudent] = semesterRecapStudent($year, 'Siswa SMP', 8);
    [$sdStudent] = semesterRecapStudent($year, 'Siswa SD', 5);
    [$otherStudent] = semesterRecapStudent($otherYear, 'Siswa Tahun Lain', 8);

    semesterRecapAssign($smpDriver, $smpStudent, $year);
    semesterRecapAssign($sdDriver, $sdStudent, $year);
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $otherDriver->id,
        'student_id' => $otherStudent->id,
        'academic_year_id' => $otherYear->id,
        'effective_from' => '2027-07-01',
        'effective_until' => null,
    ]);
    semesterRecapRate($smpDriver, 2026, 7, 10);
    semesterRecapRate($sdDriver, 2026, 7, 10);
    semesterRecapRate($otherDriver, 2027, 7, 10);
    semesterRecapBill($smpStudent, $type, 2026, 7, 100000);
    semesterRecapBill($sdStudent, $type, 2026, 7, 900000);
    semesterRecapBill($otherStudent, $type, 2027, 7, 700000);

    $report = semesterRecapReport($year, SchoolLevel::SMP);

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['driver_name'])->toBe('Sopir SMP')
        ->and($report['rows'][0]['months']['2026-07']['total'])->toBe(100000)
        ->and($report['recap']['months']['2026-07']['total'])->toBe(100000);
});

it('uses effective bills and valid multi-driver allocations without double counting', function () {
    $year = semesterRecapYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir A']);
    $secondDriver = JemputanDriver::factory()->create(['name' => 'Sopir B']);
    [$student] = semesterRecapStudent($year, 'Siswa Multi');
    semesterRecapAssign($firstDriver, $student, $year);
    semesterRecapAssign($secondDriver, $student, $year);
    semesterRecapRate($firstDriver, 2026, 7, 10);
    semesterRecapRate($secondDriver, 2026, 7, 20);
    $bill = semesterRecapBill($student, $type, 2026, 7, 700000);
    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -100000,
    ]);
    semesterRecapAllocation($student, $year, $firstDriver, 2026, 7, 400000);
    semesterRecapAllocation($student, $year, $secondDriver, 2026, 7, 200000);

    $report = semesterRecapReport($year);
    $rows = collect($report['rows'])->keyBy('driver_name');

    expect($rows['Sopir A']['months']['2026-07'])->toMatchArray([
        'revenue' => 400000,
        'foundation_fee' => 40000,
        'driver_income' => 360000,
        'total' => 400000,
    ])->and($rows['Sopir B']['months']['2026-07'])->toMatchArray([
        'revenue' => 200000,
        'foundation_fee' => 40000,
        'driver_income' => 160000,
        'total' => 200000,
    ])->and($report['recap']['months']['2026-07'])->toBe([
        'driver_income' => 520000,
        'foundation_fee' => 80000,
        'total' => 600000,
    ])->and($report['warnings']['allocation']['total_count'])->toBe(0);
});

it('keeps missing stale and orphan allocations visible and marks the report not final', function () {
    $year = semesterRecapYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $drivers = JemputanDriver::factory()->count(2)->create();
    $outsider = JemputanDriver::factory()->create();

    foreach ($drivers as $driver) {
        semesterRecapRate($driver, 2026, 7, 10);
    }

    [$missing] = semesterRecapStudent($year, 'Siswa Missing');
    [$stale] = semesterRecapStudent($year, 'Siswa Stale');
    [$orphan] = semesterRecapStudent($year, 'Siswa Orphan');

    foreach ([$missing, $stale, $orphan] as $student) {
        foreach ($drivers as $driver) {
            semesterRecapAssign($driver, $student, $year);
        }
    }

    semesterRecapBill($missing, $type, 2026, 7, 100000);
    semesterRecapBill($stale, $type, 2026, 8, 200000);
    semesterRecapAllocation($stale, $year, $drivers[0], 2026, 8, 50000);
    semesterRecapAllocation($stale, $year, $drivers[1], 2026, 8, 50000);
    semesterRecapBill($orphan, $type, 2026, 9, 300000);
    semesterRecapAllocation($orphan, $year, $drivers[0], 2026, 9, 150000);
    semesterRecapAllocation($orphan, $year, $drivers[1], 2026, 9, 150000);
    semesterRecapAllocation($orphan, $year, $outsider, 2026, 9, 0);

    $report = semesterRecapReport($year);

    expect($report['warnings']['allocation'])->toMatchArray([
        'missing_count' => 1,
        'stale_count' => 1,
        'orphan_count' => 1,
        'total_count' => 3,
    ])->and($report['is_final'])->toBeFalse()
        ->and($report['recap']['months']['2026-07']['total'])->toBe(200000)
        ->and($report['recap']['months']['2026-08']['total'])->toBe(400000)
        ->and($report['recap']['months']['2026-09']['total'])->toBe(600000);
});

it('applies historical percentages per month and sums monthly rounding', function () {
    $year = semesterRecapYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Historis']);
    [$student] = semesterRecapStudent($year, 'Siswa Historis');
    semesterRecapAssign($driver, $student, $year);
    semesterRecapRate($driver, 2026, 7, 10);
    semesterRecapRate($driver, 2026, 9, 20);
    semesterRecapBill($student, $type, 2026, 7, 5);
    semesterRecapBill($student, $type, 2026, 8, 5);
    semesterRecapBill($student, $type, 2026, 9, 5);

    $before = semesterRecapReport($year)['rows'][0];
    semesterRecapRate($driver, 2026, 10, 30);
    $after = semesterRecapReport($year)['rows'][0];

    expect($before['months']['2026-07']['percentage'])->toBe(10.0)
        ->and($before['months']['2026-07']['foundation_fee'])->toBe(1)
        ->and($before['months']['2026-08']['foundation_fee'])->toBe(1)
        ->and($before['months']['2026-09']['percentage'])->toBe(20.0)
        ->and($before['months']['2026-09']['foundation_fee'])->toBe(1)
        ->and($before['totals']['foundation_fee'])->toBe(3)
        ->and($after['months']['2026-07'])->toMatchArray($before['months']['2026-07'])
        ->and($after['months']['2026-09'])->toMatchArray($before['months']['2026-09'])
        ->and($after['months']['2026-10']['percentage'])->toBe(30.0);
});

it('does not treat null percentages as zero and warns for legacy percentages', function () {
    $year = semesterRecapYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $missingDriver = JemputanDriver::factory()->create([
        'name' => 'Sopir Tanpa Tarif',
        'foundation_deduction_percentage' => null,
    ]);
    $legacyDriver = JemputanDriver::factory()->create([
        'name' => 'Sopir Legacy',
        'foundation_deduction_percentage' => 10,
    ]);
    [$missingStudent] = semesterRecapStudent($year, 'Siswa Tanpa Tarif');
    [$legacyStudent] = semesterRecapStudent($year, 'Siswa Legacy');
    semesterRecapAssign($missingDriver, $missingStudent, $year);
    semesterRecapAssign($legacyDriver, $legacyStudent, $year);
    semesterRecapBill($missingStudent, $type, 2026, 7, 100000);
    semesterRecapBill($legacyStudent, $type, 2026, 7, 100000);

    $report = semesterRecapReport($year);
    $rows = collect($report['rows'])->keyBy('driver_name');

    expect($rows['Sopir Tanpa Tarif']['months']['2026-07']['foundation_fee'])->toBeNull()
        ->and($rows['Sopir Tanpa Tarif']['months']['2026-07']['driver_income'])->toBeNull()
        ->and($rows['Sopir Legacy']['months']['2026-07']['foundation_fee'])->toBe(10000)
        ->and($report['warnings']['missing_rate_count'])->toBe(6)
        ->and($report['warnings']['legacy_rate_count'])->toBe(6)
        ->and($report['is_final'])->toBeFalse();
});

it('keeps queries bounded and performs no financial writes', function () {
    $year = semesterRecapYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $firstDriver = JemputanDriver::factory()->create();
    [$firstStudent] = semesterRecapStudent($year, 'Siswa Awal');
    semesterRecapAssign($firstDriver, $firstStudent, $year);
    semesterRecapRate($firstDriver, 2026, 7, 10);
    semesterRecapBill($firstStudent, $type, 2026, 7, 100000);

    DB::flushQueryLog();
    DB::enableQueryLog();
    semesterRecapReport($year);
    $smallSelectCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();

    foreach (range(1, 15) as $index) {
        $driver = JemputanDriver::factory()->create();
        [$student] = semesterRecapStudent($year, 'Siswa Banyak '.$index);
        semesterRecapAssign($driver, $student, $year);
        semesterRecapRate($driver, 2026, 7, 10);
        semesterRecapBill($student, $type, 2026, 7, 100000);
    }

    $before = [
        StudentBill::query()->count(),
        BillAdjustment::query()->count(),
        JemputanDriverSalaryAllocation::query()->count(),
        JemputanDriverDeductionRate::query()->count(),
        Payment::query()->count(),
        PaymentDetail::query()->count(),
    ];
    DB::flushQueryLog();
    semesterRecapReport($year);
    $largeQueries = collect(DB::getQueryLog());
    $largeSelectCount = $largeQueries
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();
    $after = [
        StudentBill::query()->count(),
        BillAdjustment::query()->count(),
        JemputanDriverSalaryAllocation::query()->count(),
        JemputanDriverDeductionRate::query()->count(),
        Payment::query()->count(),
        PaymentDetail::query()->count(),
    ];

    expect($largeSelectCount)->toBe($smallSelectCount)
        ->and($largeSelectCount)->toBeLessThanOrEqual(9)
        ->and($largeQueries->filter(fn (array $query): bool => str_contains($query['query'], 'jemputan_driver_deduction_rates'))->count())->toBe(1)
        ->and($after)->toBe($before);
});

it('authorizes report generation', function (string $role, bool $active) {
    $year = semesterRecapYear();
    $service = app(JemputanDriverSemesterRecapService::class);
    auth()->logout();
    $this->actingAs(semesterRecapUser($role, $active));

    expect(fn () => $service->generate($year->id, null, 1))
        ->toThrow(AuthorizationException::class);
})->with([
    'TU Unit' => [User::ROLE_TU_UNIT, true],
    'inactive administrator' => [User::ROLE_ADMIN, false],
]);
