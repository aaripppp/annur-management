<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverManagement;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function membershipUiYear(
    string $label = '2026/2027',
    string $start = '2026-07-01',
    string $end = '2027-06-30',
): AcademicYear {
    return AcademicYear::query()->firstOrCreate(
        ['year' => $label],
        ['is_active' => $label === '2026/2027', 'start_date' => $start, 'end_date' => $end],
    );
}

function membershipUiStudent(AcademicYear $year, string $name): Student
{
    $class = SchoolClass::factory()->create(['name' => 'Kelas '.$name, 'level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function membershipUiAssignment(
    JemputanDriver $driver,
    Student $student,
    AcademicYear $year,
    string $from = '2026-07-01',
    ?string $until = null,
): JemputanDriverAssignment {
    return JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]));
});

it('defaults new membership to the selected academic year start month', function () {
    $defaultYear = membershipUiYear();
    $otherYear = membershipUiYear('2028/2029', '2028-08-01', '2029-07-31');
    $driver = JemputanDriver::factory()->create();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openAssignmentModal')
        ->assertSet('assignmentEffectiveMonth', $defaultYear->start_date->format('Y-m'))
        ->call('closeAssignmentModal')
        ->set('selectedAcademicYearId', (string) $otherYear->id)
        ->call('openAssignmentModal')
        ->assertSet('assignmentEffectiveMonth', '2028-08');
});

it('shows active and complete history tabs with invalid periods unchanged', function () {
    $year = membershipUiYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Tab Riwayat']);
    $activeStudent = membershipUiStudent($year, 'Siswa Aktif Tab');
    $historyStudent = membershipUiStudent($year, 'Siswa Riwayat Tab');
    membershipUiAssignment($driver, $activeStudent, $year, '2026-11-01');
    membershipUiAssignment($driver, $historyStudent, $year, '2026-07-01', '2026-09-01');
    $invalid = JemputanDriverAssignment::query()->forceCreate([
        'id' => 67,
        'jemputan_driver_id' => $driver->id,
        'student_id' => $historyStudent->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-11-10',
        'effective_until' => '2026-11-10',
    ]);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertSet('groupTab', 'active')
        ->assertSee('SISWA AKTIF TAB')
        ->assertDontSee('SISWA RIWAYAT TAB')
        ->call('setGroupTab', 'history')
        ->assertSee('SISWA AKTIF TAB')
        ->assertSee('SISWA RIWAYAT TAB')
        ->assertSee('Periode Tidak Valid')
        ->assertSee('Selesai')
        ->assertSee('Aktif');

    expect($invalid->fresh()?->id)->toBe(67)
        ->and($invalid->fresh()?->effective_from->toDateString())->toBe('2026-11-10')
        ->and($invalid->fresh()?->effective_until?->toDateString())->toBe('2026-11-10');
});

it('paginates history on the server with continuous row numbers', function () {
    $year = membershipUiYear();
    $driver = JemputanDriver::factory()->create();

    foreach (range(1, 11) as $index) {
        membershipUiAssignment(
            $driver,
            membershipUiStudent($year, 'Siswa Riwayat '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)),
            $year,
            '2026-07-01',
            '2026-09-01',
        );
    }

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('setGroupTab', 'history')
        ->assertSee('SISWA RIWAYAT 11')
        ->assertDontSee('SISWA RIWAYAT 01')
        ->call('setPage', 2, 'assignmentHistoryPage')
        ->assertSee('SISWA RIWAYAT 01')
        ->assertSee('>11</td>', false);
});

