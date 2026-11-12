<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverSemesterRecap;
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
use Livewire\Livewire;

function semesterRecapUiUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function semesterRecapUiYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function semesterRecapUiStudent(AcademicYear $year, string $name = 'Siswa Rekap'): Student
{
    $class = SchoolClass::factory()->create(['name' => 'VIII Rekap', 'level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function semesterRecapUiAssign(JemputanDriver $driver, Student $student, AcademicYear $year): void
{
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-07-01',
        'effective_until' => null,
    ]);
}

beforeEach(function () {
    $this->actingAs(semesterRecapUiUser());
});

it('shows the six-month recap navigation to administrators and hides it from TU Unit', function () {
    semesterRecapUiYear();

    $this->get(route('jemputan.semester-recap.index'))
        ->assertOk()
        ->assertSee('Rekap 6 Bulan')
        ->assertSee(route('jemputan.semester-recap.index'), false)
        ->assertSee('bg-secondary-container text-on-secondary-container font-bold', false);

    auth()->logout();
    $this->actingAs(semesterRecapUiUser(User::ROLE_TU_UNIT))
        ->get(route('siswa.index'))
        ->assertDontSee('Rekap 6 Bulan')
        ->assertDontSee(route('jemputan.semester-recap.index'), false);
});

it('renders filters three driver rows recap rows and semester totals', function () {
    $year = semesterRecapUiYear();
    $student = semesterRecapUiStudent($year);
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Semester']);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    semesterRecapUiAssign($driver, $student, $year);
    JemputanDriverDeductionRate::query()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => 2026,
        'effective_month' => 7,
        'percentage' => 10,
    ]);
    JemputanDriverDeductionRate::query()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => 2026,
        'effective_month' => 8,
        'percentage' => 7,
    ]);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 100000,
        'period_year' => 2026,
        'period_month' => 7,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 100000,
        'period_year' => 2026,
        'period_month' => 8,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);

    Livewire::test(JemputanDriverSemesterRecap::class)
        ->assertSet('selectedAcademicYearId', (string) $year->id)
        ->assertSet('selectedSemester', 1)
        ->assertSee('Tahun Ajaran')
        ->assertSee('Jenjang / Unit')
        ->assertSee('Semester 1 · Juli–Desember')
        ->assertSee('Nama Sopir')
        ->assertSee('Pembayaran')
        ->assertSee('Sopir Semester')
        ->assertSee('Fee Yayasan')
        ->assertSee('REKAP')
        ->assertSee('Rp 90.000')
        ->assertSee('Rp 10.000')
        ->assertSee('(10%)')
        ->assertSee('Rp 7.000')
        ->assertSee('(7%)')
        ->assertSee('Rp 100.000')
        ->assertSee('Gaji sopir dihitung berdasarkan seluruh tagihan Jemputan, termasuk siswa yang belum membayar.')
        ->assertSee('Tagihan yang belum dibayar ditanggung sementara oleh sekolah dan tetap masuk perhitungan gaji.')
        ->assertSee('data-fee-cell class="whitespace-nowrap', false)
        ->assertSee('inline-flex items-baseline justify-end gap-1 whitespace-nowrap', false)
        ->assertSee('data-semester-recap-table', false)
        ->assertDontSee('sticky', false)
        ->assertSee('FINAL')
        ->set('selectedSemester', 2)
        ->assertSee('Januari 2027')
        ->assertSee('Juni 2027');
});

it('shows allocation rate and historical membership warnings', function () {
    $year = semesterRecapUiYear();
    $student = semesterRecapUiStudent($year, 'Siswa Warning');
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir Tanpa Tarif']);
    $secondDriver = JemputanDriver::factory()->create([
        'name' => 'Sopir Legacy',
        'foundation_deduction_percentage' => 10,
    ]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    semesterRecapUiAssign($firstDriver, $student, $year);
    semesterRecapUiAssign($secondDriver, $student, $year);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 100000,
        'period_year' => 2026,
        'period_month' => 7,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);

    Livewire::test(JemputanDriverSemesterRecap::class)
        ->assertSee('BELUM FINAL')
        ->assertSee('Alokasi Multi-Sopir belum final.')
        ->assertSee('1 missing')
        ->assertSee('Persentase potongan yayasan belum diatur.')
        ->assertSee('Belum diatur')
        ->assertSee('Sebagian persentase masih memakai data legacy.')
        ->assertSee('Catatan membership historis:')
        ->assertSee('potensi double count tidak tersembunyi');
});

it('shows a clear empty state', function () {
    semesterRecapUiYear();

    Livewire::test(JemputanDriverSemesterRecap::class)
        ->assertSee('Belum ada data sopir pada filter ini.');
});

it('allows active administrators and super administrators to open the page', function (string $role) {
    semesterRecapUiYear();
    auth()->logout();

    $this->actingAs(semesterRecapUiUser($role))
        ->get(route('jemputan.semester-recap.index'))
        ->assertOk();
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies guests TU Unit inactive users and unauthorized Livewire access', function () {
    semesterRecapUiYear();
    auth()->logout();
    $this->get(route('jemputan.semester-recap.index'))->assertRedirect(route('login'));

    $this->actingAs(semesterRecapUiUser(User::ROLE_TU_UNIT))
        ->get(route('jemputan.semester-recap.index'))
        ->assertRedirect(route('tu.pending'));

    $this->actingAs(semesterRecapUiUser(User::ROLE_ADMIN, false))
        ->get(route('jemputan.semester-recap.index'))
        ->assertRedirect(route('login'));

    auth()->logout();
    Livewire::actingAs(semesterRecapUiUser(User::ROLE_TU_UNIT));
    Livewire::test(JemputanDriverSemesterRecap::class)->assertForbidden();
});
