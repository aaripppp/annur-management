<?php

use App\Enums\SchoolLevel;
use App\Livewire\Dashboard;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use Carbon\CarbonImmutable;
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
