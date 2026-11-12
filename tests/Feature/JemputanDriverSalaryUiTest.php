<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverSalary;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverAllocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function jemputanSalaryUiUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanSalaryUiYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function jemputanSalaryUiStudent(AcademicYear $year, string $name, string $className = 'VIII A', int $level = 8): array
{
    $class = SchoolClass::query()->firstOrCreate(['name' => $className], ['level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return [$student, $class];
}

function jemputanSalaryUiAssign(
    JemputanDriver $driver,
    Student $student,
    AcademicYear $year,
    string $from = '2026-07-01',
    ?string $until = null,
): void {
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

function jemputanSalaryUiBill(Student $student, PaymentType $type, int $month, float $amount, int $year = 2026): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => $amount,
    ]);
}

function jemputanSalaryUiAllocation(
    Student $student,
    AcademicYear $year,
    JemputanDriver $driver,
    int $month,
    float $amount,
    int $periodYear = 2026,
): JemputanDriverSalaryAllocation {
    return JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'period_year' => $periodYear,
        'period_month' => $month,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

function jemputanSalaryUiPayment(Student $student, StudentBill $bill, float $amount): void
{
    $payment = Payment::query()->create([
        'receipt_number' => 'SAL-UI-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-07-10',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => auth()->id(),
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => $amount,
    ]);
}

beforeEach(function () {
    $this->actingAs(jemputanSalaryUiUser());
});

it('adds authorized salary navigation and keeps the menu active', function () {
    $response = $this->get(route('jemputan.salary.index'));

    $response->assertOk()
        ->assertSee('Kelompok Sopir')
        ->assertSee('Rekap Jemputan')
        ->assertSee('Gaji Sopir')
        ->assertSee(route('jemputan.drivers.index'), false)
        ->assertSee(route('jemputan.recap.index'), false)
        ->assertSee(route('jemputan.salary.index'), false)
        ->assertSee('bg-secondary-container text-on-secondary-container font-bold', false);
});

it('hides salary navigation from TU Unit', function () {
    auth()->logout();

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_TU_UNIT))
        ->get(route('siswa.index'))
        ->assertDontSee('Gaji Sopir')
        ->assertDontSee(route('jemputan.salary.index'), false);
});

it('defaults to the active year and waits for a driver selection', function () {
    $year = jemputanSalaryUiYear();

    Livewire::test(JemputanDriverSalary::class)
        ->assertSet('selectedAcademicYearId', (string) $year->id)
        ->assertSet('selectedDriverId', '')
        ->assertSee('Pilih sopir untuk menghitung gaji.');
});

it('renders the revenue foundation deduction and net salary', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Gaji');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Gaji', 'foundation_deduction_percentage' => 15]);
    jemputanSalaryUiAssign($driver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Total Pendapatan')
        ->assertSee('Potongan Yayasan')
        ->assertSee('Total Diterima Sopir')
        ->assertSee('Rp 500.000')
        ->assertSee('Rp 75.000')
        ->assertSee('Rp 425.000')
        ->assertSee('15% dari pendapatan')
        ->assertSee('Gaji sopir dihitung berdasarkan seluruh tagihan Jemputan, termasuk siswa yang belum membayar.')
        ->assertSee('Tagihan yang belum dibayar ditanggung sementara oleh sekolah dan tetap masuk perhitungan gaji.')
        ->assertSee('SISWA GAJI')
        ->assertSee('Sopir Tunggal')
        ->assertSee('Export Excel')
        ->assertSee('wire:click="exportExcel"', false)
        ->assertDontSee('Export Excel tersedia setelah Batch 4 selesai');
});

