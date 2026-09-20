<?php

use App\Enums\SchoolLevel;
use App\Livewire\Dashboard;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function createDashboardFilterStudentPayment(
    Student $student,
    Bank $bank,
    User $user,
    int $amount,
    string $paymentDate,
    string $recordedAt,
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-DASH-FILTER-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    return $payment;
}

function createDashboardFilterDaycarePayment(
    DaycareChild $child,
    Bank $bank,
    User $user,
    int $amount,
    string $paymentDate,
    string $recordedAt,
): DaycarePayment {
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    return $payment;
}

function allocateDashboardFilterBill(
    StudentBill $bill,
    Bank $bank,
    User $user,
    int $amount,
    string $paymentDate,
    string $recordedAt,
): void {
    $payment = createDashboardFilterStudentPayment(
        $bill->student,
        $bank,
        $user,
        $amount,
        $paymentDate,
        $recordedAt,
    );

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'Asia/Jakarta'));
});

it('filters combined operational metrics and bank recap by unit', function () {
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $daycareChild = DaycareChild::factory()->create(['is_active' => true]);
    $sharedBank = Bank::factory()->create(['name' => 'BSI Bersama', 'is_active' => true]);
    $sdBank = Bank::factory()->create(['name' => 'Mandiri SD', 'is_active' => true]);
    $user = User::factory()->create();

    createDashboardFilterStudentPayment($smpStudent, $sharedBank, $user, 100_000, '2026-09-15', '2026-09-15 08:00:00');
    createDashboardFilterStudentPayment($sdStudent, $sdBank, $user, 200_000, '2026-09-15', '2026-09-15 09:00:00');
    createDashboardFilterDaycarePayment($daycareChild, $sharedBank, $user, 300_000, '2026-09-15', '2026-09-15 10:00:00');

    $all = Livewire::test(Dashboard::class)
        ->assertSet('operationalUnit', 'all')
        ->assertSet('operationalPeriod', Dashboard::PERIOD_ALL)
        ->assertSee('UNIT')
        ->assertSee('PERIODE')
        ->assertSee('Semua Waktu')
        ->assertDontSee('TANGGAL MULAI')
        ->assertViewHas('totalPemasukan', 600_000.0)
        ->assertViewHas('totalTransaksi', 3);

    expect($all->viewData('bankTotals')->get($sharedBank->id))->toMatchArray([
        'student_total' => 100_000.0,
        'daycare_total' => 300_000.0,
        'combined_total' => 400_000.0,
    ]);

    $smp = $all->set('operationalUnit', SchoolLevel::SMP->value)
        ->assertViewHas('totalPemasukan', 100_000.0)
        ->assertViewHas('totalTransaksi', 1);

    expect($smp->viewData('bankTotals')->get($sharedBank->id)['combined_total'])->toBe(100_000.0)
        ->and($smp->viewData('bankTotals')->get($sdBank->id)['combined_total'])->toBe(0.0);

    $daycare = $smp->set('operationalUnit', 'daycare')
        ->assertViewHas('totalPemasukan', 300_000.0)
        ->assertViewHas('totalTransaksi', 1)
        ->assertSee('Total Anak Daycare');

    expect($daycare->viewData('bankTotals')->get($sharedBank->id))->toMatchArray([
        'student_total' => 0.0,
        'daycare_total' => 300_000.0,
        'combined_total' => 300_000.0,
    ]);
});

it('uses Student recorded_at and Daycare payment_date for operational presets', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();

    createDashboardFilterStudentPayment($student, $bank, $user, 100_000, '2026-08-01', '2026-09-15 08:00:00');
    createDashboardFilterStudentPayment($student, $bank, $user, 200_000, '2026-09-15', '2026-09-14 08:00:00');
    createDashboardFilterStudentPayment($student, $bank, $user, 400_000, '2026-09-01', '2026-09-01 08:00:00');
    createDashboardFilterStudentPayment($student, $bank, $user, 600_000, '2025-03-01', '2025-03-01 08:00:00');
    createDashboardFilterDaycarePayment($child, $bank, $user, 300_000, '2026-09-15', '2026-08-01 08:00:00');
    createDashboardFilterDaycarePayment($child, $bank, $user, 500_000, '2026-09-14', '2026-09-15 08:00:00');
    createDashboardFilterDaycarePayment($child, $bank, $user, 700_000, '2025-03-01', '2026-09-15 08:00:00');

    $component = Livewire::test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 2_800_000.0)
        ->assertViewHas('totalTransaksi', 7)
        ->set('operationalPeriod', Dashboard::PERIOD_TODAY)
        ->assertViewHas('totalPemasukan', 400_000.0)
        ->assertViewHas('totalTransaksi', 2)
        ->set('operationalPeriod', Dashboard::PERIOD_YESTERDAY)
        ->assertViewHas('totalPemasukan', 700_000.0)
        ->assertViewHas('totalTransaksi', 2)
        ->set('operationalPeriod', Dashboard::PERIOD_CURRENT_MONTH)
        ->assertViewHas('totalPemasukan', 1_500_000.0)
        ->assertViewHas('totalTransaksi', 5)
        ->set('operationalPeriod', Dashboard::PERIOD_CUSTOM)
        ->assertSee('TANGGAL MULAI')
        ->assertSee('TANGGAL AKHIR')
        ->set('operationalStartDate', '2026-09-01')
        ->set('operationalEndDate', '2026-09-14')
        ->assertViewHas('totalPemasukan', 1_100_000.0)
        ->assertViewHas('totalTransaksi', 3);

    $component
        ->set('operationalStartDate', '2026-09-20')
        ->assertHasErrors('operationalEndDate');
});

