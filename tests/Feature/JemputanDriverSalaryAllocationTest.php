<?php

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function allocationAcademicYear(
    string $year = '2026/2027',
    string $startDate = '2026-07-01',
    string $endDate = '2027-06-30',
): AcademicYear {
    return AcademicYear::query()->firstOrCreate(
        ['year' => $year],
        ['is_active' => true, 'start_date' => $startDate, 'end_date' => $endDate],
    );
}

function allocationRecord(
    Student $student,
    AcademicYear $academicYear,
    JemputanDriver $driver,
    int $month = 7,
    string $amount = '300000.00',
    int $year = 2026,
): JemputanDriverSalaryAllocation {
    return JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'period_year' => $year,
        'period_month' => $month,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

it('creates the allocation table with the expected columns', function () {
    expect(Schema::hasTable('jemputan_driver_salary_allocations'))->toBeTrue();

    $columns = Schema::getColumnListing('jemputan_driver_salary_allocations');

    expect($columns)->toContain(
        'id',
        'student_id',
        'academic_year_id',
        'period_year',
        'period_month',
        'jemputan_driver_id',
        'allocated_amount',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    );
});

it('stores and resolves its model relations', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    $allocation = JemputanDriverSalaryAllocation::factory()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'period_year' => 2026,
        'period_month' => 8,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => '275000.50',
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);

    expect($allocation->student->is($student))->toBeTrue()
        ->and($allocation->driver->is($driver))->toBeTrue()
        ->and($allocation->academicYear->is($academicYear))->toBeTrue()
        ->and($allocation->createdBy->is($creator))->toBeTrue()
        ->and($allocation->updatedBy->is($updater))->toBeTrue()
        ->and($student->jemputanDriverSalaryAllocations()->count())->toBe(1)
        ->and($driver->salaryAllocations()->count())->toBe(1)
        ->and($academicYear->jemputanDriverSalaryAllocations()->count())->toBe(1);
});

it('prevents duplicate allocations for the same student period and driver', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    allocationRecord($student, $academicYear, $driver, 7, '300000.00');

    expect(fn () => allocationRecord($student, $academicYear, $driver, 7, '200000.00'))
        ->toThrow(QueryException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(1);
});

it('allows one student to have allocations for multiple drivers', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();

    allocationRecord($student, $academicYear, $firstDriver, 7, '300000.00');
    allocationRecord($student, $academicYear, $secondDriver, 7, '200000.00');

    expect($student->jemputanDriverSalaryAllocations()->count())->toBe(2)
        ->and((float) $student->jemputanDriverSalaryAllocations()->sum('allocated_amount'))->toBe(500000.0);
});

it('allows allocations for different months', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    allocationRecord($student, $academicYear, $driver, 7, '300000.00');
    allocationRecord($student, $academicYear, $driver, 8, '350000.00');

    expect($student->jemputanDriverSalaryAllocations()->count())->toBe(2);
});

it('isolates allocations by academic year', function () {
    $firstYear = allocationAcademicYear('2026/2027', '2026-07-01', '2027-06-30');
    $secondYear = allocationAcademicYear('2027/2028', '2027-07-01', '2028-06-30');
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    allocationRecord($student, $firstYear, $driver, 7, '300000.00');
    allocationRecord($student, $secondYear, $driver, 7, '400000.00');

    expect($student->jemputanDriverSalaryAllocations()->count())->toBe(2)
        ->and($firstYear->jemputanDriverSalaryAllocations()->count())->toBe(1)
        ->and($secondYear->jemputanDriverSalaryAllocations()->count())->toBe(1);
});

it('stores allocated amounts with decimal precision', function () {
    $allocation = JemputanDriverSalaryAllocation::factory()->create([
        'allocated_amount' => '123456.78',
    ])->refresh();

    expect($allocation->allocated_amount)->toBe('123456.78')
        ->and((float) $allocation->allocated_amount)->toBe(123456.78);
});

it('enforces the student and driver foreign keys', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    expect(fn () => JemputanDriverSalaryAllocation::query()->create([
        'student_id' => 999999,
        'academic_year_id' => $academicYear->id,
        'period_year' => 2026,
        'period_month' => 7,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => '100000.00',
    ]))->toThrow(QueryException::class);

    expect(fn () => JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'period_year' => 2026,
        'period_month' => 7,
        'jemputan_driver_id' => 999999,
        'allocated_amount' => '100000.00',
    ]))->toThrow(QueryException::class);
});

it('restricts deletion of historical allocation references', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    allocationRecord($student, $academicYear, $driver);

    expect(fn () => $driver->delete())->toThrow(QueryException::class)
        ->and(fn () => $academicYear->delete())->toThrow(QueryException::class)
        ->and(fn () => $student->delete())->toThrow(QueryException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(1);
});

it('nulls creator and updater references when a user is deleted', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    $allocation = JemputanDriverSalaryAllocation::factory()->create([
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);

    $creator->delete();
    $updater->delete();

    expect($allocation->refresh())
        ->created_by->toBeNull()
        ->updated_by->toBeNull();
});

it('keeps student bills untouched when an allocation exists', function () {
    $academicYear = allocationAcademicYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'amount' => '500000.00',
        'period_year' => 2026,
        'period_month' => 7,
    ]);

    allocationRecord($student, $academicYear, $driver, 7, '300000.00');

    expect($bill->refresh()->amount)->toBe('500000.00');
});

it('rolls the allocation migration back and forward', function () {
    $migration = require database_path('migrations/2026_11_10_160041_create_jemputan_driver_salary_allocations_table.php');

    $migration->down();

    expect(Schema::hasTable('jemputan_driver_salary_allocations'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('jemputan_driver_salary_allocations'))->toBeTrue();
});
