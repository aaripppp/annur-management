<?php

use App\Enums\SchoolLevel;
use App\Livewire\Dashboard;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function createDashboardTargetAllocation(
    StudentBill $bill,
    User $user,
    Bank $bank,
    int $amount,
    string $paymentDate,
    string $recordedAt,
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-DASH-TARGET-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

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

it('shows the canonical current WIB monthly target summary without other frequencies or periods', function () {
    $this->travelTo('2026-09-04 00:30:00');
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $spp = makeBillType('SPP Dashboard Dinamis');
    $activity = makeBillType('Ekskul Dashboard Dinamis');
    $yearlyType = makeBillType('Tahunan Dashboard');
    $oneTimeType = makeBillType('Sekali Dashboard');

    $septemberSpp = makeMonthlyBill($student, $spp, 500_000, 9, 2026);
    $septemberActivity = makeMonthlyBill($student, $activity, 300_000, 9, 2026);
    $augustBill = makeMonthlyBill($student, $spp, 400_000, 8, 2026);
    makeYearlyBill($student, $yearlyType, 1_000_000);
    makeOneTimeBill($student, $oneTimeType, 2_000_000);

    createDashboardTargetAllocation($augustBill, $user, $bank, 400_000, '2026-09-02', '2026-09-02 09:00:00');
    createDashboardTargetAllocation($septemberSpp, $user, $bank, 200_000, '2026-10-03', '2026-10-03 09:00:00');
    createDashboardTargetAllocation(
        $septemberActivity,
        $user,
        $bank,
        100_000,
        '2026-09-03',
        '2026-09-03 09:00:00',
        Payment::STATUS_CANCELLED,
    );

    $canonical = app(StudentTargetArrearsReportService::class)->generateMonthlySummary(9, 2026);
    $component = Livewire::test(Dashboard::class)
        ->assertSee('Capaian Tagihan Bulan Ini')
        ->assertDontSee('Unit Siswa')
        ->assertSee('September 2026')
        ->assertSee('Rp 800.000')
        ->assertSee('Rp 200.000')
        ->assertSee('Rp 600.000')
        ->assertSee('25%')
        ->assertSee('Lihat Target & Tunggakan')
        ->assertDontSee('Tahunan Dashboard')
        ->assertDontSee('Sekali Dashboard');
    $dashboardSummary = $component->viewData('targetArrearsSummary');

    expect($dashboardSummary['totals'])->toBe($canonical['totals'])
        ->and($dashboardSummary['totals']['target'])->toBe(800_000.0)
        ->and($dashboardSummary['totals']['paid'])->toBe(200_000.0)
        ->and($dashboardSummary['totals']['outstanding'])->toBe(600_000.0)
        ->and($dashboardSummary['totals']['achievement_percentage'])->toBe(25.0)
        ->and(collect($dashboardSummary['rows'])->pluck('payment_type_name')->all())
        ->toBe(['Ekskul Dashboard Dinamis', 'SPP Dashboard Dinamis'])
        ->and(collect($dashboardSummary['rows'])->every(fn (array $row): bool => $row['details'] === []))->toBeTrue()
        ->and($component->viewData('targetArrearsUrl'))->toBe(route('laporan.index', [
            'tab' => 'target',
            'target_mode' => 'monthly',
            'target_month' => 9,
            'target_year' => 2026,
            'jenjang' => 'all',
        ]));
});

it('renders a clear current-month empty state and excludes daycare activity', function () {
    $this->travelTo('2026-09-04 00:30:00');
    $user = User::factory()->create();
    $bank = Bank::factory()->cash()->create(['is_active' => true]);
    $daycarePayment = DaycarePayment::factory()->create([
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-04',
        'total_amount' => 900_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycarePayment->id,
        'description' => 'Daycare September',
        'amount' => 900_000,
    ]);

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Belum ada tagihan bulanan untuk September 2026.')
        ->assertSee('Lihat Target & Tunggakan');
    $summary = $component->viewData('targetArrearsSummary');

    expect($summary['bill_count'])->toBe(0)
        ->and($summary['rows'])->toBe([])
        ->and($summary['totals']['target'])->toBe(0)
        ->and($summary['totals']['paid'])->toBe(0)
        ->and($summary['totals']['outstanding'])->toBe(0);
});

it('resolves the dashboard billing month using the configured WIB timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 17:30:00 UTC'));
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP Batas WIB');
    makeMonthlyBill($student, $type, 450_000, 9, 2026);

    $component = Livewire::test(Dashboard::class)
        ->assertSee('September 2026')
        ->assertSee('Rp 450.000');

    expect($component->viewData('targetArrearsSummary')['month'])->toBe(9)
        ->and($component->viewData('targetArrearsSummary')['year'])->toBe(2026);
});

