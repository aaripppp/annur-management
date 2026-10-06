<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentClassPaymentRecapService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** @return array{0: AcademicYear, 1: SchoolClass, 2: Student} */
function makeClassRecapStudent(
    string $year = '2026/2027',
    int $level = 7,
    string $className = 'VII A',
    string $studentName = 'Ahmad Historis',
    bool $activeYear = true,
): array {
    $academicYear = AcademicYear::query()->updateOrCreate(['year' => $year], [
        'is_active' => $activeYear,
        'start_date' => substr($year, 0, 4).'-07-01',
        'end_date' => substr($year, 5, 4).'-06-30',
    ]);
    $schoolClass = SchoolClass::query()->create(['name' => $className, 'level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $studentName, 'class_id' => $schoolClass->id]);
    enrollClassRecapStudent($student, $academicYear, $schoolClass);

    return [$academicYear, $schoolClass, $student];
}

function enrollClassRecapStudent(Student $student, AcademicYear $academicYear, SchoolClass $schoolClass, string $status = 'active'): StudentAcademicEnrollment
{
    return StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => $status,
    ]);
}

function configureClassRecapType(
    PaymentType $type,
    SchoolLevel $level,
    BillFrequency|string $frequency = BillFrequency::Monthly,
    ?int $classLevel = null,
): void {
    makeLevelDefault($type, $level);
    makeBillRate($type, $classLevel ?? 7, 1, ['billing_frequency' => $frequency]);
}

function createClassRecapAllocation(StudentBill $bill, float $amount, string $status = Payment::STATUS_ACTIVE, string $paymentDate = '2026-10-10'): Payment
{
    $bank = Bank::query()->firstOrCreate(['name' => 'Bank Rekap Kelas'], [
        'type' => Bank::TYPE_BANK,
        'account_number' => 'REKAP-KELAS',
        'account_name' => 'Yayasan Sekolah',
        'is_active' => true,
    ]);
    $user = User::query()->first() ?? User::factory()->create();
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-KELAS-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);

    return $payment;
}

/**
 * Payment type one_time ber-audience prospective (Formulir Pendaftaran) yang
 * sudah punya rate one_time pada level kelas rekap, sehingga muncul sebagai
 * kolom one_time di rekap per kelas.
 */
function makeClassRecapFormulirType(
    SchoolLevel $level,
    int $classLevel,
    string $name = 'Formulir Pendaftaran',
): PaymentType {
    $type = makeBillType($name);
    $type->update(['audience' => PaymentTypeAudience::ProspectiveStudent]);
    configureClassRecapType($type, $level, BillFrequency::OneTime, $classLevel);

    return $type;
}

/**
 * Calon siswa yang sudah dikonversi menjadi Student.
 *
 * Link dibuat langsung lewat converted_student_id karena rekap per kelas hanya
 * membaca link tersebut; proses konversi itu sendiri diuji terpisah.
 */
function makeClassRecapConvertedProspect(
    Student $student,
    AcademicYear $academicYear,
    SchoolClass $schoolClass,
    string $registrationNumber,
    string $name,
    bool $converted = true,
): ProspectiveStudent {
    return ProspectiveStudent::factory()->create([
        'registration_number' => $registrationNumber,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'nama_lengkap' => $name,
        'jenis_kelamin' => 'P',
        'status' => $converted ? ProspectiveStudentStatus::Converted : ProspectiveStudentStatus::Registered,
        'converted_student_id' => $converted ? $student->id : null,
        'converted_at' => $converted ? now() : null,
    ]);
}

function makeClassRecapProspectiveBill(
    ProspectiveStudent $prospect,
    PaymentType $type,
    float $amount,
    string $academicYear = '2026/2027',
): ProspectiveStudentBill {
    return ProspectiveStudentBill::query()->create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => $academicYear,
        'due_date' => null,
    ]);
}

function payClassRecapProspectiveBill(
    ProspectiveStudentBill $bill,
    float $amount,
    string $status = ProspectiveStudentPayment::STATUS_ACTIVE,
): ProspectiveStudentPayment {
    $bank = Bank::query()->firstOrCreate(['name' => 'Bank Formulir Rekap'], [
        'type' => Bank::TYPE_BANK,
        'account_number' => 'REKAP-FORMULIR',
        'account_name' => 'Yayasan Sekolah',
        'is_active' => true,
    ]);

    $payment = ProspectiveStudentPayment::query()->create([
        'receipt_number' => 'KWT-FORMULIR-'.uniqid(),
        'prospective_student_id' => $bill->prospective_student_id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-10',
        'total_amount' => $amount,
        'status' => $status,
    ]);

    ProspectiveStudentPaymentDetail::query()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => $amount,
    ]);

    return $payment;
}

/** @return array{target: float, paid: float, remaining: float} */
function classRecapFormulirBalance(array $report, string $typeName = 'Formulir Pendaftaran'): array
{
    $typeId = collect($report['payment_types']['one_time'])
        ->firstWhere('name', $typeName)['id'] ?? null;

    expect($typeId)->not->toBeNull();

    return $report['rows'][0]['one_time'][$typeId];
}

it('adds Rekap Per Kelas to existing report navigation with contextual Excel actions', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();

    Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->assertSet('classRecapAcademicYearId', (string) $academicYear->id)
        ->assertSee('Rekap Per Kelas')
        ->assertSee('Tahun Ajaran')
        ->assertSee('Jenjang')
        ->assertSee('Kelas')
        ->assertSee('Pilih jenjang dan kelas untuk menampilkan rekap.')
        ->assertDontSee('Download Excel Unit')
        ->assertDontSee('Download Excel Kelas')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->assertSee('Download Excel Unit')
        ->assertDontSee('Download Excel Kelas')
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->assertSee('Download Excel Kelas')
        ->assertDontSee('Cetak PDF')
        ->assertDontSeeHtml('setTargetMode');
});

it('filters class options by jenjang and clears an invalid selected class when jenjang changes', function () {
    AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $smpClass = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $sdClass = SchoolClass::query()->create(['name' => 'VI B', 'level' => 6]);
    $kbClass = SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);

    Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->assertSee('VII A')
        ->assertDontSee('VI B')
        ->set('classRecapSchoolClassId', (string) $smpClass->id)
        ->assertSet('classRecapSchoolClassId', (string) $smpClass->id)
        ->set('classRecapSchoolLevel', SchoolLevel::SD->value)
        ->assertSet('classRecapSchoolClassId', '')
        ->assertSee('VI B')
        ->assertDontSee('VII A')
        ->set('classRecapSchoolClassId', (string) $smpClass->id)
        ->assertSet('classRecapSchoolClassId', '')
        ->set('classRecapSchoolLevel', SchoolLevel::TK->value)
        ->assertSee('KB')
        ->set('classRecapSchoolClassId', (string) $kbClass->id)
        ->assertSet('classRecapSchoolClassId', (string) $kbClass->id);
});

it('uses historical enrollment for each academic year and class after promotion', function () {
    $yearSeven = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => false,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $yearEight = AcademicYear::query()->updateOrCreate(['year' => '2027/2028'], [
        'is_active' => true,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $classSeven = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $classEight = SchoolClass::query()->create(['name' => 'VIII A', 'level' => 8]);
    $otherClass = SchoolClass::query()->create(['name' => 'VII B', 'level' => 7]);
    $student = Student::factory()->create(['nama_lengkap' => 'Ahmad Naik Kelas', 'class_id' => $classEight->id]);
    $outsider = Student::factory()->create(['nama_lengkap' => 'Siswa Kelas Lain', 'class_id' => $classSeven->id]);
    enrollClassRecapStudent($student, $yearSeven, $classSeven);
    enrollClassRecapStudent($student, $yearEight, $classEight);
    enrollClassRecapStudent($outsider, $yearSeven, $otherClass);

    $service = app(StudentClassPaymentRecapService::class);
    $sevenReport = $service->generate($yearSeven->id, SchoolLevel::SMP, $classSeven->id);
    $eightReport = $service->generate($yearEight->id, SchoolLevel::SMP, $classEight->id);

    expect(collect($sevenReport['rows'])->pluck('student_name')->all())->toBe(['Ahmad Naik Kelas'])
        ->and(collect($eightReport['rows'])->pluck('student_name')->all())->toBe(['Ahmad Naik Kelas'])
        ->and($sevenReport['school_class'])->toBe('VII A')
        ->and($eightReport['school_class'])->toBe('VIII A');
});

it('classifies KB as TK in Rekap Per Kelas without requiring a tariff', function () {
    $academicYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $kb = SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);
    $student = Student::factory()->create(['nama_lengkap' => 'Anak KB', 'class_id' => $kb->id]);
    enrollClassRecapStudent($student, $academicYear, $kb);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::TK, $kb->id);

    expect($report['school_level'])->toBe(SchoolLevel::TK->value)
        ->and($report['school_class'])->toBe('KB')
        ->and(collect($report['rows'])->pluck('student_name')->all())->toBe(['Anak KB'])
        ->and(PaymentRate::query()->where('class_level', -3)->count())->toBe(0);
});