it('shows all payment status badges without changing billed salary amounts', function () {
    $year = jemputanSalaryUiYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    [$noBillStudent] = jemputanSalaryUiStudent($year, 'Siswa Belum Ada Tagihan');
    [$unpaidStudent] = jemputanSalaryUiStudent($year, 'Siswa Belum Lunas');
    [$partialStudent] = jemputanSalaryUiStudent($year, 'Siswa Sebagian');
    [$paidStudent] = jemputanSalaryUiStudent($year, 'Siswa Lunas');

    foreach ([$noBillStudent, $unpaidStudent, $partialStudent, $paidStudent] as $student) {
        jemputanSalaryUiAssign($driver, $student, $year);
    }

    jemputanSalaryUiBill($unpaidStudent, $type, 7, 500000);
    $partialBill = jemputanSalaryUiBill($partialStudent, $type, 7, 500000);
    $paidBill = jemputanSalaryUiBill($paidStudent, $type, 7, 500000);
    jemputanSalaryUiPayment($partialStudent, $partialBill, 150000);
    jemputanSalaryUiPayment($paidStudent, $paidBill, 500000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Belum Ada Tagihan')
        ->assertSee('Belum Lunas')
        ->assertSee('Sebagian')
        ->assertSee('Lunas')
        ->assertSee('Rp 1.500.000')
        ->assertSee('Status Tagihan')
        ->assertSee('data-column="bill-amount"', false)
        ->assertSee('data-column="payment-status"', false)
        ->assertSee('data-column="description"', false)
        ->assertSee('inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap', false)
        ->assertSee('bg-surface-container-high text-on-surface-variant', false)
        ->assertSee('bg-error-container text-on-error-container', false)
        ->assertSee('bg-surface-container-highest text-on-surface-variant', false)
        ->assertSee('bg-tertiary-fixed text-on-tertiary-fixed', false)
        ->assertDontSee('bg-primary-container text-on-primary-container', false);
});

it('shows a non-final state when the driver percentage is null', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Tanpa Persentase');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => null]);
    jemputanSalaryUiAssign($driver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Persentase potongan yayasan belum diatur.')
        ->assertSee('Belum diatur')
        ->assertSee('Belum final')
        ->assertSee('Buka Master Sopir')
        ->assertSee(route('jemputan.drivers.index'), false);
});

it('shows multi-driver students under each driver with a simulation warning', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Multi');
    $drivers = JemputanDriver::factory()->count(2)->create();
    jemputanSalaryUiAssign($drivers[0], $student, $year);
    jemputanSalaryUiAssign($drivers[1], $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    foreach ($drivers as $driver) {
        Livewire::test(JemputanDriverSalary::class)
            ->set('selectedDriverId', (string) $driver->id)
            ->assertSee('SISWA MULTI')
            ->assertSee('Multi-Sopir')
            ->assertSee('2 sopir aktif')
            ->assertSee('Terdapat 1 siswa Multi-Sopir.')
            ->assertSee('simulasi')
            ->assertSee('Rp 500.000')
            ->assertDontSee('Rp 250.000');
    }
});

it('keeps summary totals independent from the active page', function () {
    $year = jemputanSalaryUiYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);

    foreach (range(1, 11) as $index) {
        [$student] = jemputanSalaryUiStudent($year, 'Siswa Halaman '.$index, 'Halaman '.$index);
        jemputanSalaryUiAssign($driver, $student, $year);
        jemputanSalaryUiBill($student, $type, 7, 100000);
    }

    Livewire::test(JemputanDriverSalary::class)
        ->set('perPage', 10)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Rp 1.100.000')
        ->assertSee('Rp 110.000')
        ->assertSee('Rp 990.000')
        ->assertSee('Halaman 1 dari 2')
        ->call('nextPage', 'salaryPage')
        ->assertSee('Rp 1.100.000')
        ->assertSee('Rp 990.000')
        ->assertSee('Halaman 2 dari 2');
});

it('filters revenue by the selected month', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Bulan');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 15]);
    jemputanSalaryUiAssign($driver, $student, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    jemputanSalaryUiBill($student, $type, 7, 100000);
    jemputanSalaryUiBill($student, $type, 1, 200000, 2027);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Rp 100.000')
        ->set('selectedMonth', '2027-01')
        ->assertSee('Rp 200.000')
        ->assertSet('selectedMonth', '2027-01');
});

it('rejects invalid driver month and page size filters', function () {
    jemputanSalaryUiYear();

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', '999999')
        ->assertHasErrors('selectedDriverId')
        ->set('selectedMonth', '2026-13')
        ->assertHasErrors('selectedMonth')
        ->set('perPage', 11)
        ->assertHasErrors('perPage');
});

it('allows active administrators to open the salary page', function (string $role) {
    auth()->logout();

    $this->actingAs(jemputanSalaryUiUser($role))
        ->get(route('jemputan.salary.index'))
        ->assertOk();
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies salary page access to guests TU Unit and inactive users', function () {
    auth()->logout();
    $this->get(route('jemputan.salary.index'))->assertRedirect(route('login'));

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_TU_UNIT))
        ->get(route('jemputan.salary.index'))
        ->assertRedirect(route('tu.pending'));

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_ADMIN, false))
        ->get(route('jemputan.salary.index'))
        ->assertRedirect(route('login'));
});