it('excludes cancelled Student payments and deleted Daycare payments', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();

    createDashboardFilterStudentPayment($student, $bank, $user, 100_000, '2026-09-15', '2026-09-15 08:00:00');
    createDashboardFilterStudentPayment(
        $student,
        $bank,
        $user,
        200_000,
        '2026-09-15',
        '2026-09-15 09:00:00',
        Payment::STATUS_CANCELLED,
    );
    $deleted = createDashboardFilterDaycarePayment($child, $bank, $user, 300_000, '2026-09-15', '2026-09-15 10:00:00');
    $deleted->delete();

    Livewire::test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 100_000.0)
        ->assertViewHas('totalTransaksi', 1);
});

it('keeps same-name bank ids separate and classifies cash only by banks type', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $child = DaycareChild::factory()->create();
    $firstBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111', 'is_active' => true]);
    $secondBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222', 'is_active' => true]);
    $cashNamedMandiri = Bank::factory()->cash()->create(['name' => 'Mandiri', 'is_active' => true]);
    $user = User::factory()->create();

    createDashboardFilterStudentPayment($student, $firstBsi, $user, 100_000, '2026-09-15', '2026-09-15 08:00:00');
    createDashboardFilterDaycarePayment($child, $secondBsi, $user, 200_000, '2026-09-15', '2026-09-15 09:00:00');
    createDashboardFilterStudentPayment($student, $cashNamedMandiri, $user, 300_000, '2026-09-15', '2026-09-15 10:00:00');

    $component = Livewire::test(Dashboard::class)->assertViewHas('totalPemasukan', 600_000.0);
    $totals = $component->viewData('bankTotals');

    expect($totals->get($firstBsi->id)['combined_total'])->toBe(100_000.0)
        ->and($totals->get($secondBsi->id)['combined_total'])->toBe(200_000.0)
        ->and($totals->get($cashNamedMandiri->id)['combined_total'])->toBe(300_000.0)
        ->and($cashNamedMandiri->type)->toBe(Bank::TYPE_CASH);
});

it('classifies Student operational receipts using historical enrollment at payment_date', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $pastYear = AcademicYear::query()->updateOrCreate(['year' => '2025/2026'], [
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $currentYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 8]);
    $smaClass = SchoolClass::factory()->create(['level' => 11]);
    $student = Student::factory()->create(['class_id' => $smaClass->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $pastYear->id,
        'school_class_id' => $smpClass->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $currentYear->id,
        'school_class_id' => $smaClass->id,
        'status' => 'active',
    ]);
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    createDashboardFilterStudentPayment($student, $bank, $user, 450_000, '2026-06-20', '2026-09-15 08:00:00');

    Livewire::test(Dashboard::class)
        ->set('operationalUnit', SchoolLevel::SMP->value)
        ->assertViewHas('totalPemasukan', 450_000.0)
        ->set('operationalUnit', SchoolLevel::SMA->value)
        ->assertViewHas('totalPemasukan', 0.0);
});

it('maps KB students into the TK operational unit', function () {
    $academicYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $kbClass = SchoolClass::factory()->create(['level' => -3]);
    $student = Student::factory()->create(['class_id' => $kbClass->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $kbClass->id,
        'status' => 'active',
    ]);
    $bank = Bank::factory()->create(['is_active' => true]);
    createDashboardFilterStudentPayment(
        $student,
        $bank,
        User::factory()->create(),
        175_000,
        '2026-09-15',
        '2026-09-15 08:00:00',
    );

    Livewire::test(Dashboard::class)
        ->set('operationalUnit', SchoolLevel::TK->value)
        ->assertViewHas('totalPemasukan', 175_000.0)
        ->assertViewHas('totalSiswaAktif', 1);
});

