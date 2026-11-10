<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverRecap;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function jemputanRecapUiUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanRecapUiYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function jemputanRecapUiStudent(AcademicYear $year, string $name, string $className = 'VIII A', int $level = 8): array
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

function jemputanRecapUiAssign(JemputanDriver $driver, Student $student, AcademicYear $year, string $from = '2026-07-01', ?string $until = null): void
{
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

function jemputanRecapUiBill(Student $student, PaymentType $type, int $month, float $amount): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_month' => $month,
        'period_year' => 2026,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => $amount,
    ]);
}

function jemputanRecapUiPayment(Student $student, StudentBill $bill, Bank $bank, string $date, float $amount): Payment
{
    $payment = Payment::query()->create([
        'receipt_number' => 'UI-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
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

    return $payment;
}

beforeEach(function () {
    $this->actingAs(jemputanRecapUiUser());
});

it('adds authorized navigation and keeps the dedicated recap menu active', function () {
    $response = $this->get(route('jemputan.recap.index'));

    $response->assertOk()
        ->assertSee('Kelompok Sopir')
        ->assertSee('Rekap Jemputan')
        ->assertSee(route('jemputan.drivers.index'), false)
        ->assertSee(route('jemputan.recap.index'), false)
        ->assertSee('bg-secondary-container text-on-secondary-container font-bold', false);
});

it('hides recap navigation from TU Unit', function () {
    auth()->logout();

    $this->actingAs(jemputanRecapUiUser(User::ROLE_TU_UNIT))
        ->get(route('siswa.index'))
        ->assertDontSee('Rekap Jemputan')
        ->assertDontSee(route('jemputan.recap.index'), false);
});

it('defaults to the active year and waits for a driver selection', function () {
    $year = jemputanRecapUiYear();

    Livewire::test(JemputanDriverRecap::class)
        ->assertSet('selectedAcademicYearId', (string) $year->id)
        ->assertSet('selectedDriverId', '')
        ->assertSee('Pilih sopir untuk menampilkan rekap.');
});

it('filters by historical year level and class and rejects invalid combinations', function () {
    $year = jemputanRecapUiYear();
    [$student, $class] = jemputanRecapUiStudent($year, 'Siswa Filter');
    $otherClass = SchoolClass::query()->create(['name' => 'XI Tidak Sesuai', 'level' => 11]);
    $driver = JemputanDriver::factory()->create();
    jemputanRecapUiAssign($driver, $student, $year);

    $component = Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->set('selectedSchoolLevel', 'SMP')
        ->assertSee('VIII A')
        ->set('selectedSchoolClassId', (string) $class->id)
        ->assertHasNoErrors()
        ->assertSee('SISWA FILTER');

    $component
        ->set('selectedSchoolClassId', (string) $otherClass->id)
        ->assertHasErrors('selectedSchoolClassId')
        ->set('selectedDriverId', '999999')
        ->assertHasErrors('selectedDriverId');
});

it('renders July through June with three subcolumns and single-line sticky cells', function () {
    $year = jemputanRecapUiYear();
    [$student] = jemputanRecapUiStudent($year, 'Nama Siswa Sangat Panjang Untuk Tetap Satu Baris');
    $driver = JemputanDriver::factory()->create();
    jemputanRecapUiAssign($driver, $student, $year);

    Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSeeInOrder(['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])
        ->assertSee('Nominal')
        ->assertSee('Tanggal')
        ->assertSee('Bank')
        ->assertSee('rowspan="2"', false)
        ->assertSee('colspan="3"', false)
        ->assertSee('sticky left-0 z-40', false)
        ->assertSee('sticky left-14 z-40', false)
        ->assertSee('sticky top-16 z-30', false)
        ->assertSee('whitespace-nowrap', false)
        ->assertSee('NAMA SISWA SANGAT PANJANG UNTUK TETAP SATU BARIS');
});

it('renders aggregated partial payment with latest date and bank and persisted tariff details', function () {
    $year = jemputanRecapUiYear();
    [$student] = jemputanRecapUiStudent($year, 'Siswa Bayar');
    $driver = JemputanDriver::factory()->create();
    jemputanRecapUiAssign($driver, $student, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $july = jemputanRecapUiBill($student, $type, 7, 500000);
    $august = jemputanRecapUiBill($student, $type, 8, 650000);
    $september = jemputanRecapUiBill($student, $type, 9, 650000);
    $firstBank = Bank::factory()->create(['name' => 'Bank Lama']);
    $latestBank = Bank::factory()->create(['name' => 'Bank Terbaru', 'account_number' => '1234567890']);
    jemputanRecapUiPayment($student, $july, $firstBank, '2026-07-10', 100000);
    jemputanRecapUiPayment($student, $july, $latestBank, '2026-07-20', 150000);
    jemputanRecapUiPayment($student, $september, $latestBank, '2026-09-20', 650000);

    Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('Rp 250.000')
        ->assertSee('20/07/2026')
        ->assertSee('Bank Terbaru · 1234567890')
        ->assertSee('Sebagian', false)
        ->assertSee('Bervariasi')
        ->assertSee('Tagihan efektif Rp 500.000', false)
        ->assertSee('Lunas', false)
        ->assertSee('Belum Dibayar', false)
        ->assertSee('Halaman Ini');
});

it('does not render the unassigned Jemputan bill banner', function () {
    $year = jemputanRecapUiYear();
    [$assignedStudent] = jemputanRecapUiStudent($year, 'Siswa Dengan Sopir');
    [$unassignedStudent] = jemputanRecapUiStudent($year, 'Siswa Tanpa Sopir');
    $driver = JemputanDriver::factory()->create();
    jemputanRecapUiAssign($driver, $assignedStudent, $year);
    jemputanRecapUiBill($unassignedStudent, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('SISWA DENGAN SOPIR')
        ->assertDontSee('belum memiliki penugasan sopir untuk bulan tagihannya');
});

it('shows a moved student only under the current driver', function () {
    $year = jemputanRecapUiYear();
    [$student] = jemputanRecapUiStudent($year, 'Siswa Pindah Sopir');
    $oldDriver = JemputanDriver::factory()->create(['name' => 'Sopir Lama']);
    $newDriver = JemputanDriver::factory()->create(['name' => 'Sopir Baru']);
    jemputanRecapUiAssign($oldDriver, $student, $year, '2026-07-01', '2026-09-30');
    jemputanRecapUiAssign($newDriver, $student, $year, '2026-10-01');
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $september = jemputanRecapUiBill($student, $type, 9, 500000);
    $october = jemputanRecapUiBill($student, $type, 10, 600000);
    $bank = Bank::factory()->create();
    jemputanRecapUiPayment($student, $september, $bank, '2026-10-01', 500000);
    jemputanRecapUiPayment($student, $october, $bank, '2026-10-02', 600000);

    Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $oldDriver->id)
        ->assertDontSee('SISWA PINDAH SOPIR');

    Livewire::test(JemputanDriverRecap::class)
        ->set('selectedDriverId', (string) $newDriver->id)
        ->assertSee('SISWA PINDAH SOPIR')
        ->assertSee('Tagihan efektif Rp 500.000', false)
        ->assertSee('Tagihan efektif Rp 600.000', false)
        ->assertDontSee('Tidak Ditugaskan', false);
});

it('paginates supported page sizes and labels summaries as page scoped', function () {
    $year = jemputanRecapUiYear();
    $driver = JemputanDriver::factory()->create();

    foreach (range(1, 11) as $index) {
        [$student] = jemputanRecapUiStudent($year, 'Siswa Halaman '.$index, 'Kelas '.$index);
        jemputanRecapUiAssign($driver, $student, $year);
    }

    Livewire::test(JemputanDriverRecap::class)
        ->set('perPage', 10)
        ->set('selectedDriverId', (string) $driver->id)
        ->assertSee('10 siswa')
        ->assertSee('Halaman Ini')
        ->assertSee('Halaman 1 dari 2')
        ->call('nextPage', 'recapPage')
        ->assertSee('1 siswa')
        ->assertSee('Halaman 2 dari 2')
        ->set('perPage', 11)
        ->assertHasErrors('perPage');
});

it('allows active administrators and denies guests TU Unit and inactive users', function (string $role) {
    auth()->logout();

    $this->actingAs(jemputanRecapUiUser($role))
        ->get(route('jemputan.recap.index'))
        ->assertOk();
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies unauthorized recap access', function () {
    auth()->logout();
    $this->get(route('jemputan.recap.index'))->assertRedirect(route('login'));

    $this->actingAs(jemputanRecapUiUser(User::ROLE_TU_UNIT))
        ->get(route('jemputan.recap.index'))
        ->assertRedirect(route('tu.pending'));

    $this->actingAs(jemputanRecapUiUser(User::ROLE_ADMIN, false))
        ->get(route('jemputan.recap.index'))
        ->assertRedirect(route('login'));
});

it('performs no financial writes while filtering and rendering', function () {
    $year = jemputanRecapUiYear();
    [$student] = jemputanRecapUiStudent($year, 'Siswa Read Only');
    $driver = JemputanDriver::factory()->create();
    jemputanRecapUiAssign($driver, $student, $year);
    $component = Livewire::test(JemputanDriverRecap::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component->set('selectedDriverId', (string) $driver->id)->assertOk();
    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1
    );
    DB::disableQueryLog();

    expect($writes)->toBeEmpty();
});

it('keeps full-page SELECT queries bounded as row counts grow', function (int $studentCount) {
    $year = jemputanRecapUiYear();
    $driver = JemputanDriver::factory()->create();

    foreach (range(1, $studentCount) as $index) {
        [$student] = jemputanRecapUiStudent($year, 'Siswa Query '.$index, 'Query '.$index);
        jemputanRecapUiAssign($driver, $student, $year);
    }

    $component = Livewire::test(JemputanDriverRecap::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $component->set('selectedDriverId', (string) $driver->id)->assertOk();
    $selectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::disableQueryLog();

    expect($selectCount)->toBe(11);
})->with([1, 10, 25, 50]);
