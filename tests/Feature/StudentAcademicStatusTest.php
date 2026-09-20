<?php

use App\Enums\BillFrequency;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use Livewire\Livewire;

beforeEach(function () {
    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $this->futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $this->futureYear2 = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30']
    );
});

function acadCreateActiveStudent(int $level = 7): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::where('year', '2026/2027')->first()->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function acadCreateFutureStudent(int $level = 7): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::where('year', '2027/2028')->first()->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function acadCreateGraduatedStudent(int $level = 12): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id, 'status' => 'lulus']);

    // Active enrollment in a past year
    $pastYear = AcademicYear::firstOrCreate(
        ['year' => '2025/2026'],
        ['is_active' => false, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $pastYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    // Lulus enrollment in target year
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::where('year', '2026/2027')->first()->id,
        'school_class_id' => $class->id,
        'status' => 'lulus',
    ]);

    return $student;
}

function acadPayBill(StudentBill $bill, int $amount): void
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-STATUS-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-15',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);
}

/*
|--------------------------------------------------------------------------
| STATUS DERIVATION TESTS
|--------------------------------------------------------------------------
*/

it('derives Aktif for student with active enrollment in active year', function () {
    $student = acadCreateActiveStudent(7);
    expect($student->academicStatus())->toBe('aktif')
        ->and($student->academicStatusLabel)->toBe('Aktif');
});

it('derives Calon Siswa for student with only future year enrollment', function () {
    $student = acadCreateFutureStudent(7);
    expect($student->academicStatus())->toBe('calon_siswa')
        ->and($student->academicStatusLabel)->toBe('Calon Siswa');
});

it('derives Lulus for graduated student', function () {
    $student = acadCreateGraduatedStudent(12);
    expect($student->academicStatus())->toBe('lulus')
        ->and($student->academicStatusLabel)->toBe('Lulus');
});

it('future continuation takes precedence over historical graduation', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = Student::factory()->create(['class_id' => $class->id, 'status' => 'lulus']);

    // Lulus enrollment
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class->id,
        'status' => 'lulus',
    ]);

    // A new future enrollment represents the student's current context.
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    expect($student->academicStatus())->toBe('calon_siswa');
});

it('returns Aktif for legacy student without enrollments', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    // No enrollments at all
    expect($student->academicStatus())->toBe('aktif');
});

it('returns correct academicClassLabel for active student', function () {
    $student = acadCreateActiveStudent(7);
    expect($student->academicClassLabel())->not->toBe('-');
});

it('returns correct academicClassLabel for future student using entry class', function () {
    $student = acadCreateFutureStudent(7);
    expect($student->academicClassLabel())->not->toBe('-');
});

it('returns correct entryYearLabel for future student', function () {
    $student = acadCreateFutureStudent(7);
    expect($student->entryYearLabel())->toBe('2027/2028');
});

it('returns null entryYearLabel for active student', function () {
    $student = acadCreateActiveStudent(7);
    expect($student->entryYearLabel())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| ONE-TIME BILL VISIBILITY BOUNDED BY ENROLLMENT PERIOD (Tests 1-10)
|--------------------------------------------------------------------------
*/

it('UP originating 2026/2027 displays in 2026/2027', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Uang Pangkal')
        ->assertSee('Tahun Ajaran 2026/2027');
});

it('UP originating 2026/2027 does NOT display in 2027/2028 after the enrollment period', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('Uang Pangkal');
});

it('UP originating 2026/2027 does NOT display in 2028/2029 after the enrollment period', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029')
        ->assertDontSee('Uang Pangkal');
});

it('same StudentBill ID is displayed only in eligible views within the enrollment period', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    $bill = makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('wire:key="bill-'.$bill->id.'"', false);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('wire:key="bill-'.$bill->id.'"', false);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029')
        ->assertDontSee('wire:key="bill-'.$bill->id.'"', false);
});

it('UP originating 2027/2028 does NOT display in 2026/2027', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2027/2028');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertDontSee('Uang Pangkal');
});

it('UP originating 2027/2028 does NOT display in 2027/2028 after the enrollment period', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2027/2028');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('Uang Pangkal');
});

it('UP originating 2027/2028 does NOT display in later academic years', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2027/2028');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029')
        ->assertDontSee('Uang Pangkal');
});

