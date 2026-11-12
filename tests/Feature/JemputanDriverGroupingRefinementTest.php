<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverAssignmentService;
use App\Services\JemputanDriverRecapService;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function groupingRefinementYear(string $label = '2026/2027'): AcademicYear
{
    [$startYear, $endYear] = explode('/', $label);

    return AcademicYear::query()->firstOrCreate(
        ['year' => $label],
        [
            'is_active' => $label === '2026/2027',
            'start_date' => $startYear.'-07-01',
            'end_date' => $endYear.'-06-30',
        ],
    );
}

function groupingRefinementUser(string $role = User::ROLE_ADMIN): User
{
    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

function groupingRefinementStudent(AcademicYear $year, string $name, string $className = 'VIII Refinement'): Student
{
    $class = SchoolClass::query()->firstOrCreate(['name' => $className], ['level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function groupingRefinementAssignment(
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

/** @return array{StudentBill, Payment} */
function groupingRefinementPaidBill(Student $student): array
{
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 500000,
        'period_month' => 7,
        'period_year' => 2026,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);
    $payment = Payment::query()->create([
        'receipt_number' => 'REF-001',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-07-10',
        'total_amount' => 250000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => auth()->id(),
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 7,
        'period_year' => 2026,
        'amount' => 250000,
    ]);

    return [$bill, $payment];
}

/** @return array<string, list<array<int, mixed>>> */
function groupingRefinementWorkbook(TestResponse $response): array
{
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
    $path = $response->baseResponse->getFile()->getPathname();
    $reader = new Reader;
    $sheets = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        @unlink($path);
    }

    return $sheets;
}

beforeEach(function () {
    $this->actingAs(groupingRefinementUser());
});

it('adds a student through the UI without any month selection', function () {
    $year = groupingRefinementYear();
    $student = groupingRefinementStudent($year, 'Siswa Tambah Langsung');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Langsung']);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openAssignmentModal')
        ->assertDontSee('type="month"', false)
        ->call('selectStudent', $student->id)
        ->call('assignStudent')
        ->assertHasNoErrors()
        ->assertSee('SISWA TAMBAH LANGSUNG')
        ->assertDispatched('annur-toast');

    expect(JemputanDriverAssignment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $year->id)
        ->currentMembership()
        ->count())->toBe(1);
});

it('keeps the search query separate from the selected student identity', function () {
    $year = groupingRefinementYear();
    $firstStudent = groupingRefinementStudent($year, 'Kenzie Astagina', 'VIII Kenzie');
    $secondStudent = groupingRefinementStudent($year, 'Kenzie Alternatif', 'VIII Alternatif');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Picker']);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openAssignmentModal')
        ->set('studentSearch', 'kenzie as')
        ->assertSee('KENZIE ASTAGINA')
        ->call('selectStudent', $firstStudent->id)
        ->assertSet('selectedStudentId', $firstStudent->id)
        ->assertSet('studentSearch', '')
        ->assertSee('KENZIE ASTAGINA')
        ->assertSee('VIII Kenzie')
        ->assertSee('Ganti Siswa')
        ->assertDontSee('id="student-search"', false)
        ->call('changeStudent')
        ->assertSet('selectedStudentId', null)
        ->assertSet('studentSearch', '')
        ->assertSee('id="student-search"', false)
        ->set('studentSearch', 'alternatif')
        ->call('selectStudent', $secondStudent->id)
        ->call('assignStudent')
        ->assertHasNoErrors();

    expect(JemputanDriverAssignment::query()->currentMembership()->where([
        'student_id' => $secondStudent->id,
        'jemputan_driver_id' => $driver->id,
        'academic_year_id' => $year->id,
    ])->exists())->toBeTrue()
        ->and(JemputanDriverAssignment::query()->where('student_id', $firstStudent->id)->exists())->toBeFalse();
});

it('keeps a student eligible for another driver but not for the same driver', function () {
    $year = groupingRefinementYear();
    $student = groupingRefinementStudent($year, 'Siswa Multi Sopir');
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir Pertama']);
    $secondDriver = JemputanDriver::factory()->create(['name' => 'Sopir Kedua']);
    groupingRefinementAssignment($firstDriver, $student, $year);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $firstDriver->id)
        ->call('openAssignmentModal')
        ->set('studentSearch', 'Multi Sopir')
        ->assertDontSee('wire:click="selectStudent('.$student->id.')"', false);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $secondDriver->id)
        ->call('openAssignmentModal')
        ->set('studentSearch', 'Multi Sopir')
        ->assertSee('wire:click="selectStudent('.$student->id.')"', false)
        ->call('selectStudent', $student->id)
        ->call('assignStudent')
        ->assertHasNoErrors();

    $sheets = groupingRefinementWorkbook($this->get(route('jemputan.recap.export', [
        'mode' => 'all',
        'academic_year_id' => $year->id,
    ])));

    expect(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->count())->toBe(2)
        ->and(collect($sheets['SOPIR PERTAMA'])->flatten()->contains('SISWA MULTI SOPIR'))->toBeTrue()
        ->and(collect($sheets['SOPIR KEDUA'])->flatten()->contains('SISWA MULTI SOPIR'))->toBeTrue();
});