it('keeps a graduated student in a legitimate historical class report', function () {
    [$academicYear, $historicalClass, $student] = makeClassRecapStudent(studentName: 'Alumni Historis');
    $currentClass = SchoolClass::query()->create(['name' => 'IX A', 'level' => 9]);
    $student->update(['class_id' => $currentClass->id, 'status' => 'lulus']);

    $report = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $historicalClass->id);

    expect(collect($report['rows'])->pluck('student_name')->all())->toBe(['Alumni Historis']);
});

it('builds July through June monthly columns and uses persisted bill-period allocations', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');
    configureClassRecapType($spp, SchoolLevel::SMP);
    configureClassRecapType($ekskul, SchoolLevel::SMP);
    configureClassRecapType($osis, SchoolLevel::SMP);
    $septemberSpp = makeMonthlyBill($student, $spp, 970_000, 9, 2026);
    $septemberEkskul = makeMonthlyBill($student, $ekskul, 60_000, 9, 2026);
    $septemberOsis = makeMonthlyBill($student, $osis, 5_000, 9, 2026);
    makeMonthlyBill($student, $spp, 970_000, 10, 2026);
    $lateSppPayment = createClassRecapAllocation($septemberSpp, 800_000, Payment::STATUS_ACTIVE, '2026-10-10');
    $lateSppPayment->update(['total_amount' => 1]);
    createClassRecapAllocation($septemberEkskul, 60_000, Payment::STATUS_ACTIVE, '2026-10-10');
    createClassRecapAllocation($septemberOsis, 5_000, Payment::STATUS_ACTIVE, '2026-10-10');
    createClassRecapAllocation($septemberSpp, 170_000, Payment::STATUS_CANCELLED, '2026-10-11');
    PaymentRate::factory()->create([
        'payment_type_id' => $spp->id,
        'class_level' => 7,
        'amount' => 1_050_000,
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2027-01-01',
    ]);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $row = $report['rows'][0];

    expect(array_column($report['months'], 'label'))->toBe([
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    ])->and(array_column($report['months'], 'year'))->toBe([
        2026, 2026, 2026, 2026, 2026, 2026,
        2027, 2027, 2027, 2027, 2027, 2027,
    ])->and($row['monthly_summary'])->toBe([
        $spp->id => 970_000.0,
        $ekskul->id => 60_000.0,
        $osis->id => 5_000.0,
        'total' => 1_035_000.0,
    ])->and($row['monthly_paid']['2026-09'])->toBe([
        $spp->id => 800_000.0,
        $ekskul->id => 60_000.0,
        $osis->id => 5_000.0,
    ])->and($row['monthly_paid']['2026-10'])->toBe([
        $spp->id => 0.0,
        $ekskul->id => 0.0,
        $osis->id => 0.0,
    ]);
});

it('uses the latest adjusted monthly target and ignores manual details without bill_id', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('  sPp  ');
    configureClassRecapType($spp, SchoolLevel::SMP);
    $spp->update(['is_active' => false]);
    makeMonthlyBill($student, $spp, 850_000, 7, 2026);
    $septemberBill = makeMonthlyBill($student, $spp, 970_000, 9, 2026);
    BillAdjustment::factory()->create(['bill_id' => $septemberBill->id, 'amount' => -70_000]);
    $bank = Bank::query()->firstOrCreate(['name' => 'Bank Manual Rekap'], [
        'type' => Bank::TYPE_BANK,
        'account_number' => 'MANUAL-REKAP',
        'account_name' => 'Yayasan Sekolah',
        'is_active' => true,
    ]);
    $user = User::query()->first() ?? User::factory()->create();
    $manualPayment = Payment::query()->create([
        'receipt_number' => 'KWT-MANUAL-REKAP',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-10',
        'total_amount' => 970_000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $manualPayment->id,
        'bill_id' => null,
        'payment_type_id' => $spp->id,
        'period_month' => 9,
        'period_year' => 2026,
        'amount' => 970_000,
    ]);

    $row = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['rows'][0];

    expect($row['monthly_summary'][$spp->id])->toBe(900_000.0)
        ->and($row['monthly_paid']['2026-09'][$spp->id])->toBe(0.0);
});

it('renders one integrated table with dynamic monthly yearly and one-time headers', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    $book = makeBillType('Uang Buku');
    $activity = makeBillType('Uang Kegiatan');
    $pangkal = makeBillType('Uang Pangkal');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly);
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly);
    configureClassRecapType($activity, SchoolLevel::SMP, BillFrequency::Yearly);
    configureClassRecapType($pangkal, SchoolLevel::SMP, BillFrequency::OneTime);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);

    Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        ->assertSee('Bulanan')
        ->assertSee('Tahunan')
        ->assertSee('Sekali Bayar')
        ->assertSee('Uang Pangkal')
        ->assertSee('Uang Buku')
        ->assertSee('Uang Kegiatan')
        ->assertSee('Juli 2026')
        ->assertSee('Juni 2027')
        ->assertSee('JUMLAH')
        ->assertSeeHtml('colspan="14"')
        ->assertSeeHtml('colspan="6"')
        ->assertSeeHtml('colspan="3"')
        ->assertDontSee('TARGET');
});

it('renders a sticky clone header aligned with the real recap table', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);

    $component = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id);

    $html = $component->html();

    $component
        ->assertSeeHtml('x-data="classRecapStickyHeader()"')
        ->assertSeeHtml('class="sticky top-16 z-30 bg-surface-container-low"')
        ->assertSeeHtml('x-ref="cloneScroller"')
        ->assertSeeHtml('x-ref="recapScroller"')
        ->assertSeeHtml('x-ref="recapTable"')
        ->assertSeeHtml('class="recap-clone-table')
        ->assertSeeHtml('class="recap-real-table')
        ->assertSeeHtml('<thead class="uppercase tracking-wider text-on-surface-variant">')
        ->assertSeeHtml('<thead class="recap-measure-head invisible uppercase tracking-wider text-on-surface-variant" aria-hidden="true">')
        ->assertSeeHtml('sticky left-0 z-30 w-14 min-w-14 border-r border-outline-variant bg-surface-container-low')
        ->assertSeeHtml('sticky left-14 z-30 w-56 min-w-56 border-r-2 border-outline bg-surface-container-low')
        ->assertSeeHtml('sticky left-0 z-10 border-r border-outline-variant bg-surface-container-lowest')
        ->assertSeeHtml('sticky left-14 z-10 border-r-2 border-outline bg-surface-container-lowest')
        ->assertSeeHtml('sticky left-0 z-20 border-r-2 border-outline bg-primary-fixed')
        ->assertSeeHtml('overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-b-xl')
        ->assertSee('Bulanan')
        ->assertSee('JUMLAH');

    expect($html)->toBeString()
        ->and(substr_count($html, 'class="recap-clone-table'))->toBe(1)
        ->and(substr_count($html, 'class="recap-real-table'))->toBe(1)
        ->and(substr_count($html, 'class="border-b border-outline-variant bg-surface-container-low"'))->toBe(4)
        ->and(substr_count($html, 'class="border-b-2 border-primary bg-surface-container-low text-label-sm"'))->toBe(2);
});

it('scopes yearly bills to the selected academic year and caps paid amounts', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $book = makeBillType('Uang Buku');
    $activity = makeBillType('Uang Kegiatan');
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly);
    configureClassRecapType($activity, SchoolLevel::SMP, BillFrequency::Yearly);
    $bookBill = makeYearlyBill($student, $book, 1_050_000, '2026/2027');
    $activityBill = makeYearlyBill($student, $activity, 2_275_000, '2026/2027');
    makeYearlyBill($student, $book, 9_000_000, '2025/2026');
    BillAdjustment::factory()->create(['bill_id' => $bookBill->id, 'amount' => -50_000]);
    createClassRecapAllocation($bookBill, 1_200_000);
    createClassRecapAllocation($bookBill, 50_000, Payment::STATUS_CANCELLED);
    createClassRecapAllocation($activityBill, 1_500_000);
    createClassRecapAllocation($activityBill, 775_000, Payment::STATUS_CANCELLED);

    $row = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['rows'][0];

    expect($row['yearly'][$book->id])->toBe([
        'target' => 1_000_000.0,
        'paid' => 1_000_000.0,
        'remaining' => 0.0,
    ])->and($row['yearly'][$activity->id])->toBe([
        'target' => 2_275_000.0,
        'paid' => 1_500_000.0,
        'remaining' => 775_000.0,
    ]);
});