it('partial payment state remains visible in the eligible enrollment year and hidden afterwards', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    $bill = makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');
    acadPayBill($bill, 2000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Rp 2.000.000')
        ->assertSee('Rp 3.000.000');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('Uang Pangkal');
});

it('fully paid UP remains visible in the eligible enrollment year and hidden afterwards', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    $bill = makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');
    acadPayBill($bill, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Lunas');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('Uang Pangkal');
});

it('viewing/filtering never creates duplicate StudentBill records', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    // View multiple academic years
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027');
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028');
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029');

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();
    expect($count)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| FUTURE STUDENT TESTS (Tests 11-17)
|--------------------------------------------------------------------------
*/

it('future student 2027/2028 is not treated as active in 2026/2027', function () {
    $student = acadCreateFutureStudent(7);
    expect($student->academicStatus())->not->toBe('aktif');
});

it('future student is classified as Calon Siswa', function () {
    $student = acadCreateFutureStudent(7);
    expect($student->academicStatus())->toBe('calon_siswa');
});

it('future student can have 2027/2028 billbook', function () {
    $student = acadCreateFutureStudent(7);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lengkapi Tagihan');
});

it('future student can display a manually created 2026/2027 bill when that year is selected', function () {
    $student = acadCreateFutureStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);

    // Create monthly bill for 2026/2027 period (shouldn't exist for future student)
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('SPP');
});

it('future student remains searchable in PaymentCreate', function () {
    $student = acadCreateFutureStudent(7);

    $searchResults = Student::where('nama_lengkap', 'like', '%'.$student->nama_lengkap.'%')
        ->orWhere('nis', 'like', '%'.$student->nis.'%')
        ->take(5)
        ->get();

    expect($searchResults->isNotEmpty())->toBeTrue();
});

it('early payment updates future student 2027/2028 bill correctly', function () {
    $student = acadCreateFutureStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, $this->futureYear->start_date);

    $bill = $student->bills()
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->academic_year)->toBe('2027/2028');

    acadPayBill($bill, 1000000);
    $bill->refresh();

    expect((float) $bill->paid_amount)->toBe(1000000.0)
        ->and((float) $bill->remaining_amount)->toBe(4000000.0);
});

it('future student remains excluded from 2026/2027 promotion', function () {
    $class7 = SchoolClass::factory()->create(['level' => 7]);
    $student = Student::factory()->create(['class_id' => $class7->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $service = app(ClassPromotionService::class);
    $preview = $service->getPreviewData($this->activeYear, $this->futureYear);

    $studentIds = $preview['students']->pluck('id')->toArray();
    expect($studentIds)->not->toContain($student->id);
});

/*
|--------------------------------------------------------------------------
| STATUS FILTER TESTS (Tests 18-27)
|--------------------------------------------------------------------------
*/

it('current-year enrolled student shows as Aktif in list', function () {
    $student = acadCreateActiveStudent(7);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'aktif')
        ->assertSee($student->nama_lengkap)
        ->assertSee('Aktif');
});

it('future-year-only student shows as Calon Siswa in list', function () {
    $student = acadCreateFutureStudent(7);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'calon_siswa')
        ->assertSee($student->nama_lengkap)
        ->assertSee('Calon Siswa');
});

it('graduated student shows as Lulus in list', function () {
    $student = acadCreateGraduatedStudent(12);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'lulus')
        ->assertSee($student->nama_lengkap)
        ->assertSee('Lulus');
});

it('graduated XII student retains XII as last class', function () {
    $student = acadCreateGraduatedStudent(12);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'lulus')
        ->assertSee($student->nama_lengkap);
});

it('status filter Aktif works', function () {
    $activeStudent = acadCreateActiveStudent(7);
    $futureStudent = acadCreateFutureStudent(7);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'aktif')
        ->assertSee($activeStudent->nama_lengkap)
        ->assertDontSee($futureStudent->nama_lengkap);
});

it('status filter Calon Siswa works', function () {
    $activeStudent = acadCreateActiveStudent(7);
    $futureStudent = acadCreateFutureStudent(7);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'calon_siswa')
        ->assertDontSee($activeStudent->nama_lengkap)
        ->assertSee($futureStudent->nama_lengkap);
});

it('status filter Lulus works', function () {
    $activeStudent = acadCreateActiveStudent(7);
    $graduatedStudent = acadCreateGraduatedStudent(12);

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'lulus')
        ->assertDontSee($activeStudent->nama_lengkap)
        ->assertSee($graduatedStudent->nama_lengkap);
});

