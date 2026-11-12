<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverManagement;
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
use App\Services\JemputanDriverSalarySpreadsheet;
use App\Services\JemputanDriverSemesterRecapService;
use App\Services\JemputanDriverSemesterRecapSpreadsheet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function rateEditUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function rateEditDriver(string $name = 'Pak Ahmad'): JemputanDriver
{
    return JemputanDriver::factory()->create(['name' => $name, 'foundation_deduction_percentage' => null]);
}

function rateEditRate(
    JemputanDriver $driver,
    float $percentage = 12,
    int $year = 2026,
    int $month = 11,
    ?string $notes = 'Tarif awal',
): JemputanDriverDeductionRate {
    return JemputanDriverDeductionRate::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'percentage' => $percentage,
        'effective_year' => $year,
        'effective_month' => $month,
        'notes' => $notes,
    ]);
}

function rateEditComponent(JemputanDriver $driver, JemputanDriverDeductionRate $rate): mixed
{
    return Livewire::test(JemputanDriverManagement::class)
        ->set('selectedDriverId', $driver->id)
        ->call('openEditDeductionRateModal', $rate->id);
}

function rateEditYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function rateEditStudentBill(AcademicYear $year, JemputanDriver $driver, int $month): void
{
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-07-01',
        'effective_until' => null,
    ]);
    $type = PaymentType::query()->firstOrCreate(['name' => 'Jemputan']);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_year' => 2026,
        'period_month' => $month,
        'billing_frequency' => BillFrequency::Monthly->value,
        'academic_year' => null,
        'amount' => 100000,
    ]);
}

/** @return list<array<int, mixed>> */
function rateEditSpreadsheetRows(string $path): array
{
    $reader = new Reader;

    try {
        $reader->open($path);
        $sheet = $reader->getSheetIterator()->current();

        return collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->values()
            ->all();
    } finally {
        $reader->close();
    }
}

beforeEach(function () {
    $this->actingAs(rateEditUser());
});

it('opens a compact prefilled edit modal from each history row', function () {
    $driver = rateEditDriver();
    $rate = rateEditRate($driver);

    rateEditComponent($driver, $rate)
        ->assertSet('isEditDeductionRateModalOpen', true)
        ->assertSet('editingDeductionRateId', $rate->id)
        ->assertSet('editDeductionRatePercentage', '12')
        ->assertSet('editDeductionRateEffectiveMonth', '2026-11')
        ->assertSet('editDeductionRateNotes', 'Tarif awal')
        ->assertSee('Pak Ahmad')
        ->assertSee('id="edit-deduction-rate-effective-month" type="month"', false)
        ->assertDontSee('id="edit-deduction-rate-year"', false)
        ->assertSee('wire:click="openEditDeductionRateModal('.$rate->id.')"', false)
        ->assertDontSee('SEBELUM')
        ->assertDontSee('Alasan Koreksi')
        ->assertDontSee('Saya telah memeriksa');
});

it('keeps the existing add percentage month input unchanged', function () {
    $driver = rateEditDriver();

    Livewire::test(JemputanDriverManagement::class)
        ->set('selectedDriverId', $driver->id)
        ->call('openDeductionRateModal')
        ->assertSee('id="deduction-rate-effective-month" type="month" wire:model.live="deductionRateEffectiveMonth"', false);
});

it('updates percentage month year and notes on the same record', function () {
    $driver = rateEditDriver();
    $rate = rateEditRate($driver);
    $rateCount = JemputanDriverDeductionRate::query()->count();

    rateEditComponent($driver, $rate)
        ->set('editDeductionRatePercentage', '15.25')
        ->set('editDeductionRateEffectiveMonth', '2027-12')
        ->set('editDeductionRateNotes', '  Catatan diperbarui  ')
        ->call('saveDeductionRateCorrection')
        ->assertHasNoErrors()
        ->assertSet('isEditDeductionRateModalOpen', false)
        ->assertDispatched('annur-toast', message: 'Riwayat persentase berhasil dikoreksi.', type: 'success');

    $rate->refresh();

    expect($rate->id)->toBe($rate->getKey())
        ->and($rate->percentage)->toBe('15.25')
        ->and($rate->effective_month)->toBe(12)
        ->and($rate->effective_year)->toBe(2027)
        ->and($rate->notes)->toBe('Catatan diperbarui')
        ->and(JemputanDriverDeductionRate::query()->count())->toBe($rateCount);
});