it('defaults the target filter to the current WIB month, year, and all jenjang', function () {
    $this->travelTo('2026-09-04 00:30:00');

    Livewire::test(Dashboard::class)
        ->assertSet('targetMonth', 9)
        ->assertSet('targetYear', 2026)
        ->assertSet('targetJenjang', 'all')
        ->assertSee('Capaian Tagihan Bulan Ini')
        ->assertSeeHtml('wire:model.live="targetMonth"')
        ->assertSeeHtml('wire:model.live="targetJenjang"');
});

it('normalizes invalid target filter values on mount', function () {
    $this->travelTo('2026-09-04 00:30:00');

    Livewire::withQueryParams(['target_bulan' => 99, 'target_tahun' => 9999, 'target_jenjang' => 'TK'])
        ->test(Dashboard::class)
        ->assertSet('targetMonth', 9)
        ->assertSet('targetYear', 2026)
        ->assertSet('targetJenjang', 'TK');
});

it('filters the target summary card by selected month and updates the card title', function () {
    $this->travelTo('2026-09-04 00:30:00');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP Filter Bulan');
    makeMonthlyBill($student, $type, 500_000, 9, 2026);
    makeMonthlyBill($student, $type, 700_000, 10, 2026);

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Capaian Tagihan Bulan Ini')
        ->assertSee('September 2026')
        ->assertSee('Rp 500.000')
        ->set('targetMonth', 10)
        ->assertSee('Capaian Tagihan Oktober 2026')
        ->assertSee('Oktober 2026')
        ->assertSee('Rp 700.000')
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals']['target'] === 700_000.0)
        ->assertViewHas('targetArrearsUrl', fn (string $url): bool => str_contains($url, 'target_month=10'));

    expect($component->get('targetMonth'))->toBe(10)
        ->and($component->viewData('targetArrearsSummary')['year'])->toBe(2026);
});

it('scopes the target summary card by jenjang without affecting operational totals', function () {
    $this->travelTo('2026-09-15 12:00:00');
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    $smpType = makeBillType('SPP SMP Target');
    $sdType = makeBillType('SPP SD Target');
    $smpBill = makeMonthlyBill($smpStudent, $smpType, 400_000, 9, 2026);
    $sdBill = makeMonthlyBill($sdStudent, $sdType, 250_000, 9, 2026);
    createDashboardTargetAllocation($smpBill, $user, $bank, 100_000, '2026-09-15', '2026-09-15 08:00:00');
    createDashboardTargetAllocation($sdBill, $user, $bank, 50_000, '2026-09-15', '2026-09-15 09:00:00');

    $component = Livewire::test(Dashboard::class)
        ->assertViewHas('totalTransaksi', 2)
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals']['target'] === 650_000.0);

    $smpOnly = $component->set('targetJenjang', SchoolLevel::SMP->value)
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals']['target'] === 400_000.0)
        ->assertViewHas('totalTransaksi', 2)
        ->assertViewHas('totalPemasukan', 150_000.0)
        ->assertSeeHtml('value="SMP"');

    expect(collect($smpOnly->viewData('targetArrearsSummary')['rows'])->pluck('payment_type_name')->all())
        ->toBe(['SPP SMP Target']);

    $all = $smpOnly->set('targetJenjang', 'all')
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals']['target'] === 650_000.0);

    expect(collect($all->viewData('targetArrearsSummary')['rows'])->pluck('payment_type_name')->all())
        ->toBe(['SPP SD Target', 'SPP SMP Target']);
});

