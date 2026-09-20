<?php

use App\Livewire\DaycareDailyReport;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\DaycareMonthlyReportService;
use App\Services\DaycareMonthlyReportSpreadsheet;
use App\Services\DaycarePaymentDeletionService;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function daycarePerTanggalPayment(
    User $user,
    Bank $bank,
    string $date,
    int $amount,
    ?DaycareChild $child = null,
    ?string $createdAt = null,
): DaycarePayment {
    return DaycarePayment::factory()->create(array_filter([
        'daycare_child_id' => $child?->id ?? DaycareChild::factory(),
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
        'created_at' => $createdAt,
    ], fn (mixed $value): bool => $value !== null));
}

it('includes receipts by operational payment_date regardless of when they were recorded', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create();

    daycarePerTanggalPayment($user, $cash, '2026-08-31', 120_000, createdAt: '2026-09-10 08:30:00');
    daycarePerTanggalPayment($user, $bank, '2026-09-01', 300_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['has_payments'])->toBeTrue()
        ->and(collect($report['cash']['dates'])->pluck('date_key')->all())->toBe(['2026-08-31'])
        ->and($report['cash']['total'])->toBe(120_000.0)
        ->and($report['bank']['total'])->toBe(0.0)
        ->and($report['grand_total'])->toBe(120_000.0)
        ->and($report['transaction_count'])->toBe(1);
});

it('merges same-date payments from the same bank into a single line and sums their amount', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    daycarePerTanggalPayment($user, $bank, '2026-08-11', 100_000);
    daycarePerTanggalPayment($user, $bank, '2026-08-11', 250_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $dateGroup = $report['bank']['dates'][0];

    expect($report['bank']['dates'])->toHaveCount(1)
        ->and($dateGroup['banks'])->toHaveCount(1)
        ->and($dateGroup['banks'][0]['amounts']['daycare'])->toBe(350_000.0)
        ->and($dateGroup['banks'][0]['total'])->toBe(350_000.0)
        ->and($dateGroup['total'])->toBe(350_000.0)
        ->and($report['bank']['total'])->toBe(350_000.0)
        ->and($report['transaction_count'])->toBe(2)
        ->and($report['detail_count'])->toBe(2);
});

it('keeps different banks on the same date as separate lines', function () {
    $user = User::factory()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);

    daycarePerTanggalPayment($user, $bsi, '2026-08-11', 100_000);
    daycarePerTanggalPayment($user, $bca, '2026-08-11', 200_000);

    $dateGroup = app(DaycareMonthlyReportService::class)->generate(2026, 8)['bank']['dates'][0];

    expect($dateGroup['banks'])->toHaveCount(2)
        ->and(collect($dateGroup['banks'])->pluck('bank_label')->all())
        ->toBe(['BCA — 222222', 'BSI — 111111'])
        ->and($dateGroup['banks'][0]['total'])->toBe(200_000.0)
        ->and($dateGroup['banks'][1]['total'])->toBe(100_000.0)
        ->and($dateGroup['total'])->toBe(300_000.0);
});

it('keeps two banks with the same name but different ids on separate lines', function () {
    $user = User::factory()->create();
    $first = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '111111']);
    $second = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);

    daycarePerTanggalPayment($user, $first, '2026-08-12', 150_000);
    daycarePerTanggalPayment($user, $second, '2026-08-12', 175_000);

    $dateGroup = app(DaycareMonthlyReportService::class)->generate(2026, 8)['bank']['dates'][0];

    expect($dateGroup['banks'])->toHaveCount(2)
        ->and(collect($dateGroup['banks'])->pluck('bank_label')->all())
        ->toBe(['Mandiri — 111111', 'Mandiri — 222222'])
        ->and($dateGroup['total'])->toBe(325_000.0);
});

