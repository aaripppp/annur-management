<?php

use App\Livewire\JemputanDriverManagement;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverAssignmentService;
use App\Services\StudentDeletionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function jemputanManager(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanAcademicYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function jemputanStudent(AcademicYear $academicYear): Student
{
    $student = Student::factory()->create();

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);

    return $student;
}

it('creates edits toggles and deletes an unassigned driver', function () {
    $academicYear = jemputanAcademicYear();
    $this->actingAs(jemputanManager());

    $component = Livewire::test(JemputanDriverManagement::class)
        ->call('openCreateDriver')
        ->set('driverName', 'Pak Budi')
        ->set('driverPhone', '08123456789')
        ->call('saveDriver')
        ->assertHasNoErrors();

    $driver = JemputanDriver::query()->where('name', 'Pak Budi')->firstOrFail();

    $component
        ->call('editDriver', $driver->id)
        ->set('driverName', 'Pak Budi Santoso')
        ->set('driverPhone', '')
        ->call('saveDriver')
        ->assertHasNoErrors()
        ->call('toggleDriverStatus', $driver->id);

    expect($driver->refresh()->name)->toBe('Pak Budi Santoso')
        ->and($driver->phone)->toBeNull()
        ->and($driver->is_active)->toBeFalse();

    $component->call('deleteDriver', $driver->id);

    expect(JemputanDriver::query()->whereKey($driver->id)->exists())->toBeFalse()
        ->and($academicYear->exists)->toBeTrue();
});

it('adds a current membership without month input or financial writes', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);

    $billCount = StudentBill::query()->count();
    $paymentCount = Payment::query()->count();
    $assignment = $service->assign($driver->id, $student->id, $academicYear->id);

    expect($assignment->effective_from->betweenIncluded($academicYear->start_date, $academicYear->end_date))->toBeTrue()
        ->and($assignment->effective_until)->toBeNull()
        ->and(StudentBill::query()->count())->toBe($billCount)
        ->and(Payment::query()->count())->toBe($paymentCount);
});

it('rejects duplicate current membership for one student in the same academic year', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);

    $service->assign($firstDriver->id, $student->id, $academicYear->id);

    expect(fn () => $service->assign($secondDriver->id, $student->id, $academicYear->id))
        ->toThrow(ValidationException::class);

    expect(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('removes and re-adds a student immediately while preserving the closed row', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $drivers = JemputanDriver::factory()->count(2)->create();
    $service = app(JemputanDriverAssignmentService::class);

    $first = $service->assign($drivers[0]->id, $student->id, $academicYear->id);
    $closed = $service->remove($first->id);
    $second = $service->assign($drivers[1]->id, $student->id, $academicYear->id);

    expect($closed->effective_until?->toDateString())->toBe($closed->effective_from->toDateString())
        ->and($second->effective_from->toDateString())->toBe($closed->effective_until?->toDateString())
        ->and($second->effective_until)->toBeNull()
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('ignores legacy period overlap when no current membership exists', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $legacyDriver = JemputanDriver::factory()->create();
    $currentDriver = JemputanDriver::factory()->create();
    $legacy = JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $legacyDriver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-07-01',
        'effective_until' => '2027-06-30',
    ]);

    $current = app(JemputanDriverAssignmentService::class)
        ->assign($currentDriver->id, $student->id, $academicYear->id);

    expect($legacy->refresh()->effective_until?->toDateString())->toBe('2027-06-30')
        ->and($current->effective_until)->toBeNull()
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('moves a student immediately and preserves the prior row', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $oldDriver = JemputanDriver::factory()->create();
    $newDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $oldAssignment = $service->assign($oldDriver->id, $student->id, $academicYear->id);

    $newAssignment = $service->move($oldAssignment->id, $newDriver->id);

    expect($oldAssignment->refresh()->effective_until?->toDateString())->toBe($newAssignment->effective_from->toDateString())
        ->and($newAssignment->effective_until)->toBeNull()
        ->and($newAssignment->jemputan_driver_id)->toBe($newDriver->id)
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('isolates current memberships by academic year', function () {
    $firstYear = jemputanAcademicYear();
    $secondYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $student = jemputanStudent($firstYear);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $secondYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);
    $drivers = JemputanDriver::factory()->count(2)->create();
    $service = app(JemputanDriverAssignmentService::class);

    $first = $service->assign($drivers[0]->id, $student->id, $firstYear->id);
    $second = $service->assign($drivers[1]->id, $student->id, $secondYear->id);

    expect($first->academic_year_id)->toBe($firstYear->id)
        ->and($second->academic_year_id)->toBe($secondYear->id)
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->count())->toBe(2);
});

