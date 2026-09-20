<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Services\SchoolMonthlyReportService;
use Livewire\Livewire;

it('aggregates all canonical student monthly receipts by bank and payment type', function () {
    $user = User::factory()->create();
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$smaStudent] = makeEnrolledStudent(SchoolLevel::SMA);
    $unclassifiedStudent = Student::factory()->create();
    $cashA = Bank::factory()->cash()->create(['name' => 'Loket A']);
    $cashB = Bank::query()->create(['name' => 'Loket B', 'type' => Bank::TYPE_CASH, 'is_active' => true]);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    $bankNamedCash = Bank::factory()->create(['name' => 'Tunai', 'account_number' => '333333']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI', 'account_number' => '444444']);
    $monthlyType = makeBillType('SPP Seluruh Unit');
    $yearlyType = makeBillType('Uang Buku Seluruh Unit');
    $oneTimeType = makeBillType('Uang Pangkal Seluruh Unit');

    $recordedInSeptember = createSchoolMonthlyReportPayment($sdStudent, $bankA, $user, '2026-09-02', [
        ['payment_type_id' => $monthlyType->id, 'amount' => 100_000],
        ['payment_type_id' => $yearlyType->id, 'amount' => 50_000],
    ], paymentDate: '2026-08-31', recordedAt: '2026-09-02 08:00:00');
    createSchoolMonthlyReportPayment($smpStudent, $bankB, $user, '2026-09-05', [
        ['payment_type_id' => $oneTimeType->id, 'amount' => 200_000],
    ]);
    createSchoolMonthlyReportPayment($smaStudent, $bankNamedCash, $user, '2026-09-06', [
        ['payment_type_id' => $yearlyType->id, 'amount' => 25_000],
    ]);
    createSchoolMonthlyReportPayment($unclassifiedStudent, $cashA, $user, '2026-09-07', [
        ['payment_type_id' => $monthlyType->id, 'amount' => 75_000],
    ]);
    createSchoolMonthlyReportPayment($sdStudent, $cashB, $user, '2026-09-08', [
        ['payment_type_id' => $oneTimeType->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $bankA, $user, '2026-09-09', [
        ['payment_type_id' => $monthlyType->id, 'amount' => 999_000],
    ], status: Payment::STATUS_CANCELLED);

    $daycare = DaycarePayment::factory()->create([
        'bank_id' => $cashA->id,
        'payment_date' => '2026-09-10',
        'total_amount' => 500_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Daycare Seluruh Unit',
        'amount' => 500_000,
    ]);

    $allUnits = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9);
    $byDate = app(SchoolMonthlyReportService::class)->generate(2026, 9);
    $byLevel = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $august = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 8);
    $categories = collect($allUnits['categories'])->pluck('key', 'name');
    $bankRows = collect($allUnits['bank']['rows']);
    $monthlyKey = $categories['SPP Seluruh Unit'];
    $yearlyKey = $categories['Uang Buku Seluruh Unit'];
    $oneTimeKey = $categories['Uang Pangkal Seluruh Unit'];

    expect($allUnits['categories'])->toHaveCount(4)
        ->and($bankRows)->toHaveCount(4)
        ->and($bankRows->pluck('bank_id'))->toContain($bankA->id, $bankB->id, $bankNamedCash->id, $zeroBank->id)
        ->and($bankRows->where('bank_name', 'BSI'))->toHaveCount(2)
        ->and($bankRows->firstWhere('bank_id', $bankA->id)['amounts'][$monthlyKey])->toBe(100_000.0)
        ->and($bankRows->firstWhere('bank_id', $bankA->id)['amounts'][$yearlyKey])->toBe(50_000.0)
        ->and($bankRows->firstWhere('bank_id', $bankA->id)['total'])->toBe(150_000.0)
        ->and($bankRows->firstWhere('bank_id', $bankB->id)['amounts'][$oneTimeKey])->toBe(200_000.0)
        ->and($bankRows->firstWhere('bank_id', $bankNamedCash->id)['total'])->toBe(25_000.0)
        ->and($bankRows->firstWhere('bank_id', $zeroBank->id)['total'])->toBe(0.0)
        ->and($allUnits['cash']['amounts'][$monthlyKey])->toBe(75_000.0)
        ->and($allUnits['cash']['amounts'][$oneTimeKey])->toBe(100_000.0)
        ->and($allUnits['cash']['total'])->toBe(175_000.0)
        ->and($allUnits['bank']['total'])->toBe(375_000.0)
        ->and($allUnits['grand_total'])->toBe(550_000.0)
        ->and($allUnits['grand_total'])->toBe($allUnits['bank']['total'] + $allUnits['cash']['total'])
        ->and(collect($allUnits['detail_rows'])->pluck('payment_id'))->toContain($recordedInSeptember->id)
        ->and(collect($august['detail_rows'])->pluck('payment_id'))->not->toContain($recordedInSeptember->id)
        ->and(collect($allUnits['detail_rows'])->pluck('student_name'))->toContain($unclassifiedStudent->nama_lengkap)
        ->and(collect($allUnits['detail_rows'])->pluck('detail_label'))->not->toContain('Daycare Seluruh Unit')
        ->and($allUnits['detail_count'])->toBe(6);

    expect($allUnits['grand_total'])->toBe($byDate['grand_total'], $byLevel['grand_total'])
        ->and($allUnits['bank']['total'])->toBe($byDate['bank']['total'], $byLevel['bank']['total'])
        ->and($allUnits['cash']['total'])->toBe($byDate['cash']['total'], $byLevel['cash']['total']);

    foreach ($allUnits['categories'] as $category) {
        $key = $category['key'];

        expect($allUnits['grand_category_totals'][$key])
            ->toBe($byDate['grand_category_totals'][$key])
            ->toBe($byLevel['bank']['category_totals'][$key] + $byLevel['cash']['category_totals'][$key]);
        expect($allUnits['bank']['category_totals'][$key])
            ->toBe($byDate['bank']['category_totals'][$key])
            ->toBe($byLevel['bank']['category_totals'][$key]);
        expect($allUnits['cash']['amounts'][$key])
            ->toBe($byDate['cash']['category_totals'][$key])
            ->toBe($byLevel['cash']['category_totals'][$key]);
    }
});