it('renders every active bank on a bank date with a blank zero cell instead of Rupiah zero', function () {
    $user = User::factory()->create();
    $used = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $unusedActive = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    Bank::factory()->create(['name' => 'BNI', 'account_number' => '333333', 'is_active' => false]);
    $cash = Bank::factory()->cash()->create();

    daycarePerTanggalPayment($user, $used, '2026-08-13', 250_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-13', 100_000);

    $dateGroup = app(DaycareMonthlyReportService::class)->generate(2026, 8)['bank']['dates'][0];

    expect($dateGroup['bank_count'])->toBe(2)
        ->and(collect($dateGroup['banks'])->pluck('bank_name')->all())->toBe(['BCA', 'BSI'])
        ->and($dateGroup['banks'][0]['amounts']['daycare'])->toBe(0.0)
        ->and($dateGroup['banks'][1]['amounts']['daycare'])->toBe(250_000.0);

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
    ])
        ->assertSee('BCA — 222222')
        ->assertSee('BSI — 111111')
        ->assertSee('Rp 250.000')
        ->assertDontSee('BCA — 333333')
        ->assertDontSee('Rp 0');
});

it('does not create bank rows for dates without any bank receipt', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    daycarePerTanggalPayment($user, $cash, '2026-08-15', 200_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-16', 100_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['bank']['dates'])->toBe([])
        ->and($report['cash']['dates'])->toHaveCount(2)
        ->and($report['cash']['total'])->toBe(300_000.0)
        ->and($report['grand_total'])->toBe(300_000.0);
});

it('excludes cash receipts from bank sections and isolates them in the cash section', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $cash = Bank::factory()->cash()->create();

    daycarePerTanggalPayment($user, $bank, '2026-08-14', 400_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-14', 600_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $dateGroup = $report['bank']['dates'][0];

    expect($dateGroup['banks'])->toHaveCount(1)
        ->and(collect($dateGroup['banks'])->pluck('bank_type')->all())->toBe(['bank'])
        ->and($dateGroup['total'])->toBe(400_000.0)
        ->and($report['cash']['dates'][0]['total'])->toBe(600_000.0)
        ->and($report['bank']['total'])->toBe(400_000.0)
        ->and($report['cash']['total'])->toBe(600_000.0)
        ->and($report['grand_total'])->toBe(1_000_000.0);
});

it('classifies receipts purely by the bank type column, not by its name', function () {
    $user = User::factory()->create();
    $bankNamedTunai = Bank::factory()->create(['name' => 'Tunai', 'account_number' => '111111']);
    $cashNamedMandiri = Bank::factory()->cash()->create(['name' => 'Mandiri']);

    daycarePerTanggalPayment($user, $bankNamedTunai, '2026-08-17', 300_000);
    daycarePerTanggalPayment($user, $cashNamedMandiri, '2026-08-17', 250_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['bank']['dates'][0]['banks'][0]['bank_label'])->toBe('Tunai — 111111')
        ->and($report['bank']['total'])->toBe(300_000.0)
        ->and($report['cash']['dates'][0]['total'])->toBe(250_000.0)
        ->and($report['grand_total'])->toBe(550_000.0);
});

it('renders the bank and cash tables with daily, section and grand totals on the web', function () {
    $user = User::factory()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create();

    daycarePerTanggalPayment($user, $bsi, '2026-08-18', 100_000);
    daycarePerTanggalPayment($user, $bca, '2026-08-18', 200_000);
    daycarePerTanggalPayment($user, $bca, '2026-08-19', 300_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-20', 500_000);

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
    ])
        ->assertSee('TOTAL PENERIMAAN BANK')
        ->assertSee('Rp 600.000')
        ->assertSee('TOTAL PENERIMAAN TUNAI')
        ->assertSee('Rp 500.000')
        ->assertSee('GRAND TOTAL')
        ->assertSee('Rp 1.100.000')
        ->assertSee('Total Harian')
        ->assertDontSee('SPP Siswa');
});