it('authorizes the Livewire component itself', function () {
    auth()->logout();
    Livewire::actingAs(jemputanSalaryUiUser(User::ROLE_TU_UNIT));

    Livewire::test(JemputanDriverSalary::class)->assertForbidden();
});

it('authorizes allocation open and save separately from button visibility', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Otorisasi Alokasi', 'VIII Otorisasi Alokasi');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);
    $component = Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id);

    $admin = auth()->user();

    $service = app(JemputanDriverAllocationService::class);

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_TU_UNIT));
    expect(fn () => $component->instance()->openAllocationModal($student->id, $service))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_ADMIN, false));
    expect(fn () => $component->instance()->openAllocationModal($student->id, $service))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($admin);
    $component
        ->call('openAllocationModal', $student->id)
        ->assertSet('isAllocationModalOpen', true)
        ->set('allocationAmounts.'.$firstDriver->id, '450000')
        ->set('allocationAmounts.'.$secondDriver->id, '250000');

    $this->actingAs(jemputanSalaryUiUser(User::ROLE_TU_UNIT));
    expect(fn () => $component->instance()->saveAllocation($service))
        ->toThrow(AuthorizationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('shows a needs attention status for stale allocations', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Alokasi Basi', 'VIII Alokasi Basi');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);
    jemputanSalaryUiAllocation($student, $year, $firstDriver, 7, 350000);
    jemputanSalaryUiAllocation($student, $year, $secondDriver, 7, 250000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->assertSee('Multi-Sopir — Perlu Diperbaiki')
        ->assertSee('Angka belum final')
        ->assertDontSee('Multi-Sopir — Alokasi Valid');
});

it('performs no financial writes while filtering and rendering', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Read Only');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 15]);
    jemputanSalaryUiAssign($driver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);
    $component = Livewire::test(JemputanDriverSalary::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component->set('selectedDriverId', (string) $driver->id)->assertOk();
    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1
    );
    DB::disableQueryLog();

    expect($writes)->toBeEmpty();
});

it('keeps SELECT queries bounded as the student count grows', function () {
    $year = jemputanSalaryUiYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $selectCounts = [];

    foreach ([1, 10, 25] as $studentCount) {
        $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 15]);

        foreach (range(1, $studentCount) as $index) {
            [$student] = jemputanSalaryUiStudent($year, 'Siswa Query '.$studentCount.'-'.$index, 'Query '.$studentCount.'-'.$index);
            jemputanSalaryUiAssign($driver, $student, $year);
            jemputanSalaryUiBill($student, $type, 7, 100000);
        }

        $component = Livewire::test(JemputanDriverSalary::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->set('selectedDriverId', (string) $driver->id)->assertOk();
        $selectCounts[] = collect(DB::getQueryLog())->pluck('query')->filter(
            fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
        )->count();
        DB::disableQueryLog();
    }

    expect(array_unique($selectCounts))->toHaveCount(1);
});