it('does not carry prior-year yearly bills into a promoted students selected year', function () {
    $yearSeven = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => false, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
    ]);
    $yearEight = AcademicYear::query()->updateOrCreate(['year' => '2027/2028'], [
        'is_active' => true, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30',
    ]);
    $classSeven = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $classEight = SchoolClass::query()->create(['name' => 'VIII A', 'level' => 8]);
    $student = Student::factory()->create(['class_id' => $classEight->id]);
    enrollClassRecapStudent($student, $yearSeven, $classSeven);
    enrollClassRecapStudent($student, $yearEight, $classEight);
    $book = makeBillType('Uang Buku');
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly, $classEight->level);
    makeYearlyBill($student, $book, 2_000_000, '2026/2027');
    makeYearlyBill($student, $book, 2_400_000, '2027/2028');

    $row = app(StudentClassPaymentRecapService::class)
        ->generate($yearEight->id, SchoolLevel::SMP, $classEight->id)['rows'][0];

    expect($row['yearly'][$book->id]['target'])->toBe(2_400_000.0);
});

it('carries the single persisted Uang Pangkal bill through legitimate enrollment years without mutation', function () {
    $firstYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => false, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
    ]);
    $secondYear = AcademicYear::query()->updateOrCreate(['year' => '2027/2028'], [
        'is_active' => true, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30',
    ]);
    $classSeven = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $classEight = SchoolClass::query()->create(['name' => 'VIII A', 'level' => 8]);
    $student = Student::factory()->create(['class_id' => $classEight->id]);
    enrollClassRecapStudent($student, $firstYear, $classSeven);
    enrollClassRecapStudent($student, $secondYear, $classEight);
    $pangkal = makeBillType('Uang Pangkal');
    configureClassRecapType($pangkal, SchoolLevel::SMP, BillFrequency::OneTime, $classSeven->level);
    makeBillRate($pangkal, $classEight->level, 1, ['billing_frequency' => BillFrequency::OneTime]);
    $bill = makeOneTimeBill($student, $pangkal, 5_000_000, '2026/2027');
    createClassRecapAllocation($bill, 2_000_000);
    createClassRecapAllocation($bill, 1_000_000, Payment::STATUS_CANCELLED);
    $beforeCounts = [StudentBill::count(), Payment::count(), PaymentDetail::count(), StudentAcademicEnrollment::count()];

    $firstRow = app(StudentClassPaymentRecapService::class)
        ->generate($firstYear->id, SchoolLevel::SMP, $classSeven->id)['rows'][0];
    $secondRow = app(StudentClassPaymentRecapService::class)
        ->generate($secondYear->id, SchoolLevel::SMP, $classEight->id)['rows'][0];

    expect($firstRow['one_time'][$pangkal->id])->toBe([
        'target' => 5_000_000.0,
        'paid' => 2_000_000.0,
        'remaining' => 3_000_000.0,
    ])->and($secondRow['one_time'][$pangkal->id])->toBe($firstRow['one_time'][$pangkal->id])
        ->and(StudentBill::query()->where('student_id', $student->id)->where('billing_frequency', BillFrequency::OneTime->value)->count())->toBe(1)
        ->and([StudentBill::count(), Payment::count(), PaymentDetail::count(), StudentAcademicEnrollment::count()])->toBe($beforeCounts);
});

it('reconciles every JUMLAH value from visible student rows only', function () {
    [$academicYear, $schoolClass, $studentA] = makeClassRecapStudent(studentName: 'Aisyah');
    $studentB = Student::factory()->create(['nama_lengkap' => 'Budi', 'class_id' => $schoolClass->id]);
    enrollClassRecapStudent($studentB, $academicYear, $schoolClass);
    $otherClass = SchoolClass::query()->create(['name' => 'VII B', 'level' => 7]);
    $outsider = Student::factory()->create(['nama_lengkap' => 'Citra Luar Kelas', 'class_id' => $otherClass->id]);
    enrollClassRecapStudent($outsider, $academicYear, $otherClass);
    $types = collect(['SPP', 'Ekskul', 'OSIS', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'])
        ->mapWithKeys(function (string $name): array {
            $type = makeBillType($name);
            $frequency = match ($name) {
                'Uang Buku', 'Uang Kegiatan' => BillFrequency::Yearly,
                'Uang Pangkal' => BillFrequency::OneTime,
                default => BillFrequency::Monthly,
            };
            configureClassRecapType($type, SchoolLevel::SMP, $frequency);

            return [$name => $type];
        });

    foreach ([[$studentA, 1], [$studentB, 2], [$outsider, 100]] as [$student, $factor]) {
        $julySpp = makeMonthlyBill($student, $types['SPP'], 100_000 * $factor, 7, 2026);
        $juneEkskul = makeMonthlyBill($student, $types['Ekskul'], 10_000 * $factor, 6, 2027);
        makeMonthlyBill($student, $types['OSIS'], 1_000 * $factor, 7, 2026);
        $book = makeYearlyBill($student, $types['Uang Buku'], 200_000 * $factor);
        makeYearlyBill($student, $types['Uang Kegiatan'], 300_000 * $factor);
        $entry = makeOneTimeBill($student, $types['Uang Pangkal'], 400_000 * $factor);
        createClassRecapAllocation($julySpp, 50_000 * $factor);
        createClassRecapAllocation($juneEkskul, 5_000 * $factor);
        createClassRecapAllocation($book, 100_000 * $factor);
        createClassRecapAllocation($entry, 150_000 * $factor);
    }

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(collect($report['rows'])->pluck('student_name')->all())->toBe(['Aisyah', 'Budi'])
        ->and($report['student_count'])->toBe(2);

    foreach ($report['payment_types']['monthly'] as $type) {
        expect($report['totals']['monthly_summary'][$type['id']])
            ->toBe((float) collect($report['rows'])->sum(fn (array $row): float => $row['monthly_summary'][$type['id']]));
    }

    expect($report['totals']['monthly_summary']['total'])
        ->toBe((float) collect($report['rows'])->sum(fn (array $row): float => $row['monthly_summary']['total']));

    foreach ($report['months'] as $month) {
        foreach ($report['payment_types']['monthly'] as $type) {
            expect($report['totals']['monthly_paid'][$month['key']][$type['id']])
                ->toBe((float) collect($report['rows'])->sum(fn (array $row): float => $row['monthly_paid'][$month['key']][$type['id']]));
        }
    }

    foreach ($report['payment_types']['yearly'] as $type) {
        foreach (['target', 'paid', 'remaining'] as $key) {
            expect($report['totals']['yearly'][$type['id']][$key])
                ->toBe((float) collect($report['rows'])->sum(fn (array $row): float => $row['yearly'][$type['id']][$key]));
        }
    }

    foreach ($report['payment_types']['one_time'] as $type) {
        foreach (['target', 'paid', 'remaining'] as $key) {
            expect($report['totals']['one_time'][$type['id']][$key])
                ->toBe((float) collect($report['rows'])->sum(fn (array $row): float => $row['one_time'][$type['id']][$key]));
        }
    }
});

it('excludes Daycare records and never merges their financial identifiers', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent(studentName: 'Siswa Sekolah');
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    $daycareChild = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Daycare']);
    DaycarePayment::factory()->create(['daycare_child_id' => $daycareChild->id, 'total_amount' => 9_000_000]);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(collect($report['rows'])->pluck('student_name')->all())->toBe(['Siswa Sekolah'])
        ->and($report['totals']['monthly_summary'][$spp->id])->toBe(970_000.0);
});

it('loads a whole class with a bounded batched query count', function () {
    [$academicYear, $schoolClass, $firstStudent] = makeClassRecapStudent(studentName: 'Siswa 01');
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP);
    makeMonthlyBill($firstStudent, $spp, 970_000, 7, 2026);

    for ($number = 2; $number <= 20; $number++) {
        $student = Student::factory()->create([
            'nama_lengkap' => sprintf('Siswa %02d', $number),
            'class_id' => $schoolClass->id,
        ]);
        enrollClassRecapStudent($student, $academicYear, $schoolClass);
        makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($report['student_count'])->toBe(20)
        ->and($queryCount)->toBeLessThanOrEqual(10);
});

it('derives recap columns from rate frequencies instead of payment type names', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    $osis = makeBillType('OSIS');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly);
    configureClassRecapType($osis, SchoolLevel::SMP, BillFrequency::Yearly);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    makeYearlyBill($student, $osis, 200_000, '2026/2027');

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(array_column($report['payment_types']['monthly'], 'id'))->toBe([$spp->id])
        ->and(array_column($report['payment_types']['yearly'], 'id'))->toBe([$osis->id])
        ->and($report['payment_types']['one_time'])->toBe([])
        ->and($report['rows'][0]['monthly_summary'][$spp->id])->toBe(970_000.0)
        ->and($report['rows'][0]['yearly'][$osis->id]['target'])->toBe(200_000.0);
});

