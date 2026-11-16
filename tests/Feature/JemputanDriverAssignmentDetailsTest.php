<?php

use App\Livewire\JemputanDriverManagement;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\JemputanDriverAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function assignmentDetailsYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function assignmentDetailsStudent(AcademicYear $academicYear, string $name): Student
{
    $class = SchoolClass::factory()->create(['name' => 'Kelas '.$name, 'level' => 8]);
    $student = Student::factory()->create([
        'nama_lengkap' => $name,
        'class_id' => $class->id,
    ]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

/** @return array<string, array<int, array<string, mixed>>> */
function assignmentDetailsFinancialSnapshot(): array
{
    return collect([
        'student_bills',
        'payments',
        'payment_details',
        'bill_adjustments',
        'student_payment_settings',
        'jemputan_driver_salary_allocations',
    ])->mapWithKeys(fn (string $table): array => [
        $table => DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
    ])->all();
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]));
});

it('adds members with optional destination and service type through the UI', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Detail Create']);
    $withDetails = assignmentDetailsStudent($academicYear, 'Siswa Dengan Detail');
    $withoutDetails = assignmentDetailsStudent($academicYear, 'Siswa Tanpa Detail');

    $component = Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openAssignmentModal')
        ->assertSee('Contoh: Cikarang Baru')
        ->assertSee('PP')
        ->assertSee('Pulang')
        ->assertSee('Pergi')
        ->call('selectStudent', $withDetails->id)
        ->set('assignmentDestination', '  Cikarang Baru  ')
        ->set('assignmentServiceType', 'PP')
        ->call('assignStudent')
        ->assertHasNoErrors();

    $component
        ->call('openAssignmentModal')
        ->call('selectStudent', $withoutDetails->id)
        ->call('assignStudent')
        ->assertHasNoErrors();

    $first = JemputanDriverAssignment::query()->where('student_id', $withDetails->id)->sole();
    $second = JemputanDriverAssignment::query()->where('student_id', $withoutDetails->id)->sole();

    expect($first->destination)->toBe('Cikarang Baru')
        ->and($first->service_type)->toBe('PP')
        ->and($second->destination)->toBeNull()
        ->and($second->service_type)->toBeNull();
});

it('edits legacy blank details without changing membership identity or period', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Detail Edit']);
    $student = assignmentDetailsStudent($academicYear, 'Siswa Detail Lama');
    $assignment = JemputanDriverAssignment::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-07-01',
        'effective_until' => '2027-01-01',
        'destination' => null,
        'service_type' => null,
    ]);
    $membershipBefore = [
        'student_id' => $assignment->student_id,
        'jemputan_driver_id' => $assignment->jemputan_driver_id,
        'academic_year_id' => $assignment->academic_year_id,
        'effective_from' => $assignment->effective_from->toDateString(),
        'effective_until' => $assignment->effective_until?->toDateString(),
    ];
    $financialBefore = assignmentDetailsFinancialSnapshot();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('setGroupTab', 'history')
        ->assertSee('—')
        ->call('openEditAssignmentDetails', $assignment->id)
        ->assertSet('isGroupModalOpen', false)
        ->assertSet('isEditAssignmentDetailsModalOpen', true)
        ->assertSee('SISWA DETAIL LAMA')
        ->assertSee('Sopir Detail Edit')
        ->set('editAssignmentDestination', 'Grand Cikarang City')
        ->set('editAssignmentServiceType', 'Pulang')
        ->call('saveAssignmentDetails')
        ->assertHasNoErrors()
        ->assertSet('isEditAssignmentDetailsModalOpen', false)
        ->assertSet('isGroupModalOpen', true)
        ->assertSee('Grand Cikarang City')
        ->assertSee('Pulang');

    $assignment->refresh();

    expect($assignment->destination)->toBe('Grand Cikarang City')
        ->and($assignment->service_type)->toBe('Pulang')
        ->and([
            'student_id' => $assignment->student_id,
            'jemputan_driver_id' => $assignment->jemputan_driver_id,
            'academic_year_id' => $assignment->academic_year_id,
            'effective_from' => $assignment->effective_from->toDateString(),
            'effective_until' => $assignment->effective_until?->toDateString(),
        ])->toBe($membershipBefore)
        ->and(assignmentDetailsFinancialSnapshot())->toBe($financialBefore);
});

it('rejects invalid service types in the UI and assignment service', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create();
    $student = assignmentDetailsStudent($academicYear, 'Siswa Validasi Detail');
    $assignment = JemputanDriverAssignment::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
    ]);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openEditAssignmentDetails', $assignment->id)
        ->set('editAssignmentServiceType', 'Antar Jemput Khusus')
        ->call('saveAssignmentDetails')
        ->assertHasErrors('editAssignmentServiceType');

    expect(fn () => app(JemputanDriverAssignmentService::class)->updateDetails(
        $assignment->id,
        'Tujuan tidak tersimpan',
        'Antar Jemput Khusus',
    ))->toThrow(ValidationException::class)
        ->and($assignment->fresh()?->destination)->toBeNull()
        ->and($assignment->fresh()?->service_type)->toBeNull();
});