it('shows Atur Pembagian only for multi-driver students', function () {
    $year = jemputanSalaryUiYear();
    [$singleStudent] = jemputanSalaryUiStudent($year, 'Siswa Tunggal Tombol', 'VIII Tunggal Tombol');
    [$multiStudent] = jemputanSalaryUiStudent($year, 'Siswa Multi Tombol', 'VIII Multi Tombol');
    [$multiWithoutBill] = jemputanSalaryUiStudent($year, 'Siswa Multi Tanpa Tagihan', 'VIII Multi Tanpa Tagihan');
    $singleDriver = JemputanDriver::factory()->create();
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($singleDriver, $singleStudent, $year);
    jemputanSalaryUiAssign($firstDriver, $multiStudent, $year);
    jemputanSalaryUiAssign($secondDriver, $multiStudent, $year);
    jemputanSalaryUiAssign($firstDriver, $multiWithoutBill, $year);
    jemputanSalaryUiAssign($secondDriver, $multiWithoutBill, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    jemputanSalaryUiBill($singleStudent, $type, 7, 300000);
    jemputanSalaryUiBill($multiStudent, $type, 7, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $singleDriver->id)
        ->assertSee('Sopir Tunggal')
        ->assertDontSee('Atur Pembagian')
        ->assertDontSee('Multi-Sopir — Belum Dialokasikan');

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->assertSee('Multi-Sopir — Belum Dialokasikan')
        ->assertSee('Atur Pembagian')
        ->assertSee('Belum Ada Tagihan')
        ->assertSee('wire:click="openAllocationModal('.$multiStudent->id.')"', false)
        ->assertDontSee('wire:click="openAllocationModal('.$multiWithoutBill->id.')"', false)
        ->assertSee('Angka belum final');
});

it('renders a compact multi-driver row without bill using only the Multi-Sopir badge', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Fatih', 'VIII Fatih');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    PaymentType::factory()->create(['name' => 'Jemputan']);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->assertSee('FATIH')
        ->assertSee('Belum Ada Tagihan')
        ->assertSee('Multi-Sopir')
        ->assertSee('flex items-center gap-2 whitespace-nowrap', false)
        ->assertSee('whitespace-nowrap px-4 py-3 align-middle', false)
        ->assertDontSee('2 sopir aktif')
        ->assertDontSee('Pembagian belum diperlukan karena belum ada tagihan.')
        ->assertDontSee('Atur Pembagian');
});

it('opens and closes the allocation modal with fresh report data', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Modal Alokasi', 'VIII Modal Alokasi');
    $student->update(['nis' => 'NIS-MODAL-01']);
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir Modal A']);
    $secondDriver = JemputanDriver::factory()->create(['name' => 'Sopir Modal B']);
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);
    jemputanSalaryUiAllocation($student, $year, $firstDriver, 7, 450000);
    jemputanSalaryUiAllocation($student, $year, $secondDriver, 7, 250000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->assertSet('isAllocationModalOpen', true)
        ->assertSet('allocationStudentId', $student->id)
        ->assertSet('allocationStudentNis', 'NIS-MODAL-01')
        ->assertSet('allocationStudentClass', 'VIII Modal Alokasi')
        ->assertSet('allocationMonthKey', '2026-07')
        ->assertSet('allocationTotal', 700000.0)
        ->assertSet('allocationDifference', 0.0)
        ->assertSet('allocationAmounts.'.$firstDriver->id, '450000.00')
        ->assertSet('allocationAmounts.'.$secondDriver->id, '250000.00')
        ->assertSee('SISWA MODAL ALOKASI')
        ->assertSee('NIS-MODAL-01')
        ->assertSee('Sopir Modal A')
        ->assertSee('Sopir Modal B')
        ->assertSee('Pembagian ini hanya untuk perhitungan gaji sopir. Nominal tagihan siswa tidak berubah.')
        ->call('closeAllocationModal')
        ->assertSet('isAllocationModalOpen', false)
        ->assertSet('allocationStudentId', null)
        ->assertSet('allocationAmounts', []);
});

it('recalculates allocation total and difference while typing', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Hitung Alokasi', 'VIII Hitung Alokasi');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);
    $component = Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->assertSet('allocationTotal', 0.0)
        ->assertSet('allocationDifference', 700000.0);

    $component->set('allocationAmounts.'.$firstDriver->id, '400000')
        ->assertSet('allocationTotal', 400000.0)
        ->assertSet('allocationDifference', 300000.0)
        ->set('allocationAmounts.'.$secondDriver->id, '250000')
        ->assertSet('allocationTotal', 650000.0)
        ->assertSet('allocationDifference', 50000.0)
        ->set('allocationAmounts.'.$secondDriver->id, '300000')
        ->assertSet('allocationTotal', 700000.0)
        ->assertSet('allocationDifference', 0.0);
});

it('rejects allocation totals that do not match the bill and keeps the modal open', function (string $firstAmount, string $secondAmount) {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Total Salah', 'VIII Total Salah');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, $firstAmount)
        ->set('allocationAmounts.'.$secondDriver->id, $secondAmount)
        ->call('saveAllocation')
        ->assertHasErrors('allocationForm')
        ->assertSet('isAllocationModalOpen', true)
        ->assertSet('allocationAmounts.'.$firstDriver->id, $firstAmount)
        ->assertSet('allocationAmounts.'.$secondDriver->id, $secondAmount)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
})->with([
    'kurang' => ['450000', '249999'],
    'lebih' => ['450000', '250001'],
]);