it('links the target report with the selected month, year, and jenjang', function () {
    $this->travelTo('2026-09-04 00:30:00');

    $component = Livewire::test(Dashboard::class)
        ->set('targetMonth', 10)
        ->set('targetYear', 2026)
        ->set('targetJenjang', SchoolLevel::SD->value);

    expect($component->viewData('targetArrearsUrl'))->toBe(route('laporan.index', [
        'tab' => 'target',
        'target_mode' => 'monthly',
        'target_month' => 10,
        'target_year' => 2026,
        'jenjang' => 'SD',
    ]));
});

it('shows yearly achievement from the canonical summary with historical level and active allocations', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['class_id' => SchoolClass::factory()->create(['level' => 10])->id]);
    $type = makeBillType('Tahunan Dashboard Lengkap');
    $bill = makeYearlyBill($student, $type, 1_000_000, '2026/2027');
    makeYearlyBill($student, $type, 2_000_000, '2025/2026');

    BillAdjustment::query()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -100_000,
        'reason' => 'Diskon tahunan Dashboard',
        'created_by' => $user->id,
    ]);
    createDashboardTargetAllocation($bill, $user, $bank, 300_000, '2028-08-10', '2028-08-10 09:00:00');
    createDashboardTargetAllocation($bill, $user, $bank, 200_000, '2028-08-11', '2028-08-11 09:00:00', Payment::STATUS_CANCELLED);

    $canonical = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_YEARLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
    );

    $component = Livewire::test(Dashboard::class)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_YEARLY)
        ->set('targetAcademicYear', '2026/2027')
        ->set('targetJenjang', SchoolLevel::SMP->value)
        ->assertSee('Capaian Tagihan Tahunan')
        ->assertSee('Tahun Ajaran 2026/2027')
        ->assertSee('Rp 900.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 600.000')
        ->assertSee('33,3%')
        ->assertDontSeeHtml('wire:model.live="targetMonth"');

    expect($component->viewData('targetArrearsSummary')['totals'])->toBe($canonical['totals'])
        ->and($component->viewData('targetArrearsSummary')['bill_count'])->toBe(1)
        ->and($component->viewData('targetArrearsUrl'))->toBe(route('laporan.index', [
            'tab' => 'target',
            'target_mode' => 'yearly',
            'target_academic_year' => '2026/2027',
            'jenjang' => 'SMP',
        ]));
});

it('shows one-time achievement only in the originating academic year', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    [$student] = makeEnrolledStudent(SchoolLevel::SD);
    AcademicYear::query()->create([
        'year' => '2025/2026',
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $type = makeBillType('Sekali Bayar Dashboard Asal');
    $originBill = makeOneTimeBill($student, $type, 1_200_000, '2026/2027');
    makeOneTimeBill($student, $type, 900_000, '2025/2026');
    createDashboardTargetAllocation($originBill, $user, $bank, 400_000, '2029-01-10', '2029-01-10 09:00:00');

    $canonical = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_ONE_TIME,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SD,
    );

    $component = Livewire::test(Dashboard::class)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_ONE_TIME)
        ->set('targetAcademicYear', '2026/2027')
        ->set('targetJenjang', SchoolLevel::SD->value)
        ->assertSee('Capaian Tagihan Sekali Bayar')
        ->assertSee('TAHUN AJARAN ASAL TAGIHAN')
        ->assertSee('Rp 1.200.000')
        ->assertSee('Rp 400.000')
        ->assertSee('Rp 800.000')
        ->assertSee('33,3%');

    expect($component->viewData('targetArrearsSummary')['totals'])->toBe($canonical['totals'])
        ->and($component->viewData('targetArrearsSummary')['bill_count'])->toBe(1)
        ->and($component->viewData('targetArrearsUrl'))->toBe(route('laporan.index', [
            'tab' => 'target',
            'target_mode' => 'one_time',
            'target_academic_year' => '2026/2027',
            'jenjang' => 'SD',
        ]));

    $component
        ->set('targetJenjang', 'all')
        ->set('targetAcademicYear', '2025/2026')
        ->assertViewHas('targetArrearsSummary', fn (array $summary): bool => $summary['totals']['target'] === 900_000.0);
});