it('keeps the column of a configured payment type even when no bill exists', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $uniform = makeBillType('Biaya Seragam');
    configureClassRecapType($uniform, SchoolLevel::SMP, BillFrequency::Monthly);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types']['monthly'])->toBe([['id' => $uniform->id, 'name' => 'Biaya Seragam']])
        ->and($report['rows'][0]['monthly_summary'][$uniform->id])->toBe(0.0)
        ->and($report['rows'][0]['monthly_paid']['2026-07'][$uniform->id])->toBe(0.0)
        ->and($report['rows'][0]['monthly_summary']['total'])->toBe(0.0);
});

it('supports multiple one-time payment types independently', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $pangkal = makeBillType('Uang Pangkal');
    $uniform = makeBillType('Seragam');
    configureClassRecapType($pangkal, SchoolLevel::SMP, BillFrequency::OneTime);
    configureClassRecapType($uniform, SchoolLevel::SMP, BillFrequency::OneTime);
    $pangkalBill = makeOneTimeBill($student, $pangkal, 5_000_000, '2026/2027');
    createClassRecapAllocation($pangkalBill, 2_000_000);
    makeOneTimeBill($student, $uniform, 750_000, '2026/2027');

    $row = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['rows'][0];

    expect($row['one_time'][$pangkal->id])->toBe([
        'target' => 5_000_000.0,
        'paid' => 2_000_000.0,
        'remaining' => 3_000_000.0,
    ])->and($row['one_time'][$uniform->id])->toBe([
        'target' => 750_000.0,
        'paid' => 0.0,
        'remaining' => 750_000.0,
    ]);
});

it('drops a payment type once its jenjang mapping is deactivated', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    PaymentTypeSchoolLevel::query()
        ->where('payment_type_id', $spp->id)
        ->where('school_level', SchoolLevel::SMP)
        ->update(['is_active' => false]);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types']['monthly'])->toBe([])
        ->and($report['payment_types']['yearly'])->toBe([])
        ->and($report['payment_types']['one_time'])->toBe([])
        ->and($report['rows'][0]['monthly_summary']['total'])->toBe(0.0);
});

it('scopes recap columns to the selected jenjang and ignores foreign mappings', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $smpType = makeBillType('SPP');
    $sdType = makeBillType('Ekskul');
    configureClassRecapType($smpType, SchoolLevel::SMP, BillFrequency::Monthly);
    configureClassRecapType($sdType, SchoolLevel::SD, BillFrequency::Monthly);
    makeMonthlyBill($student, $sdType, 20_000, 7, 2026);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(array_column($report['payment_types']['monthly'], 'id'))->toBe([$smpType->id])
        ->and($report['rows'][0]['monthly_summary'])->toBe([$smpType->id => 0.0, 'total' => 0.0]);
});

it('keeps OSIS out of an SD recap while including it for SMP', function () {
    [$academicYear, $sdClass, $sdStudent] = makeClassRecapStudent('2026/2027', 6, 'VI A', 'Siswa SD');
    [,$smpClass, $smpStudent] = makeClassRecapStudent('2026/2027', 7, 'VII A', 'Siswa SMP');
    $spp = makeBillType('SPP');
    $osis = makeBillType('OSIS');
    configureClassRecapType($spp, SchoolLevel::SD, BillFrequency::Monthly, $sdClass->level);
    configureClassRecapType($osis, SchoolLevel::SMP, BillFrequency::Monthly, $smpClass->level);
    makeMonthlyBill($sdStudent, $spp, 100_000, 7, 2026);
    makeMonthlyBill($smpStudent, $osis, 5_000, 7, 2026);

    $sdReport = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SD, $sdClass->id);
    $smpReport = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $smpClass->id);

    expect(array_column($sdReport['payment_types']['monthly'], 'name'))->toBe(['SPP'])
        ->and(array_column($smpReport['payment_types']['monthly'], 'name'))->toBe(['OSIS'])
        ->and($sdReport['rows'][0]['monthly_summary'][$spp->id])->toBe(100_000.0)
        ->and($smpReport['rows'][0]['monthly_summary'][$osis->id])->toBe(5_000.0);
});

it('shows a newly configured payment type without source changes', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $vaccine = makeBillType('Vaksinasi Tahunan');
    configureClassRecapType($vaccine, SchoolLevel::SMP, BillFrequency::Yearly);
    makeYearlyBill($student, $vaccine, 150_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types']['yearly'])->toBe([['id' => $vaccine->id, 'name' => 'Vaksinasi Tahunan']])
        ->and($report['rows'][0]['yearly'][$vaccine->id]['target'])->toBe(150_000.0)
        ->and($report['rows'][0]['yearly'][$vaccine->id]['remaining'])->toBe(150_000.0)
        ->and($report['totals']['yearly'][$vaccine->id]['target'])->toBe(150_000.0);
});

it('scopes yearly columns to the recapped class level so a TK-only yearly Ekskul stays out of SMP', function () {
    [$academicYear, $smpClass] = makeClassRecapStudent('2026/2027', 7, 'VII A', 'Siswa SMP');
    [,$tkClass] = makeClassRecapStudent('2026/2027', -1, 'TKB', 'Siswa TK');
    $ekskul = makeBillType('Ekskul');
    makeLevelDefault($ekskul, SchoolLevel::TK);
    makeLevelDefault($ekskul, SchoolLevel::SMP);
    makeBillRate($ekskul, -1, 300_000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($ekskul, 7, 60_000, ['billing_frequency' => BillFrequency::Monthly]);

    $tkReport = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::TK, $tkClass->id);
    $smpReport = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $smpClass->id);

    expect(array_column($tkReport['payment_types']['yearly'], 'name'))->toBe(['Ekskul'])
        ->and($tkReport['payment_types']['monthly'])->toBe([])
        ->and(array_column($smpReport['payment_types']['monthly'], 'name'))->toBe(['Ekskul'])
        ->and($smpReport['payment_types']['yearly'])->toBe([])
        ->and($smpReport['payment_types']['one_time'])->toBe([]);
});

it('does not show a jenjang-applicable type when no rate matches the recapped class level', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $ekskul = makeBillType('Ekskul Kelas VIII');
    makeLevelDefault($ekskul, SchoolLevel::SMP);
    makeBillRate($ekskul, 8, 75_000, ['billing_frequency' => BillFrequency::Yearly]);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types']['yearly'])->toBe([])
        ->and($report['payment_types']['monthly'])->toBe([])
        ->and($report['payment_types']['one_time'])->toBe([]);
});

it('keeps an applicable class-level payment type visible before any bill or payment exists', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $book = makeBillType('Uang Buku');
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly, $schoolClass->level);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(StudentBill::query()->where('payment_type_id', $book->id)->count())->toBe(0)
        ->and($report['payment_types']['yearly'])->toBe([['id' => $book->id, 'name' => 'Uang Buku']])
        ->and($report['rows'][0]['yearly'][$book->id]['target'])->toBe(0.0)
        ->and($report['rows'][0]['yearly'][$book->id]['remaining'])->toBe(0.0)
        ->and($report['totals']['yearly'][$book->id]['target'])->toBe(0.0);
});

it('keeps a prospective-student payment type in the recap columns', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $formulir = makeBillType('Formulir');
    $formulir->update(['audience' => PaymentTypeAudience::ProspectiveStudent]);
    configureClassRecapType($formulir, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types']['one_time'])->toBe([['id' => $formulir->id, 'name' => 'Formulir']])
        ->and($report['payment_types']['monthly'])->toBe([])
        ->and($report['payment_types']['yearly'])->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Formulir Pendaftaran prospective pada rekap per kelas
|--------------------------------------------------------------------------
*/

it('fills a paid prospective Formulir column for a converted student', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0001', 'Rina Calon');
    $bill = makeClassRecapProspectiveBill($prospect, $formulir, 500_000);
    payClassRecapProspectiveBill($bill, 500_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 500_000.0, 'paid' => 500_000.0, 'remaining' => 0.0])
        ->and($report['totals']['one_time'][$formulir->id])->toBe(['target' => 500_000.0, 'paid' => 500_000.0, 'remaining' => 0.0])
        // Tagihan pendaftaran tetap hidup di sisi prospective, tidak disalin ke student.
        ->and(StudentBill::query()->where('payment_type_id', $formulir->id)->count())->toBe(0);
});