it('rejects new and moved assignments to inactive drivers', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $activeDriver = JemputanDriver::factory()->create();
    $inactiveDriver = JemputanDriver::factory()->inactive()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $assignment = $service->assign($activeDriver->id, $student->id, $academicYear->id);

    expect(fn () => $service->assign($inactiveDriver->id, Student::factory()->create()->id, $academicYear->id))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->move($assignment->id, $inactiveDriver->id))
        ->toThrow(ValidationException::class);
});

it('preserves assignments when a driver becomes inactive and prevents assigned driver deletion', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    $assignment = app(JemputanDriverAssignmentService::class)
        ->assign($driver->id, $student->id, $academicYear->id);

    $driver->update(['is_active' => false]);

    expect($assignment->fresh())->not->toBeNull()
        ->and(fn () => $driver->delete())->toThrow(QueryException::class);
});

it('enforces student foreign-key integrity and cleans assignments with existing student deletion', function () {
    $academicYear = jemputanAcademicYear();
    $student = jemputanStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    app(JemputanDriverAssignmentService::class)->assign($driver->id, $student->id, $academicYear->id);

    expect(fn () => JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => 999999,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-08-01',
    ]))->toThrow(QueryException::class);

    app(StudentDeletionService::class)->delete($student->id);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->exists())->toBeFalse()
        ->and(JemputanDriver::query()->whereKey($driver->id)->exists())->toBeTrue();
});

it('allows active admins and super admins to access the module', function (string $role) {
    jemputanAcademicYear();

    $this->actingAs(jemputanManager($role))
        ->get(route('jemputan.drivers.index'))
        ->assertOk()
        ->assertSee('Kelompok Sopir Jemputan');
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies guests TU Unit and inactive administrators', function () {
    $this->get(route('jemputan.drivers.index'))->assertRedirect(route('login'));

    $this->actingAs(jemputanManager(User::ROLE_TU_UNIT))
        ->get(route('jemputan.drivers.index'))
        ->assertRedirect(route('tu.pending'));

    $this->actingAs(jemputanManager(User::ROLE_ADMIN, false))
        ->get(route('jemputan.drivers.index'))
        ->assertRedirect(route('login'));
});

it('blocks hidden Livewire mutations from unauthorized users', function (string $role, bool $active) {
    jemputanAcademicYear();
    $this->actingAs(jemputanManager($role, $active));

    Livewire::test(JemputanDriverManagement::class)->assertForbidden();

    expect(JemputanDriver::query()->count())->toBe(0);
})->with([
    'TU Unit' => [User::ROLE_TU_UNIT, true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);

it('shows Jemputan navigation only to authorized administrators', function () {
    $admin = jemputanManager();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSee('Jemputan')
        ->assertSee('Kelompok Sopir')
        ->assertSee(route('jemputan.drivers.index'), false);

    $this->actingAs(jemputanManager(User::ROLE_TU_UNIT))
        ->get(route('siswa.index'))
        ->assertDontSee('Kelompok Sopir')
        ->assertDontSee(route('jemputan.drivers.index'), false);
});

it('keeps the driver list query count bounded as rows grow', function () {
    jemputanAcademicYear();
    $this->actingAs(jemputanManager());
    JemputanDriver::factory()->count(20)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(JemputanDriverManagement::class)->assertOk();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'));

    DB::disableQueryLog();

    expect($queries->count())->toBeLessThanOrEqual(12);
});