it('adds stops rejoins and moves memberships on manually selected months', function () {
    $year = membershipUiYear();
    $student = membershipUiStudent($year, 'Siswa Alur Bulanan');
    $sourceDriver = JemputanDriver::factory()->create(['name' => 'Sopir Sumber UI']);
    $targetDriver = JemputanDriver::factory()->create(['name' => 'Sopir Tujuan UI']);
    $otherDriver = JemputanDriver::factory()->create(['name' => 'Sopir Multi UI']);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $sourceDriver->id)
        ->call('openAssignmentModal')
        ->call('selectStudent', $student->id)
        ->set('assignmentEffectiveMonth', '2026-08')
        ->call('assignStudent')
        ->assertHasNoErrors();

    $first = JemputanDriverAssignment::query()->where('jemputan_driver_id', $sourceDriver->id)->firstOrFail();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $sourceDriver->id)
        ->call('openCloseAssignment', $first->id)
        ->set('closeEffectiveMonth', '2026-10')
        ->call('closeAssignment')
        ->assertHasNoErrors()
        ->call('openAssignmentModal')
        ->call('selectStudent', $student->id)
        ->set('assignmentEffectiveMonth', '2026-11')
        ->call('assignStudent')
        ->assertHasNoErrors();

    $rejoined = JemputanDriverAssignment::query()->where('jemputan_driver_id', $sourceDriver->id)->currentMembership()->firstOrFail();
    membershipUiAssignment($otherDriver, $student, $year, '2026-07-01');

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $sourceDriver->id)
        ->call('openMoveAssignment', $rejoined->id)
        ->set('moveDriverId', (string) $targetDriver->id)
        ->set('moveEffectiveMonth', '2027-01')
        ->call('moveAssignment')
        ->assertHasNoErrors();

    expect($first->fresh()?->effective_from->toDateString())->toBe('2026-08-01')
        ->and($first->fresh()?->effective_until?->toDateString())->toBe('2026-10-01')
        ->and($rejoined->fresh()?->effective_until?->toDateString())->toBe('2027-01-01')
        ->and(JemputanDriverAssignment::query()->where('jemputan_driver_id', $targetDriver->id)->firstOrFail()->effective_from->toDateString())->toBe('2027-01-01')
        ->and(JemputanDriverAssignment::query()->where('jemputan_driver_id', $otherDriver->id)->currentMembership()->exists())->toBeTrue()
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(4);
});

it('edits only the selected period and rejects empty and overlapping periods', function () {
    $year = membershipUiYear();
    $student = membershipUiStudent($year, 'Siswa Edit Periode');
    $driver = JemputanDriver::factory()->create();
    $first = membershipUiAssignment($driver, $student, $year, '2026-07-01', '2026-09-01');
    $second = membershipUiAssignment($driver, $student, $year, '2026-11-01');

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('setGroupTab', 'history')
        ->call('openEditAssignmentPeriod', $second->id)
        ->set('editAssignmentEffectiveFromMonth', '2026-09')
        ->set('editAssignmentEffectiveUntilMonth', '2026-09')
        ->call('saveAssignmentPeriod')
        ->assertHasErrors('editAssignmentEffectiveUntilMonth')
        ->set('editAssignmentEffectiveUntilMonth', '')
        ->set('editAssignmentEffectiveFromMonth', '2026-08')
        ->call('saveAssignmentPeriod')
        ->assertHasErrors('assignmentPeriod')
        ->set('editAssignmentEffectiveFromMonth', '2026-10')
        ->call('saveAssignmentPeriod')
        ->assertHasNoErrors();

    expect($first->fresh()?->effective_from->toDateString())->toBe('2026-07-01')
        ->and($first->fresh()?->effective_until?->toDateString())->toBe('2026-09-01')
        ->and($second->fresh()?->effective_from->toDateString())->toBe('2026-10-01')
        ->and($second->fresh()?->effective_until)->toBeNull();
});