it('rejects negative and over precision allocation amounts inline', function (string $amount) {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Nominal Salah', 'VIII Nominal Salah');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, $amount)
        ->set('allocationAmounts.'.$secondDriver->id, '700000')
        ->call('saveAllocation')
        ->assertHasErrors('allocationAmounts.'.$firstDriver->id)
        ->assertSet('isAllocationModalOpen', true)
        ->assertSet('allocationAmounts.'.$firstDriver->id, $amount)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
})->with([
    'negatif' => ['-1'],
    'tiga desimal' => ['450000.001'],
]);

it('saves a valid split closes the modal and refreshes the salary summary', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Simpan Alokasi', 'VIII Simpan Alokasi');
    $firstDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    $bill = jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, '450000')
        ->set('allocationAmounts.'.$secondDriver->id, '250000')
        ->call('saveAllocation')
        ->assertHasNoErrors()
        ->assertSet('isAllocationModalOpen', false)
        ->assertSet('allocationAmounts', [])
        ->assertDispatched('annur-toast', message: 'Pembagian nominal berhasil disimpan.', type: 'success')
        ->assertSee('Multi-Sopir — Alokasi Valid')
        ->assertSee('Gaji sopir:')
        ->assertSee('Rp 450.000')
        ->assertSee('Rp 45.000')
        ->assertSee('Rp 405.000')
        ->assertDontSee('Angka belum final');

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(2)
        ->and($bill->refresh()->amount)->toBe('700000.00')
        ->and(PaymentType::query()->where('name', 'Jemputan')->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->count())->toBe(2);
});

it('saves the same allocation to other months with the same bill amount', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Multi Bulan Sama', 'VIII Multi Bulan Sama');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    jemputanSalaryUiBill($student, $type, 7, 700000);
    jemputanSalaryUiBill($student, $type, 8, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, '450000')
        ->set('allocationAmounts.'.$secondDriver->id, '250000')
        ->set('applyAllocationToOtherMonths', true)
        ->set('additionalAllocationMonthKeys', ['2026-08'])
        ->call('saveAllocation')
        ->assertHasNoErrors()
        ->assertSet('isAllocationModalOpen', false)
        ->assertDispatched('annur-toast', message: 'Pembagian nominal berhasil disimpan.', type: 'success');

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(4)
        ->and(JemputanDriverSalaryAllocation::query()->where('period_month', 7)->count())->toBe(2)
        ->and(JemputanDriverSalaryAllocation::query()->where('period_month', 8)->count())->toBe(2);
});

it('rejects copying an allocation to a month with a different bill safely', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Multi Bulan Beda', 'VIII Multi Bulan Beda');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    jemputanSalaryUiBill($student, $type, 7, 700000);
    jemputanSalaryUiBill($student, $type, 8, 800000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, '450000')
        ->set('allocationAmounts.'.$secondDriver->id, '250000')
        ->set('applyAllocationToOtherMonths', true)
        ->set('additionalAllocationMonthKeys', ['2026-08'])
        ->call('saveAllocation')
        ->assertHasErrors('allocationMonths')
        ->assertSet('isAllocationModalOpen', true)
        ->assertSet('allocationAmounts.'.$firstDriver->id, '450000')
        ->assertSet('allocationAmounts.'.$secondDriver->id, '250000')
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('keeps financial data unchanged when saving an allocation', function () {
    $year = jemputanSalaryUiYear();
    [$student] = jemputanSalaryUiStudent($year, 'Siswa Finansial Alokasi', 'VIII Finansial Alokasi');
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    jemputanSalaryUiAssign($firstDriver, $student, $year);
    jemputanSalaryUiAssign($secondDriver, $student, $year);
    $bill = jemputanSalaryUiBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $firstDriver->id)
        ->call('openAllocationModal', $student->id)
        ->set('allocationAmounts.'.$firstDriver->id, '450000')
        ->set('allocationAmounts.'.$secondDriver->id, '250000')
        ->call('saveAllocation')
        ->assertSet('isAllocationModalOpen', false);

    expect($bill->refresh()->amount)->toBe('700000.00')
        ->and(StudentBill::query()->count())->toBe(1)
        ->and(JemputanDriverAssignment::query()->count())->toBe(2)
        ->and(JemputanDriverSalaryAllocation::query()->count())->toBe(2);
});