it('reports the remaining amount of a partially paid prospective Formulir', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0002', 'Rina Sebagian');
    $bill = makeClassRecapProspectiveBill($prospect, $formulir, 500_000);
    payClassRecapProspectiveBill($bill, 200_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 500_000.0, 'paid' => 200_000.0, 'remaining' => 300_000.0]);
});

it('does not count a cancelled prospective payment as Formulir paid', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0003', 'Rina Dibatalkan');
    $bill = makeClassRecapProspectiveBill($prospect, $formulir, 500_000);
    payClassRecapProspectiveBill($bill, 500_000, ProspectiveStudentPayment::STATUS_CANCELLED);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 500_000.0, 'paid' => 0.0, 'remaining' => 500_000.0]);
});

it('ignores prospective Formulir data belonging to another student', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    // Siswa ini sengaja tidak di-enroll di kelas yang sedang direkap.
    $otherStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Luar Rekap']);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0004', 'Rina Lain');
    $bill = makeClassRecapProspectiveBill($prospect, $formulir, 500_000);
    payClassRecapProspectiveBill($bill, 500_000);

    // Calon siswa terkonversi milik siswa yang tidak direkap di kelas ini.
    $otherProspect = makeClassRecapConvertedProspect($otherStudent, $academicYear, $schoolClass, 'REG-FORM-0005', 'Rina Di Kelas Lain');
    $otherBill = makeClassRecapProspectiveBill($otherProspect, $formulir, 750_000);
    payClassRecapProspectiveBill($otherBill, 750_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['student_count'])->toBe(1)
        ->and(classRecapFormulirBalance($report))->toBe(['target' => 500_000.0, 'paid' => 500_000.0, 'remaining' => 0.0])
        ->and($report['totals']['one_time'][$formulir->id]['target'])->toBe(500_000.0);
});

it('ignores a prospective Formulir bill that was never converted', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $pending = makeClassRecapConvertedProspect(
        $student,
        $academicYear,
        $schoolClass,
        'REG-FORM-0006',
        'Rina Belum Konversi',
        converted: false,
    );
    $bill = makeClassRecapProspectiveBill($pending, $formulir, 500_000);
    payClassRecapProspectiveBill($bill, 500_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0]);
});

it('ignores a prospective Formulir bill from a future academic year', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0007', 'Rina Tahun Depan');
    $futureBill = makeClassRecapProspectiveBill($prospect, $formulir, 500_000, '2027/2028');
    payClassRecapProspectiveBill($futureBill, 500_000);

    $pastBill = makeClassRecapProspectiveBill($prospect, $formulir, 400_000, '2025/2026');
    payClassRecapProspectiveBill($pastBill, 400_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    // Tagihan one_time terbawa ke tahun berikutnya, tagihan tahun depan tidak.
    expect(classRecapFormulirBalance($report))->toBe(['target' => 400_000.0, 'paid' => 400_000.0, 'remaining' => 0.0]);
});

it('keeps student payment types on StudentBill while showing prospective Formulir', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP);
    $uangPangkal = makeBillType('Uang Pangkal');
    configureClassRecapType($uangPangkal, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);

    payActiveBill(makeMonthlyBill($student, $spp, 500_000, 8, 2026), 500_000);
    payActiveBill(makeOneTimeBill($student, $uangPangkal, 750_000), 750_000);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0008', 'Rina Combo');
    payClassRecapProspectiveBill(makeClassRecapProspectiveBill($prospect, $formulir, 350_000), 350_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    $oneTime = collect($report['payment_types']['one_time'])->keyBy('name');

    expect(classRecapFormulirBalance($report))->toBe(['target' => 350_000.0, 'paid' => 350_000.0, 'remaining' => 0.0])
        // Tipe one_time sisi siswa tetap dibaca dari StudentBill.
        ->and($report['rows'][0]['one_time'][$oneTime['Uang Pangkal']['id']])
        ->toBe(['target' => 750_000.0, 'paid' => 750_000.0, 'remaining' => 0.0])
        ->and($report['rows'][0]['monthly_paid']['2026-08'][$spp->id])->toBe(500_000.0);
});

it('never sums a student bill and a prospective bill for the same prospective type', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0009', 'Rina Ganda');
    payClassRecapProspectiveBill(makeClassRecapProspectiveBill($prospect, $formulir, 500_000), 500_000);

    // Data StudentBill untuk tipe prospective tidak pernah dibuat sistem, tapi
    // kalau muncul, kolom tetap hanya boleh berisi satu sumber.
    makeOneTimeBill($student, $formulir, 500_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 500_000.0, 'paid' => 500_000.0, 'remaining' => 0.0]);
});

it('stops reading prospective history once the payment type audience becomes student', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);

    $prospect = makeClassRecapConvertedProspect($student, $academicYear, $schoolClass, 'REG-FORM-0010', 'Rina Diganti');
    payClassRecapProspectiveBill(makeClassRecapProspectiveBill($prospect, $formulir, 500_000), 500_000);

    // Konfigurasi berubah: tipe kini milik siswa, jadi StudentBill yang berlaku.
    $formulir->update(['audience' => PaymentTypeAudience::Student]);
    makeOneTimeBill($student, $formulir, 600_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(classRecapFormulirBalance($report))->toBe(['target' => 600_000.0, 'paid' => 0.0, 'remaining' => 600_000.0]);
});

it('loads prospective Formulir data without adding a query per student', function () {
    [$academicYear, $schoolClass, $firstStudent] = makeClassRecapStudent();
    $formulir = makeClassRecapFormulirType(SchoolLevel::SMP, $schoolClass->level);
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP);

    $enrolled = [collect([$firstStudent])];

    for ($number = 2; $number <= 20; $number++) {
        $student = Student::factory()->create([
            'nama_lengkap' => sprintf('Siswa %02d', $number),
            'class_id' => $schoolClass->id,
        ]);
        enrollClassRecapStudent($student, $academicYear, $schoolClass);
        $enrolled[] = collect([$student]);
    }

    $measure = function () use ($academicYear, $schoolClass): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $before = $measure();

    foreach ($enrolled as [$student]) {
        $prospect = makeClassRecapConvertedProspect(
            $student,
            $academicYear,
            $schoolClass,
            'REG-BATCH-'.str_pad((string) $student->id, 4, '0', STR_PAD_LEFT),
            'Calon '.$student->nama_lengkap,
        );
        payClassRecapProspectiveBill(makeClassRecapProspectiveBill($prospect, $formulir, 500_000), 500_000);
    }

    $after = $measure();

    expect($after)->toBe($before)
        ->and($before)->toBeLessThanOrEqual(11);
});

it('excludes a jenjang mapped Jemputan from the main recap table', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    $spp = makeBillType('SPP');
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(array_column($report['payment_types']['monthly'], 'id'))->toBe([$spp->id])
        ->and(array_column($report['payment_types']['monthly'], 'name'))->toBe(['SPP'])
        ->and($report['rows'][0]['monthly_summary'][$jemputan->id] ?? null)->toBeNull()
        ->and($report['rows'][0]['monthly_summary']['total'])->toBe(970_000.0)
        ->and($report['totals']['monthly_summary']['total'])->toBe(970_000.0)
        ->and($report['totals']['monthly_paid']['2026-07'][$jemputan->id] ?? null)->toBeNull();
});

it('excludes a jenjang mapped Adm Jemputan from the main recap table', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $adm = makeBillType('Adm Jemputan');
    $pangkal = makeBillType('Uang Pangkal');
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    configureClassRecapType($pangkal, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    $admBill = makeOneTimeBill($student, $adm, 50_000, '2026/2027');
    createClassRecapAllocation($admBill, 50_000);
    makeOneTimeBill($student, $pangkal, 5_000_000, '2026/2027');

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect(array_column($report['payment_types']['one_time'], 'id'))->toBe([$pangkal->id])
        ->and($report['rows'][0]['one_time'][$adm->id] ?? null)->toBeNull()
        ->and($report['rows'][0]['one_time'][$pangkal->id]['target'])->toBe(5_000_000.0)
        ->and($report['totals']['one_time'][$adm->id] ?? null)->toBeNull();
});