it('reports zero achievement for a zero effective one-time target', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::TK);
    $type = makeBillType('Sekali Bayar Target Nol');
    $bill = makeOneTimeBill($student, $type, 500_000);
    BillAdjustment::query()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500_000,
        'reason' => 'Pembebasan penuh',
        'created_by' => $user->id,
    ]);

    $component = Livewire::test(Dashboard::class)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_ONE_TIME);
    $summary = $component->viewData('targetArrearsSummary');

    expect($summary['bill_count'])->toBe(1)
        ->and($summary['totals']['target'])->toBe(0.0)
        ->and($summary['totals']['paid'])->toBe(0.0)
        ->and($summary['totals']['outstanding'])->toBe(0.0)
        ->and($summary['totals']['achievement_percentage'])->toBe(0.0)
        ->and($summary['totals']['achievement_label'])->toBe('0%');
});

it('renders mode-specific empty states for yearly and one-time tabs', function () {
    makeEnrolledStudent(SchoolLevel::SMA);

    Livewire::test(Dashboard::class)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_YEARLY)
        ->assertSee('Belum ada tagihan tahunan untuk Tahun Ajaran 2026/2027.')
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_ONE_TIME)
        ->assertSee('Belum ada tagihan sekali bayar dengan asal Tahun Ajaran 2026/2027.');
});

it('preserves monthly and academic-year filters while switching billing modes', function () {
    makeEnrolledStudent(SchoolLevel::SD);

    Livewire::test(Dashboard::class)
        ->set('targetMonth', 10)
        ->set('targetYear', 2027)
        ->set('targetAcademicYear', '2026/2027')
        ->set('targetJenjang', SchoolLevel::SD->value)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_YEARLY)
        ->assertSet('targetMonth', 10)
        ->assertSet('targetYear', 2027)
        ->assertSet('targetAcademicYear', '2026/2027')
        ->assertSet('targetJenjang', SchoolLevel::SD->value)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_ONE_TIME)
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_MONTHLY)
        ->assertSet('targetMonth', 10)
        ->assertSet('targetYear', 2027)
        ->assertSet('targetAcademicYear', '2026/2027')
        ->assertSet('targetJenjang', SchoolLevel::SD->value)
        ->assertSeeHtml('wire:model.live="targetMonth"');
});

it('defaults the target academic year to active then falls back to the latest available year', function () {
    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => true,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSet('targetMode', StudentTargetArrearsReportService::MODE_MONTHLY)
        ->assertSet('targetAcademicYear', '2027/2028');

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->create([
        'year' => '2028/2029',
        'is_active' => false,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSet('targetAcademicYear', '2028/2029');
});

it('queries only the selected billing frequency summary', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    makeMonthlyBill($student, makeBillType('Bulanan Batas Query Dashboard'), 100_000, 9, 2026);
    makeYearlyBill($student, makeBillType('Tahunan Batas Query Dashboard'), 200_000);
    makeOneTimeBill($student, makeBillType('Sekali Batas Query Dashboard'), 300_000);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::withQueryParams([
        'target_mode' => StudentTargetArrearsReportService::MODE_ONE_TIME,
        'target_academic_year' => '2026/2027',
    ])->test(Dashboard::class);

    $frequencyQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'student_bills')
            && str_contains($query['query'], 'billing_frequency'));

    DB::disableQueryLog();

    expect($frequencyQueries)->toHaveCount(1)
        ->and($frequencyQueries->first()['bindings'])->toContain(StudentTargetArrearsReportService::MODE_ONE_TIME)
        ->and($frequencyQueries->first()['bindings'])->not->toContain(StudentTargetArrearsReportService::MODE_MONTHLY)
        ->and($frequencyQueries->first()['bindings'])->not->toContain(StudentTargetArrearsReportService::MODE_YEARLY);
});