it('rejects a tampered selected student id during save', function () {
    groupingRefinementYear();
    $ineligibleStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Tidak Terdaftar']);
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Validasi Picker']);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openAssignmentModal')
        ->set('selectedStudentId', $ineligibleStudent->id)
        ->call('assignStudent')
        ->assertHasErrors('selectedStudentId')
        ->assertSee('Pilihan siswa tidak lagi valid')
        ->assertSee('Ganti Siswa');

    expect(JemputanDriverAssignment::query()->where('student_id', $ineligibleStudent->id)->exists())->toBeFalse();
});

it('removes a student from current membership recap and Excel without changing finances', function () {
    $year = groupingRefinementYear();
    $removedStudent = groupingRefinementStudent($year, 'Siswa Dikeluarkan');
    $remainingStudent = groupingRefinementStudent($year, 'Siswa Tetap', 'VIII Tetap');
    $removedDriver = JemputanDriver::factory()->create(['name' => 'Sopir Dihapus Anggota']);
    $remainingDriver = JemputanDriver::factory()->create(['name' => 'Sopir Tetap']);
    $assignment = groupingRefinementAssignment($removedDriver, $removedStudent, $year);
    groupingRefinementAssignment($remainingDriver, $remainingStudent, $year);
    [$bill, $payment] = groupingRefinementPaidBill($removedStudent);
    $billCount = StudentBill::query()->count();
    $paymentCount = Payment::query()->count();
    $detailCount = PaymentDetail::query()->count();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $removedDriver->id)
        ->assertSee('SISWA DIKELUARKAN')
        ->call('openCloseAssignment', $assignment->id)
        ->call('closeAssignment')
        ->assertHasNoErrors()
        ->assertDontSee('SISWA DIKELUARKAN')
        ->assertSee('0 siswa')
        ->assertDispatched('annur-toast');

    expect($assignment->refresh()->effective_until)->not->toBeNull()
        ->and(Student::query()->whereKey($removedStudent->id)->exists())->toBeTrue()
        ->and(StudentBill::query()->count())->toBe($billCount)
        ->and(Payment::query()->count())->toBe($paymentCount)
        ->and(PaymentDetail::query()->count())->toBe($detailCount)
        ->and($bill->refresh()->amount)->toBe('500000.00')
        ->and($payment->refresh()->status)->toBe(Payment::STATUS_ACTIVE)
        ->and(app(JemputanDriverRecapService::class)->generate($year->id, $removedDriver->id)['rows'])->toBe([]);

    $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $removedDriver->id,
    ]))->assertSessionHasErrors('driver_id');

    $sheets = groupingRefinementWorkbook($this->get(route('jemputan.recap.export', [
        'mode' => 'all',
        'academic_year_id' => $year->id,
    ])));

    expect($sheets)->not->toHaveKey('SOPIR DIHAPUS ANGGOTA')
        ->and($sheets)->toHaveKey('SOPIR TETAP')
        ->and(collect($sheets)->flatten()->all())->not->toContain('SISWA DIKELUARKAN');
});

it('moves current membership immediately from the old driver to the new driver', function () {
    $year = groupingRefinementYear();
    $student = groupingRefinementStudent($year, 'Siswa Pindah Current');
    $oldDriver = JemputanDriver::factory()->create(['name' => 'Sopir Lama Current']);
    $newDriver = JemputanDriver::factory()->create(['name' => 'Sopir Baru Current']);
    $assignment = groupingRefinementAssignment($oldDriver, $student, $year);

    $component = Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $oldDriver->id)
        ->call('openMoveAssignment', $assignment->id)
        ->set('moveDriverId', (string) $newDriver->id)
        ->call('moveAssignment')
        ->assertHasNoErrors()
        ->assertDontSee('SISWA PINDAH CURRENT')
        ->assertDispatched('annur-toast');

    $component
        ->call('closeGroup')
        ->call('viewGroup', $newDriver->id)
        ->assertSee('SISWA PINDAH CURRENT');

    expect($assignment->refresh()->effective_until)->not->toBeNull()
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->value('jemputan_driver_id'))->toBe($newDriver->id)
        ->and(app(JemputanDriverRecapService::class)->generate($year->id, $oldDriver->id)['rows'])->toBe([])
        ->and(app(JemputanDriverRecapService::class)->generate($year->id, $newDriver->id)['rows'][0]['student_id'])->toBe($student->id);

    $sheets = groupingRefinementWorkbook($this->get(route('jemputan.recap.export', [
        'mode' => 'all',
        'academic_year_id' => $year->id,
    ])));

    expect($sheets)->not->toHaveKey('SOPIR LAMA CURRENT')
        ->and($sheets)->toHaveKey('SOPIR BARU CURRENT');
});