it('keeps the main recap aligned when only Jemputan types are mapped', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    $adm = makeBillType('Adm Jemputan');
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);

    expect($report['payment_types'])->toBe(['monthly' => [], 'yearly' => [], 'one_time' => []])
        ->and($report['rows'][0]['monthly_summary'])->toBe(['total' => 0.0])
        ->and($report['totals']['monthly_paid'])->toBe(array_fill_keys(array_column($report['months'], 'key'), []));
});

it('lists a student with a Jemputan bill in the combined Jemputan table', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    $juli = makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);
    $agustus = makeMonthlyBill($student, $jemputan, 500_000, 8, 2026);
    createClassRecapAllocation($juli, 500_000);
    createClassRecapAllocation($agustus, 200_000);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $recap = $report['segregated'];

    expect($recap['title'])->toBe('Rekap Jemputan')
        ->and($recap['student_count'])->toBe(1)
        ->and($recap['rows'])->toHaveCount(1)
        ->and($recap['rows'][0]['student_name'])->toBe($student->nama_lengkap)
        ->and($recap['rows'][0]['months']['2026-07'])->toBe([
            'target' => 500_000.0,
            'paid' => 500_000.0,
            'remaining' => 0.0,
        ])
        ->and($recap['rows'][0]['months']['2026-08'])->toBe([
            'target' => 500_000.0,
            'paid' => 200_000.0,
            'remaining' => 300_000.0,
        ])
        ->and($recap['rows'][0]['months'])->not->toHaveKey('2026-09')
        ->and($recap['rows'][0]['jemputan']['remaining'])->toBe(300_000.0)
        ->and($recap['rows'][0]['adm_jemputan'])->toBeNull();
});

it('omits students without any Jemputan or Adm Jemputan bill from the combined table', function () {
    [$academicYear, $schoolClass, $withBill] = makeClassRecapStudent();
    $withoutBill = Student::factory()->create([
        'nama_lengkap' => 'Tanpa Jemputan',
        'class_id' => $schoolClass->id,
    ]);
    enrollClassRecapStudent($withoutBill, $academicYear, $schoolClass);
    $jemputan = makeBillType('Jemputan');
    $spp = makeBillType('SPP');
    makeMonthlyBill($withBill, $jemputan, 500_000, 7, 2026);
    makeMonthlyBill($withoutBill, $spp, 970_000, 7, 2026);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $recap = $report['segregated'];

    expect($report['student_count'])->toBe(2)
        ->and($recap['student_count'])->toBe(1)
        ->and(array_column($recap['rows'], 'student_name'))->toBe([$withBill->nama_lengkap]);
});

it('maps the Jemputan matrix to July through June across the academic year boundary', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);
    makeMonthlyBill($student, $jemputan, 500_000, 1, 2027);
    makeMonthlyBill($student, $jemputan, 500_000, 6, 2027);

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $months = array_column($report['months'], 'key');
    $monthsRecap = $report['segregated']['rows'][0]['months'];

    expect($months)->toBe([
        '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12',
        '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06',
    ])
        ->and(array_keys($monthsRecap))->toBe(['2026-07', '2027-01', '2027-06'])
        ->and(array_keys($report['segregated']['month_totals']))->toBe($months);
});

it('totals the Jemputan matrix per student and across all students', function () {
    [$academicYear, $schoolClass, $first] = makeClassRecapStudent();
    $second = Student::factory()->create([
        'nama_lengkap' => 'Budi Jemputan',
        'class_id' => $schoolClass->id,
    ]);
    enrollClassRecapStudent($second, $academicYear, $schoolClass);
    $jemputan = makeBillType('Jemputan');

    $firstJuly = makeMonthlyBill($first, $jemputan, 500_000, 7, 2026);
    makeMonthlyBill($first, $jemputan, 500_000, 8, 2026);
    $secondJuly = makeMonthlyBill($second, $jemputan, 600_000, 7, 2026);
    BillAdjustment::query()->create([
        'bill_id' => $firstJuly->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50_000,
        'reason' => 'Diskon siswa',
    ]);
    createClassRecapAllocation($firstJuly, 400_000);
    createClassRecapAllocation($secondJuly, 100_000);

    $recap = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['segregated'];

    $firstRow = collect($recap['rows'])->firstWhere('student_name', $first->nama_lengkap);
    $secondRow = collect($recap['rows'])->firstWhere('student_name', $second->nama_lengkap);

    expect($firstRow['months']['2026-07'])->toBe([
        'target' => 450_000.0,
        'paid' => 400_000.0,
        'remaining' => 50_000.0,
    ])
        ->and($firstRow['months']['2026-08'])->toBe([
            'target' => 500_000.0,
            'paid' => 0.0,
            'remaining' => 500_000.0,
        ])
        ->and($firstRow['jemputan']['target'])->toBe(950_000.0)
        ->and($firstRow['jemputan']['paid'])->toBe(400_000.0)
        ->and($firstRow['jemputan']['remaining'])->toBe(550_000.0)
        ->and($secondRow['jemputan']['target'])->toBe(600_000.0)
        ->and($secondRow['jemputan']['paid'])->toBe(100_000.0)
        ->and($secondRow['jemputan']['remaining'])->toBe(500_000.0)
        ->and($recap['jemputan_totals'])->toBe([
            'target' => 1_550_000.0,
            'paid' => 500_000.0,
            'remaining' => 1_050_000.0,
        ])
        ->and($recap['adm_jemputan_totals'])->toBe(['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0])
        ->and($recap['total_tagihan'])->toBe(1_550_000.0)
        ->and($firstRow['total_tagihan'])->toBe(950_000.0)
        ->and($secondRow['total_tagihan'])->toBe(600_000.0)
        ->and($recap['month_totals']['2026-07']['target'])->toBe(1_050_000.0)
        ->and($recap['month_totals']['2026-07']['paid'])->toBe(500_000.0)
        ->and($recap['month_totals']['2026-07']['remaining'])->toBe(550_000.0)
        ->and($recap['month_totals']['2026-08']['target'])->toBe(500_000.0)
        ->and($recap['month_totals']['2026-08']['paid'])->toBe(0.0)
        ->and($recap['month_totals']['2026-09']['target'])->toBe(0.0);
});

it('merges Adm Jemputan into the same row as the monthly Jemputan matrix', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $adm = makeBillType('Adm Jemputan');
    $jemputan = makeBillType('Jemputan');
    $settled = makeOneTimeBill($student, $adm, 50_000, '2026/2027');
    createClassRecapAllocation($settled, 50_000);
    makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);

    $recap = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['segregated'];

    expect($recap['rows'])->toHaveCount(1)
        ->and($recap['rows'][0]['student_name'])->toBe($student->nama_lengkap)
        ->and($recap['rows'][0]['months'])->toHaveKey('2026-07')
        ->and($recap['rows'][0]['jemputan']['target'])->toBe(500_000.0)
        ->and($recap['rows'][0]['adm_jemputan']['target'])->toBe(50_000.0)
        ->and($recap['rows'][0]['bill_count'])->toBe(2)
        ->and($recap['rows'][0]['total_tagihan'])->toBe(550_000.0)
        ->and($recap['jemputan_totals']['target'])->toBe(500_000.0)
        ->and($recap['adm_jemputan_totals']['target'])->toBe(50_000.0)
        ->and($recap['total_tagihan'])->toBe(550_000.0);
});

it('reports Adm Jemputan with settlement status in the combined row', function () {
    [$academicYear, $schoolClass, $paid] = makeClassRecapStudent();
    $unpaid = Student::factory()->create([
        'nama_lengkap' => 'Belum Lunas Adm',
        'class_id' => $schoolClass->id,
    ]);
    enrollClassRecapStudent($unpaid, $academicYear, $schoolClass);
    $adm = makeBillType('Adm Jemputan');
    $jemputan = makeBillType('Jemputan');

    $settled = makeOneTimeBill($paid, $adm, 50_000, '2026/2027');
    createClassRecapAllocation($settled, 50_000);
    makeOneTimeBill($unpaid, $adm, 50_000, '2026/2027');
    makeMonthlyBill($paid, $jemputan, 500_000, 7, 2026);

    $recap = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['segregated'];
    $rows = collect($recap['rows']);
    $settledRow = $rows->firstWhere('student_name', $paid->nama_lengkap);
    $unpaidRow = $rows->firstWhere('student_name', $unpaid->nama_lengkap);

    expect($recap['student_count'])->toBe(2)
        ->and($settledRow['adm_jemputan']['target'])->toBe(50_000.0)
        ->and($settledRow['adm_jemputan']['paid'])->toBe(50_000.0)
        ->and($settledRow['adm_jemputan']['remaining'])->toBe(0.0)
        ->and($settledRow['adm_jemputan']['status'])->toBe(StudentBill::STATUS_PAID)
        ->and($unpaidRow['jemputan'])->toBeNull()
        ->and($unpaidRow['months'])->toBe([])
        ->and($unpaidRow['adm_jemputan']['target'])->toBe(50_000.0)
        ->and($unpaidRow['adm_jemputan']['paid'])->toBe(0.0)
        ->and($unpaidRow['adm_jemputan']['remaining'])->toBe(50_000.0)
        ->and($unpaidRow['adm_jemputan']['status'])->toBe(StudentBill::STATUS_UNPAID)
        ->and($recap['adm_jemputan_totals']['target'])->toBe(100_000.0)
        ->and($recap['adm_jemputan_totals']['remaining'])->toBe(50_000.0)
        ->and($settledRow['months'])->toHaveKey('2026-07')
        ->and(collect($settledRow['months'])->keys()->all())->not->toContain('2026-01');
});