it('returns clear empty matrices without populated active bank rows', function () {
    Bank::factory()->create();
    makeBillType('SPP Seluruh Unit Kosong');

    $report = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9);

    expect($report['detail_count'])->toBe(0)
        ->and($report['bank']['rows'])->toBe([])
        ->and($report['bank']['total'])->toBe(0.0)
        ->and($report['cash']['total'])->toBe(0.0)
        ->and($report['grand_total'])->toBe(0.0);
});

it('nests all monthly modes under the monthly top-level tab and preserves the period', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(SchoolDailyReport::class)
        ->assertSee('Laporan Harian')
        ->assertSee('Laporan Bulanan')
        ->assertSee('Rekap Bank')
        ->assertSee('Target & Tunggakan', false)
        ->assertDontSee('Per Jenjang')
        ->call('setActiveTab', 'monthly')
        ->assertSet('monthlyMode', SchoolDailyReport::MONTHLY_MODE_BY_DATE)
        ->assertSee('Per Tanggal')
        ->assertSee('Per Jenjang')
        ->assertSee('Seluruh Unit')
        ->set('reportMonth', 9)
        ->set('reportYear', 2026)
        ->call('setMonthlyMode', SchoolDailyReport::MONTHLY_MODE_BY_LEVEL)
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->assertSee('Penerimaan Bank per Jenjang')
        ->call('setMonthlyMode', SchoolDailyReport::MONTHLY_MODE_ALL_UNITS)
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->assertSee('Belum ada penerimaan siswa pada September 2026.');

    expect(substr_count($component->html(), "setActiveTab('level')"))->toBe(0);

    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'level'])
        ->assertSet('activeTab', 'monthly')
        ->assertSet('monthlyMode', SchoolDailyReport::MONTHLY_MODE_BY_LEVEL);
});

it('renders the all-units bank and cash matrices with blank zeros and correct exports', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BCA UI Seluruh Unit', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI UI Seluruh Unit', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket UI Seluruh Unit']);
    $type = makeBillType('SPP UI Seluruh Unit');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-11', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-12', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'monthlyMode' => SchoolDailyReport::MONTHLY_MODE_ALL_UNITS,
        'reportMonth' => 9,
        'reportYear' => 2026,
    ])
        ->assertSee('Penerimaan Bank Seluruh Unit')
        ->assertSee('Penerimaan Tunai Seluruh Unit')
        ->assertSee($bank->optionLabel())
        ->assertSee($zeroBank->optionLabel())
        ->assertDontSee($cash->name)
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 400.000')
        ->assertSee('laporan/seluruh-unit.xlsx')
        ->assertSee('laporan/seluruh-unit.pdf');

    expect($component->html())
        ->not->toContain('Rp 0')
        ->not->toContain('Penerimaan Bank per Tanggal')
        ->not->toContain('Penerimaan Bank per Jenjang');
});