it('supports immediate same-month re-addition and academic-year isolation', function () {
    $firstYear = groupingRefinementYear();
    $nextYear = groupingRefinementYear('2027/2028');
    $student = groupingRefinementStudent($firstYear, 'Siswa Readd');
    $nextClass = SchoolClass::query()->create(['name' => 'IX Rollover', 'level' => 9]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $nextYear->id,
        'school_class_id' => $nextClass->id,
        'status' => 'active',
    ]);
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir Readd']);
    $otherDriver = JemputanDriver::factory()->create(['name' => 'Sopir Rollover']);
    $service = app(JemputanDriverAssignmentService::class);
    $first = $service->assign($firstDriver->id, $student->id, $firstYear->id);
    $closed = $service->remove($first->id);
    $readded = $service->assign($firstDriver->id, $student->id, $firstYear->id);
    $nextYearAssignment = $service->assign($otherDriver->id, $student->id, $nextYear->id);
    $nextYearSameDriver = $service->assign($firstDriver->id, $student->id, $nextYear->id);

    expect(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->where('academic_year_id', $firstYear->id)->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->currentMembership()->where('student_id', $student->id)->where('academic_year_id', $nextYear->id)->count())->toBe(2)
        ->and($closed->effective_until?->toDateString())->toBe($readded->effective_from->toDateString())
        ->and($readded->effective_until)->toBeNull()
        ->and($nextYearSameDriver->effective_until)->toBeNull()
        ->and(app(JemputanDriverRecapService::class)->generate($firstYear->id, $firstDriver->id)['rows'][0]['student_id'])->toBe($student->id)
        ->and(app(JemputanDriverRecapService::class)->generate($nextYear->id, $otherDriver->id)['rows'][0]['student_id'])->toBe($student->id)
        ->and(fn () => $service->assign($firstDriver->id, $student->id, $firstYear->id))
        ->toThrow(ValidationException::class);

    $sheets = groupingRefinementWorkbook($this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $firstYear->id,
        'driver_id' => $firstDriver->id,
    ])));

    expect(collect($sheets)->flatten()->filter(fn ($value): bool => $value === 'SISWA READD')->count())->toBe(1);

    $service->remove($nextYearAssignment->id);

    expect(app(JemputanDriverRecapService::class)->generate($nextYear->id, $otherDriver->id)['rows'])->toBe([]);
});

it('renders accessible icon actions and full-viewport teleported confirmation modals', function () {
    $year = groupingRefinementYear();
    $student = groupingRefinementStudent($year, 'Siswa Ikon');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Ikon']);
    $assignment = groupingRefinementAssignment($driver, $student, $year);

    $component = Livewire::test(JemputanDriverManagement::class)
        ->assertSee('title="Lihat Grup"', false)
        ->assertSee('aria-label="Edit Sopir Sopir Ikon"', false)
        ->assertSee('aria-label="Nonaktifkan Sopir Sopir Ikon"', false)
        ->assertSee('aria-label="Hapus Sopir Sopir Ikon"', false)
        ->call('openCreateDriver')
        ->assertSee('Tambah Sopir')
        ->assertSee('fixed inset-0 z-[9999] overflow-y-auto', false)
        ->call('closeDriverModal')
        ->call('viewGroup', $driver->id)
        ->assertSee('title="Keluarkan Siswa"', false)
        ->assertSee('aria-label="Pindah Sopir untuk SISWA IKON"', false)
        ->call('openAssignmentModal')
        ->assertSee('Tambah Siswa ke Sopir Ikon')
        ->assertDontSee('Mulai Bulan')
        ->assertDontSee('Akhir Periode')
        ->assertDontSee('type="month"', false)
        ->assertSee('fixed inset-0 z-[9999] overflow-y-auto', false)
        ->call('closeAssignmentModal')
        ->call('openMoveAssignment', $assignment->id)
        ->assertSee('Pindah Sopir')
        ->assertSee('fixed inset-0 z-[9999] overflow-y-auto', false)
        ->call('cancelMoveAssignment')
        ->call('openCloseAssignment', $assignment->id)
        ->assertSee('Keluarkan Siswa')
        ->assertSee('x-teleport="body"', false)
        ->assertSee('fixed inset-0 z-[9999] overflow-y-auto', false)
        ->assertSee('flex min-h-full items-center justify-center', false);

    $component
        ->call('cancelCloseAssignment')
        ->call('openDriverConfirmation', $driver->id, 'toggle')
        ->assertSee('Ubah Status Sopir')
        ->assertSee('fixed inset-0 z-[9999]', false);
});

it('reuses the shared timed toast for success and validation errors', function () {
    groupingRefinementYear();

    Livewire::test(JemputanDriverManagement::class)
        ->call('openCreateDriver')
        ->set('driverName', 'Sopir Toast')
        ->call('saveDriver')
        ->assertDispatched('annur-toast', message: 'Sopir Jemputan berhasil ditambahkan.', type: 'success');

    Livewire::test(JemputanDriverManagement::class)
        ->call('openCreateDriver')
        ->set('driverName', '')
        ->call('saveDriver')
        ->assertHasErrors('driverName')
        ->assertDispatched('annur-toast', type: 'error');

    $this->get(route('jemputan.drivers.index'))
        ->assertSee('x-on:annur-toast.window="notify($event.detail)"', false)
        ->assertSee("this.type === 'error' ? 5000 : 3000", false)
        ->assertSee('data-flash-toast', false);
});