it('keeps Adm Jemputan out of the Jemputan twelve month matrix', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $adm = makeBillType('Adm Jemputan');
    $admBill = makeOneTimeBill($student, $adm, 50_000, '2026/2027');
    $admBill->update(['period_month' => 7, 'period_year' => 2026]);

    $recap = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['segregated'];

    expect($recap['rows'])->toHaveCount(1)
        ->and($recap['rows'][0]['months'])->toBe([])
        ->and($recap['rows'][0]['jemputan'])->toBeNull()
        ->and($recap['rows'][0]['adm_jemputan']['target'])->toBe(50_000.0)
        ->and($recap['month_totals']['2026-07']['target'])->toBe(0.0)
        ->and($recap['adm_jemputan_totals']['target'])->toBe(50_000.0);
});

it('reports an empty combined table instead of hiding the recap page', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    makeBillType('Jemputan');
    makeBillType('Adm Jemputan');

    $report = app(StudentClassPaymentRecapService::class)->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id);
    $recap = $report['segregated'];

    expect($recap['rows'])->toBe([])
        ->and($recap['student_count'])->toBe(0)
        ->and($recap['jemputan_totals'])->toBe(['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0])
        ->and($recap['adm_jemputan_totals'])->toBe(['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0])
        ->and($report['student_count'])->toBe(1)
        ->and(array_column($report['payment_types']['monthly'], 'id'))->toBe([$spp->id]);
});

it('renders one Rekap Jemputan table that mirrors the main recap structure', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $onlyAdm = Student::factory()->create([
        'nama_lengkap' => 'Siswa Adm Saja',
        'class_id' => $schoolClass->id,
    ]);
    $onlyJemputan = Student::factory()->create([
        'nama_lengkap' => 'Siswa Jemputan Saja',
        'class_id' => $schoolClass->id,
    ]);
    enrollClassRecapStudent($onlyAdm, $academicYear, $schoolClass);
    enrollClassRecapStudent($onlyJemputan, $academicYear, $schoolClass);
    $spp = makeBillType('SPP');
    $jemputan = makeBillType('Jemputan');
    $adm = makeBillType('Adm Jemputan');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    $jemputanBill = makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);
    createClassRecapAllocation($jemputanBill, 250_000);
    $admBill = makeOneTimeBill($student, $adm, 50_000, '2026/2027');
    createClassRecapAllocation($admBill, 50_000);
    makeOneTimeBill($onlyAdm, $adm, 50_000, '2026/2027');
    createClassRecapAllocation(makeMonthlyBill($onlyJemputan, $jemputan, 500_000, 8, 2026), 500_000);

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        // One table, and the separate Adm Jemputan table is gone.
        ->assertDontSee('adm-jemputan-recap-table')
        ->assertDontSee('Belum ada tagihan Adm Jemputan untuk kelas ini.')
        // Grouped header is two levels only: the three groups, then their subcolumns.
        ->assertSee('>Ringkasan Tagihan</th>', false)
        ->assertSee('>Bulanan</th>', false)
        ->assertSee('>Sekali Bayar</th>', false)
        ->assertSee('colspan="3"', false)
        ->assertSee('colspan="12"', false)
        // Adm Jemputan settlement stays in the Sekali Bayar column.
        ->assertSee('Lunas')
        ->assertSee('Belum Lunas')
        ->assertSee('Siswa Adm Saja')
        ->assertSee('Siswa Jemputan Saja')
        // Main recap is untouched.
        ->assertSee('SPP')
        ->html();

    $months = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $schoolClass->id)['months'];

    $mainRecap = substr($html, 0, strpos($html, '>Rekap Jemputan</h2>'));
    $jemputanRecap = substr($html, strpos($html, '>Rekap Jemputan</h2>'));
    $offsets = [];

    preg_match_all('/<thead\b[^>]*>.*?<\/thead>/s', $jemputanRecap, $theads);
    $headerRows = array_map(fn (string $thead): int => substr_count($thead, '<tr'), $theads[0]);

    foreach ($months as $month) {
        $label = $month['label'].' '.$month['year'];

        expect($html)->toContain($label);
        $offsets[] = strpos($html, $label);

        // One header row per month label, mirrored across the clone and the measure thead.
        expect(substr_count($jemputanRecap, $label))->toBe(2);
    }

    // Jul 2026 through Jun 2027 appear in that document order.
    expect($offsets)->toBe(collect($offsets)->sort()->values()->all())
        // Exactly two header levels in the visible clone thead and the collapsed measure thead.
        ->and($headerRows)->toBe([2, 2])
        ->and($jemputanRecap)->toContain('>Jemputan</th>')
        ->and($jemputanRecap)->toContain('>Total</th>')
        ->and($jemputanRecap)->not->toContain('>Tagihan</th>')
        ->and($jemputanRecap)->not->toContain('>Terbayar</th>')
        ->and($jemputanRecap)->not->toContain('rowspan="3"')
        // No. and Nama Siswa span both header levels; Adm Jemputan has two columns of its own.
        ->and(substr_count($jemputanRecap, 'rowspan="2"'))->toBe(4)
        ->and(substr_count($jemputanRecap, '>Jemputan</th>'))->toBe(2)
        ->and(substr_count($jemputanRecap, '>Adm Jemputan</th>'))->toBe(4)
        // Exactly two recap tables on the page: the main one plus the combined Jemputan one.
        ->and(substr_count($html, 'JUMLAH'))->toBe(2)
        // The main recap header stays free of Jemputan and Adm Jemputan columns.
        ->and($mainRecap)->toContain('>SPP</th>')
        ->and($mainRecap)->not->toContain('>Jemputan</th>')
        ->and($mainRecap)->not->toContain('>Adm Jemputan</th>');
});

it('reuses the main recap sticky header and scroll contract for the Jemputan table', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    $adm = makeBillType('Adm Jemputan');
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);
    makeOneTimeBill($student, $adm, 50_000, '2026/2027');

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        // Same Alpine helper and the same measure/clone/scroller wiring as the main table.
        ->assertSee('x-data="classRecapStickyHeader()"', false)
        ->assertSee('class="recap-clone-table', false)
        ->assertSee('class="recap-real-table', false)
        ->assertSee('recap-measure-head invisible', false)
        ->assertSee('x-ref="recapScroller"', false)
        ->assertSee('x-ref="cloneScroller"', false)
        ->assertSee('x-ref="recapTable"', false)
        ->assertSee('sticky top-16 z-30 bg-surface-container-low', false)
        ->assertSee('overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-b-xl', false)
        // No and Nama Siswa reuse the main recap sticky contract byte for byte.
        ->assertSee('sticky left-0 z-30 w-14 min-w-14', false)
        ->assertSee('sticky left-14 z-30 w-56 min-w-56', false)
        ->assertSee('sticky left-0 z-10 border-r border-outline-variant bg-surface-container-lowest px-3 py-3 text-center font-numeric-data', false)
        ->assertSee('sticky left-14 z-10 border-r-2 border-outline bg-surface-container-lowest px-4 py-3 font-medium', false)
        ->html();

    // The body name cell must not carry a width/nowrap override the main table does not have.
    expect($html)->not->toContain('sticky left-14 z-10 w-56 min-w-56 whitespace-nowrap')
        ->and(substr_count($html, 'classRecapStickyHeader()'))->toBe(2)
        ->and(substr_count($html, 'recap-measure-head'))->toBe(2)
        ->and(substr_count($html, 'x-ref="recapScroller"'))->toBe(2);
});