it('honours the month and year filters on the report component', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    daycarePerTanggalPayment($user, $bank, '2026-08-21', 400_000);
    daycarePerTanggalPayment($user, $bank, '2026-09-21', 900_000);

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
    ])
        ->assertSee('Agustus 2026')
        ->assertSee('Rp 400.000')
        ->assertDontSee('900.000');

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 9,
        'reportYear' => 2026,
    ])
        ->assertSee('September 2026')
        ->assertSee('Rp 900.000');
});

it('excludes payments removed through the daycare deletion service', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    daycarePerTanggalPayment($user, $bank, '2026-08-22', 350_000);
    $removed = daycarePerTanggalPayment($user, $bank, '2026-08-22', 700_000);
    app(DaycarePaymentDeletionService::class)->delete($removed->id);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['bank']['total'])->toBe(350_000.0)
        ->and($report['grand_total'])->toBe(350_000.0)
        ->and($report['transaction_count'])->toBe(1);
});

it('never mixes student payments into the daycare monthly figures', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    daycarePerTanggalPayment($user, $bank, '2026-08-23', 250_000);

    $student = Student::factory()->create();
    Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-PERTANGGAL',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-23',
        'total_amount' => 9_999_999,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['bank']['total'])->toBe(250_000.0)
        ->and($report['grand_total'])->toBe(250_000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and($report['category'])->toBe('DAYCARE');
});

it('exports the same per-tanggal rows with numeric money and blank zero cells', function () {
    $user = User::factory()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create();

    daycarePerTanggalPayment($user, $bsi, '2026-08-24', 120_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-24', 80_000);
    daycarePerTanggalPayment($user, $bca, '2026-08-25', 60_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $path = app(DaycareMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;
    $reader->open($path);

    try {
        $rows = collect(iterator_to_array($reader->getSheetIterator()->current()->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->all();
    } finally {
        $reader->close();
        unlink($path);
    }

    $bcaRows = collect($rows)->filter(fn (array $row): bool => ($row[1] ?? null) === 'BCA — 222222')->values();
    $ringkasanIndex = collect($rows)->search(fn (array $row): bool => ($row[0] ?? null) === 'RINGKASAN TOTAL');

    expect($ringkasanIndex)->not->toBeFalse();

    $bankSummary = $rows[$ringkasanIndex + 1] ?? null;
    $cashSummary = $rows[$ringkasanIndex + 2] ?? null;
    $grandSummary = $rows[$ringkasanIndex + 3] ?? null;

    expect($bcaRows)->toHaveCount(2)
        ->and($bcaRows[0][0])->toContain('24 Agustus 2026')
        ->and((float) $bcaRows[0][2])->toBe(0.0)
        ->and($bcaRows[1][0])->toContain('25 Agustus 2026')
        ->and((float) $bcaRows[1][2])->toBe(60_000.0)
        ->and((float) $bankSummary[1])->toBe($report['bank']['total'])
        ->and((float) $cashSummary[1])->toBe($report['cash']['total'])
        ->and((float) $grandSummary[1])->toBe($report['grand_total'])
        ->and((float) $grandSummary[1])->toBe(260_000.0);
});

it('renders the same per-tanggal daycare totals in the monthly PDF', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create();
    daycarePerTanggalPayment($user, $bank, '2026-08-26', 450_000);
    daycarePerTanggalPayment($user, $cash, '2026-08-27', 350_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'admin_name' => 'Administrator',
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $html = view('reports.daycare-monthly-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('Mandiri — 111111')
        ->toContain('26 Agt 2026')
        ->toContain('27 Agt 2026')
        ->toContain('Rp 450.000')
        ->toContain('Rp 350.000')
        ->toContain('Rp 800.000')
        ->toContain('GRAND TOTAL');
});

it('labels each receipt date with an Indonesian weekday and month name', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    daycarePerTanggalPayment($user, $cash, '2026-08-01', 100_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['cash']['dates'][0]['date_label'])->toBe('Sabtu, 01 Agustus 2026')
        ->and($report['month_label'])->toBe('Agustus 2026');
});