it('requires explicit confirmation when a period correction affects financial months', function () {
    $year = membershipUiYear();
    $student = membershipUiStudent($year, 'Siswa Dampak Historis');
    $driver = JemputanDriver::factory()->create();
    $assignment = membershipUiAssignment($driver, $student, $year);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => PaymentType::factory()->create(['name' => 'Jemputan'])->id,
        'billing_frequency' => BillFrequency::Monthly->value,
        'period_year' => 2026,
        'period_month' => 7,
    ]);
    $financialBefore = DB::table('student_bills')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openEditAssignmentPeriod', $assignment->id)
        ->set('editAssignmentEffectiveFromMonth', '2026-08')
        ->call('saveAssignmentPeriod')
        ->assertSet('editPeriodRequiresConfirmation', true)
        ->assertSet('isEditAssignmentPeriodModalOpen', true)
        ->assertDispatched('annur-toast', type: 'error');

    expect($assignment->fresh()?->effective_from->toDateString())->toBe('2026-07-01');

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openEditAssignmentPeriod', $assignment->id)
        ->set('editAssignmentEffectiveFromMonth', '2026-08')
        ->call('saveAssignmentPeriod')
        ->call('confirmAssignmentPeriodImpact')
        ->assertHasNoErrors()
        ->assertSet('isEditAssignmentPeriodModalOpen', false)
        ->assertSet('isGroupModalOpen', true);

    expect($assignment->fresh()?->effective_from->toDateString())->toBe('2026-08-01')
        ->and(DB::table('student_bills')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all())->toBe($financialBefore);
});

it('requires historical confirmation for an affected stored allocation without changing it', function () {
    $year = membershipUiYear();
    $student = membershipUiStudent($year, 'Siswa Dampak Alokasi');
    $driver = JemputanDriver::factory()->create();
    $assignment = membershipUiAssignment($driver, $student, $year);
    $allocation = JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'period_year' => 2026,
        'period_month' => 7,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => 350000,
    ]);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openEditAssignmentPeriod', $assignment->id)
        ->set('editAssignmentEffectiveFromMonth', '2026-08')
        ->call('saveAssignmentPeriod')
        ->assertSet('editPeriodRequiresConfirmation', true);

    expect($assignment->fresh()?->effective_from->toDateString())->toBe('2026-07-01')
        ->and($allocation->fresh()?->allocated_amount)->toBe('350000.00');
});

it('keeps one modal active while opening and cancelling member actions', function () {
    $year = membershipUiYear();
    $student = membershipUiStudent($year, 'Siswa Modal Tunggal');
    $driver = JemputanDriver::factory()->create();
    $assignment = membershipUiAssignment($driver, $student, $year);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openEditAssignmentPeriod', $assignment->id)
        ->assertSet('isGroupModalOpen', false)
        ->assertSet('isEditAssignmentPeriodModalOpen', true)
        ->assertSet('isCloseModalOpen', false)
        ->assertSet('isMoveModalOpen', false)
        ->call('cancelEditAssignmentPeriod')
        ->assertSet('isEditAssignmentPeriodModalOpen', false)
        ->assertSet('isGroupModalOpen', true)
        ->call('openCloseAssignment', $assignment->id)
        ->assertSet('isGroupModalOpen', false)
        ->assertSet('isCloseModalOpen', true)
        ->call('cancelCloseAssignment')
        ->assertSet('isCloseModalOpen', false)
        ->assertSet('isGroupModalOpen', true);
});

it('keeps active and history tab queries bounded as membership rows grow', function () {
    $year = membershipUiYear();
    $driver = JemputanDriver::factory()->create();
    membershipUiAssignment($driver, membershipUiStudent($year, 'Siswa Query UI 1'), $year);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $smallComponent = Livewire::test(JemputanDriverManagement::class)->call('viewGroup', $driver->id)->assertOk();
    $smallActiveSelectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::flushQueryLog();
    $smallComponent->call('setGroupTab', 'history')->assertOk();
    $smallHistorySelectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();

    foreach (range(2, 25) as $index) {
        membershipUiAssignment($driver, membershipUiStudent($year, 'Siswa Query UI '.$index), $year);
    }

    DB::flushQueryLog();
    $largeComponent = Livewire::test(JemputanDriverManagement::class)->call('viewGroup', $driver->id)->assertOk();
    $largeActiveSelectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::flushQueryLog();
    $largeComponent->call('setGroupTab', 'history')->assertOk();
    $largeHistorySelectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::disableQueryLog();

    expect($largeActiveSelectCount)->toBe($smallActiveSelectCount)
        ->and($largeHistorySelectCount)->toBe($smallHistorySelectCount)
        ->and($largeActiveSelectCount)->toBeLessThanOrEqual(25)
        ->and($largeHistorySelectCount)->toBeLessThanOrEqual(25);
});
