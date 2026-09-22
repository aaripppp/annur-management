<?php

use App\Enums\BillFrequency;
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

function configureClassRecapType(PaymentType $type, SchoolLevel $level, BillFrequency|string $frequency = BillFrequency::Monthly): void
{
    makeLevelDefault($type, $level);
    makeBillRate($type, 7, 1, ['billing_frequency' => $frequency]);
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

it('adds Rekap Per Kelas to existing report navigation without export or report mode tabs', function () {
    [$academicYear] = makeClassRecapStudent();

    Livewire::test(SchoolDailyReport::class)
        ->call('setActiveTab', 'class')
        ->assertSet('classRecapAcademicYearId', (string) $academicYear->id)
        ->assertSee('Rekap Per Kelas')
        ->assertSee('Tahun Ajaran')
        ->assertSee('Jenjang')
        ->assertSee('Kelas')
        ->assertSee('Pilih jenjang dan kelas untuk menampilkan rekap.')
        ->assertDontSee('Unduh Excel')
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
        ->and(substr_count($html, 'class="border-b border-outline-variant bg-surface-container-low text-label-sm"'))->toBe(2);
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
    configureClassRecapType($book, SchoolLevel::SMP, BillFrequency::Yearly);
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
    configureClassRecapType($pangkal, SchoolLevel::SMP, BillFrequency::OneTime);
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
    configureClassRecapType($spp, SchoolLevel::SD);
    configureClassRecapType($osis, SchoolLevel::SMP);
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