it('rejects invalid edit values while keeping the modal and input open', function (string $field, mixed $value) {
    $driver = rateEditDriver();
    $rate = rateEditRate($driver);

    rateEditComponent($driver, $rate)
        ->set($field, $value)
        ->call('saveDeductionRateCorrection')
        ->assertHasErrors($field)
        ->assertSet($field, $value)
        ->assertSet('isEditDeductionRateModalOpen', true);
})->with([
    'negative percentage' => ['editDeductionRatePercentage', '-1'],
    'percentage above maximum' => ['editDeductionRatePercentage', '100.01'],
    'percentage with three decimals' => ['editDeductionRatePercentage', '12.345'],
    'invalid month' => ['editDeductionRateEffectiveMonth', '2026-13'],
    'invalid year' => ['editDeductionRateEffectiveMonth', '0099-11'],
    'notes too long' => ['editDeductionRateNotes', str_repeat('a', 501)],
]);

it('rejects a duplicate target period without changing either rate', function () {
    $driver = rateEditDriver();
    $july = rateEditRate($driver, 7, 2026, 7, 'Juli');
    $november = rateEditRate($driver, 12, 2026, 11, 'November');

    rateEditComponent($driver, $november)
        ->set('editDeductionRateEffectiveMonth', '2026-07')
        ->call('saveDeductionRateCorrection')
        ->assertHasErrors('editDeductionRateEffectiveMonth')
        ->assertSet('isEditDeductionRateModalOpen', true);

    expect($july->fresh()->percentage)->toBe('7.00')
        ->and($november->fresh()->effective_month)->toBe(11)
        ->and(JemputanDriverDeductionRate::query()->count())->toBe(2);
});

it('rejects stale modal data without overwriting another administrator change', function () {
    $driver = rateEditDriver();
    $rate = rateEditRate($driver);
    $component = rateEditComponent($driver, $rate)
        ->set('editDeductionRatePercentage', '15');

    $rate->update(['percentage' => 14, 'notes' => 'Diubah admin lain']);

    $component->call('saveDeductionRateCorrection')
        ->assertHasErrors('editDeductionRate')
        ->assertSet('editDeductionRatePercentage', '15')
        ->assertSet('isEditDeductionRateModalOpen', true);

    expect($rate->fresh()->percentage)->toBe('14.00')
        ->and($rate->fresh()->notes)->toBe('Diubah admin lain');
});

it('requires the edited rate to belong to the selected driver', function () {
    $selectedDriver = rateEditDriver('Sopir Dipilih');
    $otherDriver = rateEditDriver('Sopir Lain');
    $otherRate = rateEditRate($otherDriver);

    $component = Livewire::test(JemputanDriverManagement::class)
        ->set('selectedDriverId', $selectedDriver->id);

    expect(fn () => $component->call('openEditDeductionRateModal', $otherRate->id))
        ->toThrow(ModelNotFoundException::class);
});