it('shows active Student and Daycare populations separately and ignores date filters', function () {
    [$activeSmp] = makeEnrolledStudent(SchoolLevel::SMP);
    [$inactiveSmp] = makeEnrolledStudent(SchoolLevel::SMP);
    [$activeSd] = makeEnrolledStudent(SchoolLevel::SD);
    $inactiveSmp->enrollments()->update(['status' => 'lulus']);
    DaycareChild::factory()->create(['is_active' => true]);
    DaycareChild::factory()->inactive()->create();

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Total Peserta Aktif')
        ->assertViewHas('totalSiswaAktif', 2)
        ->assertViewHas('activeDaycareChildren', 1)
        ->set('operationalPeriod', Dashboard::PERIOD_YESTERDAY)
        ->assertViewHas('totalSiswaAktif', 2)
        ->assertViewHas('activeDaycareChildren', 1)
        ->set('operationalUnit', SchoolLevel::SMP->value)
        ->assertSee('Total Siswa Aktif')
        ->assertViewHas('totalSiswaAktif', 1)
        ->assertViewHas('activeDaycareChildren', 0)
        ->set('operationalUnit', 'daycare')
        ->assertSee('Total Anak Daycare')
        ->assertViewHas('totalSiswaAktif', 0)
        ->assertViewHas('activeDaycareChildren', 1);

    expect($activeSmp->exists)->toBeTrue()->and($activeSd->exists)->toBeTrue();
});

it('keeps Capaian unfiltered and aligned with the canonical current WIB month', function () {
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $type = makeBillType('SPP Filter Dashboard');
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    $daycareChild = DaycareChild::factory()->create();

    $smpSeptember = makeMonthlyBill($smpStudent, $type, 500_000, 9, 2026);
    makeMonthlyBill($sdStudent, $type, 300_000, 9, 2026);
    makeMonthlyBill($smpStudent, $type, 700_000, 10, 2026);
    allocateDashboardFilterBill($smpSeptember, $bank, $user, 200_000, '2026-10-05', '2026-10-05 08:00:00');
    createDashboardFilterDaycarePayment($daycareChild, $bank, $user, 900_000, '2026-09-15', '2026-09-15 08:00:00');

    $service = app(StudentTargetArrearsReportService::class);
    $allCanonical = $service->generateMonthlySummary(9, 2026);
    $component = Livewire::test(Dashboard::class)
        ->assertSee('Capaian Tagihan Bulan Ini')
        ->assertSee('September 2026')
        ->assertDontSeeHtml('wire:model.live="targetUnit"')
        ->assertDontSeeHtml('wire:model.live="targetPeriod"')
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals'] === $allCanonical['totals']);

    expect($allCanonical['totals'])->toMatchArray([
        'target' => 800_000.0,
        'paid' => 200_000.0,
        'outstanding' => 600_000.0,
        'achievement_percentage' => 25.0,
    ])->and($component->viewData('targetArrearsUrl'))->toBe(route('laporan.index', [
        'tab' => 'target',
        'target_mode' => 'monthly',
        'target_month' => 9,
        'target_year' => 2026,
        'jenjang' => 'all',
    ]));
});

it('renders without the redundant tampilkan button while filters stay reactive', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $bank = Bank::factory()->create(['is_active' => true]);
    createDashboardFilterStudentPayment($student, $bank, User::factory()->create(), 100_000, '2026-09-15', '2026-09-15 08:00:00');

    $component = Livewire::test(Dashboard::class)
        ->assertDontSee('Tampilkan')
        ->assertDontSeeHtml('wire:click="$refresh"')
        ->assertDontSeeHtml('lg:w-32');

    $component->set('operationalUnit', SchoolLevel::SMP->value)
        ->assertViewHas('totalPemasukan', 100_000.0)
        ->set('operationalPeriod', Dashboard::PERIOD_YESTERDAY)
        ->assertViewHas('totalPemasukan', 0.0);
});

it('keeps inactive banks with filtered receipts visible so income reconciles to bank cards', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $inactiveBank = Bank::factory()->create(['is_active' => false]);
    $activeZeroBank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    createDashboardFilterStudentPayment($student, $inactiveBank, $user, 125_000, '2026-09-15', '2026-09-15 08:00:00');

    $component = Livewire::test(Dashboard::class);
    $bankTotals = $component->viewData('bankTotals');

    expect((float) $component->viewData('totalPemasukan'))->toBe(125_000.0)
        ->and((float) $bankTotals->sum('combined_total'))->toBe(125_000.0)
        ->and($bankTotals->get($inactiveBank->id)['combined_total'])->toBe(125_000.0)
        ->and($bankTotals->get($activeZeroBank->id)['combined_total'])->toBe(0.0);
});