it('blocks detail mutations from unauthorized users', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create();
    $student = assignmentDetailsStudent($academicYear, 'Siswa Detail Terlarang');
    $assignment = JemputanDriverAssignment::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
    ]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_TU_UNIT, 'is_active' => true]));

    Livewire::test(JemputanDriverManagement::class)->assertForbidden();

    expect($assignment->fresh()?->destination)->toBeNull()
        ->and($assignment->fresh()?->service_type)->toBeNull();
});

it('keeps details in ended history and creates independent details when rejoining', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create();
    $student = assignmentDetailsStudent($academicYear, 'Siswa Join Ulang Detail');
    $service = app(JemputanDriverAssignmentService::class);
    $first = $service->assign(
        $driver->id,
        $student->id,
        $academicYear->id,
        '2026-07',
        'Tujuan Periode Pertama',
        'Pergi',
    );
    $service->remove($first->id, '2026-09');
    $second = $service->assign(
        $driver->id,
        $student->id,
        $academicYear->id,
        '2026-11',
        'Tujuan Periode Kedua',
        'PP',
    );

    expect($first->fresh()?->destination)->toBe('Tujuan Periode Pertama')
        ->and($first->fresh()?->service_type)->toBe('Pergi')
        ->and($first->fresh()?->effective_until?->toDateString())->toBe('2026-09-01')
        ->and($second->destination)->toBe('Tujuan Periode Kedua')
        ->and($second->service_type)->toBe('PP')
        ->and($second->id)->not->toBe($first->id);
});

it('copies details on move while preserving independent multi-driver assignments', function () {
    $academicYear = assignmentDetailsYear();
    $student = assignmentDetailsStudent($academicYear, 'Siswa Pindah Detail');
    $sourceDriver = JemputanDriver::factory()->create();
    $targetDriver = JemputanDriver::factory()->create();
    $otherDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $source = $service->assign(
        $sourceDriver->id,
        $student->id,
        $academicYear->id,
        '2026-07',
        'Tujuan Asal',
        'PP',
    );
    $other = $service->assign(
        $otherDriver->id,
        $student->id,
        $academicYear->id,
        '2026-07',
        'Tujuan Multi Lain',
        'Pergi',
    );
    $target = $service->move($source->id, $targetDriver->id, '2026-11');
    $service->updateDetails($target->id, 'Tujuan Baru', 'Pulang');

    expect($source->fresh()?->destination)->toBe('Tujuan Asal')
        ->and($source->fresh()?->service_type)->toBe('PP')
        ->and($source->fresh()?->effective_until?->toDateString())->toBe('2026-11-01')
        ->and($target->fresh()?->destination)->toBe('Tujuan Baru')
        ->and($target->fresh()?->service_type)->toBe('Pulang')
        ->and($target->fresh()?->effective_from->toDateString())->toBe('2026-11-01')
        ->and($other->fresh()?->destination)->toBe('Tujuan Multi Lain')
        ->and($other->fresh()?->service_type)->toBe('Pergi');
});

it('shows details in active and history tabs while retaining pagination', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create();

    foreach (range(1, 11) as $index) {
        JemputanDriverAssignment::factory()->create([
            'jemputan_driver_id' => $driver->id,
            'student_id' => assignmentDetailsStudent($academicYear, 'Siswa Detail '.$index)->id,
            'academic_year_id' => $academicYear->id,
            'destination' => 'Tujuan '.$index,
            'service_type' => $index % 2 === 0 ? 'PP' : 'Pergi',
        ]);
    }

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertSee('Tujuan')
        ->assertSee('Ket')
        ->assertSee('Edit Detail Jemputan')
        ->call('setPage', 2, 'assignmentsPage')
        ->assertSee('Tujuan 11')
        ->call('setGroupTab', 'history')
        ->assertSee('Tujuan 11')
        ->assertSee('Pergi');
});

it('keeps group detail queries bounded as rows grow', function () {
    $academicYear = assignmentDetailsYear();
    $driver = JemputanDriver::factory()->create();
    JemputanDriverAssignment::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => assignmentDetailsStudent($academicYear, 'Siswa Query Detail 1')->id,
        'academic_year_id' => $academicYear->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(JemputanDriverManagement::class)->call('viewGroup', $driver->id);
    $smallSelects = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'),
    )->count();

    foreach (range(2, 20) as $index) {
        JemputanDriverAssignment::factory()->create([
            'jemputan_driver_id' => $driver->id,
            'student_id' => assignmentDetailsStudent($academicYear, 'Siswa Query Detail '.$index)->id,
            'academic_year_id' => $academicYear->id,
        ]);
    }

    DB::flushQueryLog();
    Livewire::test(JemputanDriverManagement::class)->call('viewGroup', $driver->id);
    $largeSelects = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'),
    )->count();
    DB::disableQueryLog();

    expect($largeSelects)->toBe($smallSelects)
        ->and($largeSelects)->toBeLessThanOrEqual(25);
});