it('moves November to December and keeps resolver report and spreadsheets consistent', function () {
    $year = rateEditYear();
    $driver = rateEditDriver('Sopir Konsisten');
    rateEditRate($driver, 7, 2026, 7, 'Juli');
    $november = rateEditRate($driver, 12, 2026, 11, 'Dipindah');
    rateEditStudentBill($year, $driver, 11);
    rateEditStudentBill($year, $driver, 12);

    rateEditComponent($driver, $november)
        ->set('editDeductionRateEffectiveMonth', '2026-12')
        ->call('saveDeductionRateCorrection')
        ->assertHasNoErrors();

    $resolver = app(JemputanDriverDeductionRateResolver::class);
    $salaryService = app(JemputanDriverSalaryService::class);
    $novemberSalary = $salaryService->generate($year->id, $driver->id, '2026-11');
    $decemberExport = $salaryService->exportData($year->id, $driver->id, '2026-12');
    $semesterReport = app(JemputanDriverSemesterRecapService::class)->generate($year->id, null, 1);
    $semesterRow = collect($semesterReport['rows'])->firstWhere('driver_id', $driver->id);

    expect($resolver->resolve($driver, 2026, 11)['percentage'])->toBe(7.0)
        ->and($resolver->resolve($driver, 2026, 12)['percentage'])->toBe(12.0)
        ->and($novemberSalary['summary']['percentage'])->toBe(7.0)
        ->and($novemberSalary['summary']['deduction'])->toBe(7000)
        ->and($decemberExport['summary']['percentage'])->toBe(12.0)
        ->and($decemberExport['summary']['deduction'])->toBe(12000)
        ->and($semesterRow['months']['2026-11']['foundation_fee'])->toBe(7000)
        ->and($semesterRow['months']['2026-12']['foundation_fee'])->toBe(12000);

    $salaryPath = app(JemputanDriverSalarySpreadsheet::class)->create($decemberExport);
    $semesterPath = app(JemputanDriverSemesterRecapSpreadsheet::class)->create($semesterReport);

    try {
        $salaryText = collect(rateEditSpreadsheetRows($salaryPath))->flatten()->filter(fn ($value): bool => is_string($value))->implode(' ');
        $semesterRows = rateEditSpreadsheetRows($semesterPath);
        $driverStart = collect($semesterRows)->search(fn (array $row): bool => ($row[1] ?? null) === 'Sopir Konsisten');

        expect($salaryText)->toContain('Potongan Yayasan (12%)')
            ->and($driverStart)->not->toBeFalse()
            ->and($semesterRows[$driverStart + 1][7])->toBe(7000)
            ->and($semesterRows[$driverStart + 1][8])->toBe(12000);
    } finally {
        @unlink($salaryPath);
        @unlink($semesterPath);
    }
});

it('allows active administrators and super administrators to edit', function (string $role) {
    auth()->logout();
    $this->actingAs(rateEditUser($role));
    $driver = rateEditDriver();
    $rate = rateEditRate($driver);

    rateEditComponent($driver, $rate)
        ->set('editDeductionRatePercentage', '13')
        ->call('saveDeductionRateCorrection')
        ->assertHasNoErrors();

    expect($rate->fresh()->percentage)->toBe('13.00');
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('blocks unauthorized and inactive users from edit actions', function (string $role, bool $active) {
    auth()->logout();
    $this->actingAs(rateEditUser($role, $active));

    Livewire::test(JemputanDriverManagement::class)->assertForbidden();
})->with([
    'TU Unit' => [User::ROLE_TU_UNIT, true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);

it('keeps history rendering queries bounded as rate rows grow', function () {
    $driver = rateEditDriver();
    rateEditRate($driver, 7, 2025, 1);

    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::test(JemputanDriverManagement::class)->set('selectedDriverId', $driver->id);
    $smallSelects = collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'),
    )->count();

    foreach (range(2, 24) as $offset) {
        rateEditRate($driver, 7, 2025 + intdiv($offset, 12), (($offset - 1) % 12) + 1);
    }

    DB::flushQueryLog();
    Livewire::test(JemputanDriverManagement::class)->set('selectedDriverId', $driver->id);
    $largeSelects = collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'),
    )->count();
    DB::disableQueryLog();

    expect($largeSelects)->toBe($smallSelects);
});