it('centers headers, amounts and footer totals while keeping student names left aligned', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    $jemputan = makeBillType('Jemputan');
    $adm = makeBillType('Adm Jemputan');
    $book = makeBillType('Uang Buku');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly, $schoolClass->level);
    createClassRecapAllocation(makeMonthlyBill($student, $spp, 970_000, 7, 2026), 970_000);
    createClassRecapAllocation(makeMonthlyBill($student, $jemputan, 500_000, 7, 2026), 250_000);
    createClassRecapAllocation(makeOneTimeBill($student, $adm, 50_000, '2026/2027'), 50_000);
    makeOneTimeBill($student, $book, 150_000, '2026/2027');

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        ->html();

    $tables = [];

    // The two real recap tables carry every body, header and footer cell.
    preg_match_all('/<table class="recap-real-table.*?<\/table>/s', $html, $matches);

    foreach ($matches[0] as $table) {
        $tables[str_contains($table, '>Jemputan</th>') ? 'Jemputan recap' : 'main recap'] = $table;
    }

    expect($tables)->toHaveKeys(['main recap', 'Jemputan recap']);

    foreach ($tables as $label => $table) {
        preg_match_all('/<(th|td)\b[^>]*>.*?<\/\1>/is', $table, $cells);
        $numericCells = array_values(array_filter(
            $cells[0],
            fn (string $cell): bool => str_contains($cell, 'font-numeric-data')
        ));
        $nameCells = array_values(array_filter(
            $cells[0],
            fn (string $cell): bool => str_contains($cell, 'Nama Siswa') || str_contains($cell, $student->nama_lengkap)
        ));

        expect($numericCells)->not->toBeEmpty()
            // Every amount cell, body and footer alike, is centered.
            ->and(collect($numericCells)->every(fn (string $cell): bool => str_contains($cell, 'text-center')))->toBeTrue()
            // No recap cell is right aligned any more.
            ->and(collect($cells[0])->filter(fn (string $cell): bool => str_contains($cell, 'text-right')))->toBeEmpty()
            // The name cells carry no alignment override and inherit the table's left alignment.
            ->and($nameCells)->not->toBeEmpty()
            ->and(collect($nameCells)->every(fn (string $cell): bool => ! str_contains($cell, 'text-center')))->toBeTrue()
            ->and(collect($nameCells)->every(fn (string $cell): bool => ! str_contains($cell, 'text-right')))->toBeTrue()
            ->and($table)->toContain('border-collapse text-left text-body-sm')
            // The grid draws a strong boundary at the major groups and a light line per column.
            ->and(substr_count($table, 'border-r-2 border-outline'))->toBeGreaterThan(0)
            ->and(substr_count($table, 'border-r border-outline-variant'))->toBeGreaterThan(0)
            ->and($label)->toBeString();

        // Every row separates all of its columns and stops cleanly at the table edge.
        preg_match_all('/<tr\b.*?<\/tr>/s', $table, $rows);
        $bodyRows = array_values(array_filter(
            $rows[0],
            fn (string $row): bool => str_contains($row, '<td')
        ));

        expect($bodyRows)->not->toBeEmpty()
            ->and(collect($bodyRows)->every(function (string $row): bool {
                preg_match_all('/<td\b[^>]*>/i', $row, $cells);

                // The trailing column must not hang a border off the right edge, and the
                // sticky Nama boundary must always read as a strong group separator.
                return ! str_contains(end($cells[0]), 'border-r')
                    && str_contains($row, 'border-r-2 border-outline');
            }))->toBeTrue();
    }

    // The JUMLAH label is the only recap footer cell left aligned.
    preg_match_all('/<tfoot>.*?<\/tfoot>/s', $html, $foots);

    expect($foots[0])->toHaveCount(2);

    foreach ($foots[0] as $foot) {
        $cells = array_values(array_filter(
            explode('<td', $foot),
            fn (string $chunk): bool => str_contains($chunk, 'font-numeric-data') || str_contains($chunk, 'JUMLAH')
        ));

        expect($cells)->not->toBeEmpty()
            ->and($cells[0])->toContain('JUMLAH')
            ->and($cells[0])->not->toContain('text-center')
            ->and(collect(array_slice($cells, 1))->every(fn (string $cell): bool => str_contains($cell, 'text-center')))->toBeTrue();
    }
});

it('drops the extra inner bordered box and keeps the sticky band out of an overflow ancestor', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $jemputan = makeBillType('Jemputan');
    makeBillType('Adm Jemputan');
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        // The old nested box is gone and the section now matches the main recap shell.
        ->assertDontSee('mt-5 overflow-hidden rounded-xl border border-outline-variant', false)
        ->assertSee('border-b border-outline-variant px-5 py-4 sm:flex-row', false)
        ->assertSee('text-title-md font-title-md text-on-surface">Rekap Jemputan</h2>', false)
        ->html();

    $sectionStart = strpos($html, '>Rekap Jemputan</h2>');
    $stickyBand = strpos($html, 'sticky top-16 z-30', $sectionStart);

    expect($sectionStart)->toBeInt()
        ->and($stickyBand)->toBeInt();

    // Nothing between the Jemputan section and its sticky band may clip the scrollport.
    $beforeStickyBand = substr($html, $sectionStart, $stickyBand - $sectionStart);

    expect($beforeStickyBand)->not->toContain('overflow-hidden')
        ->and($beforeStickyBand)->not->toContain('overflow-x-auto');
});

it('renders one body cell per logical column without colspan empties', function () {
    [$academicYear, $schoolClass, $student] = makeClassRecapStudent();
    $onlyAdm = Student::factory()->create([
        'nama_lengkap' => 'Siswa Adm Saja',
        'class_id' => $schoolClass->id,
    ]);
    enrollClassRecapStudent($onlyAdm, $academicYear, $schoolClass);
    $jemputan = makeBillType('Jemputan');
    $adm = makeBillType('Adm Jemputan');
    configureClassRecapType($jemputan, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    configureClassRecapType($adm, SchoolLevel::SMP, BillFrequency::OneTime, $schoolClass->level);
    $both = makeMonthlyBill($student, $jemputan, 500_000, 7, 2026);
    createClassRecapAllocation($both, 100_000);
    $admBill = makeOneTimeBill($student, $adm, 50_000, '2026/2027');
    createClassRecapAllocation($admBill, 50_000);
    makeOneTimeBill($onlyAdm, $adm, 50_000, '2026/2027');

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        ->html();

    $sectionStart = strpos($html, '>Rekap Jemputan</h2>');
    $tbodyStart = strpos($html, '<tbody', $sectionStart);
    $tbodyEnd = strpos($html, '</tbody>', $tbodyStart);
    $tbody = substr($html, $tbodyStart, $tbodyEnd - $tbodyStart);

    // No + Nama + 3 summary + 12 monthly + 1 one-time = 18 logical columns.
    expect(substr_count($tbody, '<tr '))->toBe(2)
        ->and(substr_count($tbody, '<td'))->toBe(36)
        ->and($tbody)->not->toContain('colspan');

    $tfootStart = strpos($html, '<tfoot>', $sectionStart);
    $tfootEnd = strpos($html, '</tfoot>', $tfootStart);
    $tfoot = substr($html, $tfootStart, $tfootEnd - $tfootStart);

    // One footer row: colspan="2" label plus 16 aligned total cells.
    expect(substr_count($tfoot, '<tr '))->toBe(1)
        ->and(substr_count($tfoot, '<td'))->toBe(17)
        ->and(substr_count($tfoot, 'colspan="2"'))->toBe(1)
        ->and($tfoot)->toContain('JUMLAH');
});
it('renders a single empty state when the combined table has no Jemputan nor Adm Jemputan data', function () {
    [$academicYear, $schoolClass] = makeClassRecapStudent();
    $spp = makeBillType('SPP');
    configureClassRecapType($spp, SchoolLevel::SMP, BillFrequency::Monthly, $schoolClass->level);
    makeBillType('Jemputan');
    makeBillType('Adm Jemputan');

    $html = Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $schoolClass->id)
        ->set('classRecapAcademicYearId', (string) $academicYear->id)
        ->assertSee('Rekap Jemputan')
        ->assertSee('Belum ada tagihan Jemputan maupun Adm Jemputan untuk kelas ini.')
        ->assertDontSee('Belum ada tagihan Adm Jemputan untuk kelas ini.')
        ->assertSee('SPP')
        ->html();

    expect(substr_count($html, 'Belum ada tagihan Jemputan maupun Adm Jemputan untuk kelas ini.'))->toBe(1);
});