it('search + status filter work together', function () {
    $activeStudent = acadCreateActiveStudent(7);
    $futureStudent = acadCreateFutureStudent(7);

    Livewire::test(StudentManagement::class)
        ->set('search', $futureStudent->nama_lengkap)
        ->set('filterStatus', 'calon_siswa')
        ->assertSee($futureStudent->nama_lengkap)
        ->assertDontSee($activeStudent->nama_lengkap);
});

it('pagination resets when status changes', function () {
    Livewire::test(StudentManagement::class)
        ->set('filterStatus', 'aktif')
        ->assertSet('filterStatus', 'aktif')
        ->set('filterStatus', 'calon_siswa')
        ->assertSet('filterStatus', 'calon_siswa');
});

/*
|--------------------------------------------------------------------------
| PERIOD FILTER TESTS (Tests 28-31)
|--------------------------------------------------------------------------
*/

it('2026/2027 only exposes periods within its start/end dates', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Agustus 2026')
        ->assertDontSee('Agustus 2027');
});

it('2027/2028 periods do not leak into 2026/2027', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2027);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertDontSee('Agustus 2027');
});

it('changing academic year resets summaryCategory', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSet('summaryCategory', 'all')
        ->set('summaryCategory', 'monthly')
        ->set('selectedAcademicYear', '2027/2028')
        ->assertSet('summaryCategory', 'all');
});

it('2027/2028 menampilkan pilihan Ringkasan tetap tanpa opsi periode', function () {
    $student = acadCreateActiveStudent(7);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertDontSee('Agustus 2026')
        ->assertDontSee('Juli 2027')
        ->assertSee('Semua Tagihan')
        ->assertSee('Tagihan Bulanan')
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Tagihan Sekali Bayar');
});

/*
|--------------------------------------------------------------------------
| SUMMARY TESTS (Tests 32-37)
|--------------------------------------------------------------------------
*/

it('Total Tagihan matches visible/filter scope', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 975000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 1.950.000</p>');
});

it('Total Dibayar matches visible/filter scope', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    $bill = makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);
    acadPayBill($bill, 500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 500.000</p>');
});

it('Total Tunggakan = Total Tagihan - Total Dibayar', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    $bill = makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);
    acadPayBill($bill, 500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 975.000</p>')  // Tagihan
        ->assertSeeHtml('tracking-wider">Rp 500.000</p>')  // Dibayar
        ->assertSeeHtml('tracking-wider">Rp 475.000</p>'); // Tunggakan
});

it('lifetime one-time bill is counted once in summary', function () {
    $student = acadCreateActiveStudent(7);
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    // Summary in 2026/2027 should count it once
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 5.000.000</p>');

    // Summary in 2027/2028 (outside the enrollment period) should no longer count it
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->set('summaryCategory', 'all')
        ->assertDontSeeHtml('tracking-wider">Rp 5.000.000</p>')
        ->assertSeeHtml('tracking-wider">Rp 0</p>');
});

it('academic-year change recalculates totals correctly', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);

    // 2026/2027: has bill
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 975.000</p>');

    // 2027/2028: no bills
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 0</p>');
});

it('kategori berubah menghitung ulang totals dengan benar', function () {
    $student = acadCreateActiveStudent(7);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 975000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->set('summaryCategory', 'monthly')
        ->assertSeeHtml('tracking-wider">Rp 1.950.000</p>')
        ->set('summaryCategory', 'yearly')
        ->assertSeeHtml('tracking-wider">Rp 0</p>')
        ->set('summaryCategory', 'all')
        ->assertSeeHtml('tracking-wider">Rp 1.950.000</p>');
});

/*
|--------------------------------------------------------------------------
| STUDENT DETAIL STATUS DISPLAY (UI)
|--------------------------------------------------------------------------
*/

it('student detail shows Aktif badge for active student', function () {
    $student = acadCreateActiveStudent(7);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Aktif');
});

it('student detail shows Calon Siswa badge for future student', function () {
    $student = acadCreateFutureStudent(7);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Calon Siswa')
        ->assertSee('Rencana Masuk');
});

it('student detail shows Lulus badge for graduated student', function () {
    $student = acadCreateGraduatedStudent(12);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lulus')
        ->assertSee('Kelas Terakhir');
});
