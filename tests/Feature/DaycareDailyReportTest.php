<?php

use App\Livewire\DaycareDailyReport;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\User;
use App\Services\DaycareDailyReportService;
use App\Services\DaycareDailyReportSpreadsheet;
use App\Services\DaycarePaymentDeletionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function daycareReportPayment(User $user, Bank $bank, string $date, int $amount, ?DaycareChild $child = null): DaycarePayment
{
    return DaycarePayment::factory()->create([
        'daycare_child_id' => $child?->id ?? DaycareChild::factory(),
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);
}

it('protects the daycare report page and export endpoints with authentication', function () {
    $this->get(route('daycare.report.daily'))->assertRedirect(route('login'));
    $this->get(route('daycare.report.daily.export', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));
    $this->get(route('daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));
});

it('aggregates an inclusive daycare date range into one grouped report', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);

    daycareReportPayment($user, $cash, '2026-08-25', 900_000);
    daycareReportPayment($user, $cash, '2026-08-26', 100_000);
    daycareReportPayment($user, $cash, '2026-08-27', 200_000);
    daycareReportPayment($user, $bsi, '2026-08-28', 300_000);
    daycareReportPayment($user, $bsi, '2026-08-29', 800_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $cashGroup = $report['channels']['cash']['banks'][0];
    $bankGroup = $report['channels']['transfer']['banks'][0];

    expect($report['is_single_day'])->toBeFalse()
        ->and($report['period_title'])->toBe('Periode')
        ->and($report['period_label'])->toBe('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->and($report['transaction_count'])->toBe(3)
        ->and($report['total_cash'])->toBe(300_000.0)
        ->and($report['total_transfer'])->toBe(300_000.0)
        ->and($report['grand_total'])->toBe(600_000.0)
        ->and($cashGroup['categories'][0]['count'])->toBe(2)
        ->and($cashGroup['categories'][0]['total'])->toBe(300_000.0)
        ->and($bankGroup['categories'][0]['count'])->toBe(1)
        ->and($bankGroup['categories'][0]['total'])->toBe(300_000.0);

    $component = Livewire::test(DaycareDailyReport::class, [
        'reportStartDate' => '2026-08-26',
        'reportEndDate' => '2026-08-28',
    ])
        ->assertSee('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->assertSee('Rp 600.000');

    expect($component->viewData('report')['grand_total'])->toBe(600_000.0);
});

it('groups daycare payments by bank type and bank id with one SPP Daycare category', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'BSI']);
    $firstBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $secondBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '333333']);

    daycareReportPayment($user, $cash, '2026-08-27', 100_000);
    daycareReportPayment($user, $cash, '2026-08-27', 50_000);
    daycareReportPayment($user, $firstBsi, '2026-08-27', 300_000);
    daycareReportPayment($user, $firstBsi, '2026-08-27', 60_000);
    daycareReportPayment($user, $secondBsi, '2026-08-27', 200_000);
    daycareReportPayment($user, $bca, '2026-08-27', 500_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');
    $cashGroup = $report['channels']['cash']['banks'][0];
    $transferGroups = collect($report['channels']['transfer']['banks'])->keyBy('bank_id');

    expect($report['category'])->toBe('SPP Daycare')
        ->and($report['transaction_count'])->toBe(6)
        ->and($report['total_cash'])->toBe(150_000.0)
        ->and($report['total_transfer'])->toBe(1_060_000.0)
        ->and($report['grand_total'])->toBe(1_210_000.0)
        ->and($cashGroup['bank_id'])->toBe($cash->id)
        ->and($cashGroup['transaction_count'])->toBe(2)
        ->and($cashGroup['categories'])->toBe([[
            'key' => 'spp-daycare',
            'name' => 'SPP Daycare',
            'count' => 2,
            'total' => 150_000.0,
        ]])
        ->and($transferGroups->keys()->all())->toContain($firstBsi->id, $secondBsi->id, $bca->id)
        ->and($transferGroups)->toHaveCount(3)
        ->and($transferGroups[$firstBsi->id]['transaction_count'])->toBe(2)
        ->and($transferGroups[$firstBsi->id]['total'])->toBe(360_000.0)
        ->and($transferGroups[$secondBsi->id]['total'])->toBe(200_000.0)
        ->and($transferGroups[$bca->id]['total'])->toBe(500_000.0);
});

it('filters the daycare report to the selected date only', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    daycareReportPayment($user, $cash, '2026-08-27', 100_000);
    daycareReportPayment($user, $cash, '2026-08-26', 999_999);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');

    expect($report['is_single_day'])->toBeTrue()
        ->and($report['period_title'])->toBe('Tanggal')
        ->and($report['period_label'])->toBe('27 Agustus 2026')
        ->and($report['transaction_count'])->toBe(1)
        ->and($report['grand_total'])->toBe(100_000.0);
});

it('never includes student payments in the daycare report', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA SISWA', 'account_number' => '123']);
    $type = makeBillType('SPP Daycare Isolation');

    $studentPayment = Payment::query()->create([
        'receipt_number' => 'KWT-STU-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-27',
        'total_amount' => 500_000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $studentPayment->id,
        'payment_type_id' => $type->id,
        'amount' => 500_000,
    ]);

    daycareReportPayment($user, $cash, '2026-08-27', 100_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');

    expect($report['transaction_count'])->toBe(1)
        ->and($report['grand_total'])->toBe(100_000.0);
});

it('does not render daycare child names on the report page', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Rahasia Daycare']);
    $cash = Bank::factory()->cash()->create();

    daycareReportPayment($user, $cash, '2026-08-27', 100_000, $child);

    Livewire::test(DaycareDailyReport::class, ['reportDate' => '2026-08-27'])
        ->assertSee('SPP Daycare')
        ->assertDontSee('Anak Rahasia Daycare');
});

it('excludes hard-deleted daycare payments from the report', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $deleted = daycareReportPayment($user, $cash, '2026-08-27', 100_000);
    daycareReportPayment($user, $cash, '2026-08-27', 50_000);

    app(DaycarePaymentDeletionService::class)->delete($deleted->id);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');

    expect($report['transaction_count'])->toBe(1)
        ->and($report['grand_total'])->toBe(50_000.0);
});

it('loads the daycare report without per-payment queries', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create();

    foreach (range(1, 15) as $index) {
        daycareReportPayment($user, $index % 2 === 0 ? $cash : $bank, '2026-08-27', $index * 1_000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($report['transaction_count'])->toBe(15)
        ->and($queryCount)->toBeLessThanOrEqual(2);
});

it('renders the same totals on the livewire page as the service', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    daycareReportPayment($user, $cash, '2026-08-27', 100_000);
    daycareReportPayment($user, $bsi, '2026-08-27', 200_000);
    daycareReportPayment($user, $bsi, '2026-08-27', 300_000);
    daycareReportPayment($user, $bca, '2026-08-27', 400_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-27');

    Livewire::test(DaycareDailyReport::class, ['reportDate' => '2026-08-27'])
        ->assertSee('SPP Daycare')
        ->assertSee('TUNAI / CASH')
        ->assertSee('TRANSFER / DEBET')
        ->assertSee($bsi->optionLabel())
        ->assertSee($bca->optionLabel())
        ->assertSee('Rincian')
        ->assertDontSee('Tampilkan')
        ->assertDontSeeHtml('<select')
        ->assertDontSeeHtml('wire:model="selectedBank"')
        ->assertSee('500.000')
        ->assertSee('400.000')
        ->assertSee('Rp '.number_format($report['grand_total'], 0, ',', '.'));
});

it('renders a safe empty state without fabricated rows for an empty day', function () {
    Livewire::test(DaycareDailyReport::class, ['reportDate' => '2026-08-27'])
        ->assertSee('Belum ada transaksi Daycare pada periode yang dipilih.')
        ->assertDontSee('SPP Daycare');
});

it('uses the shared responsive report layout across daycare report tabs', function () {
    $component = Livewire::test(DaycareDailyReport::class, ['reportDate' => '2026-08-27']);

    expect($component->html())
        ->toContain('annur-report-page')
        ->toContain('sm:grid-cols-2')
        ->not->toContain('Tampilkan')
        ->toContain('sm:grid-cols-2 xl:grid-cols-3')
        ->toContain('h-11 w-full')
        ->toContain('overflow-x-auto');

    $component->call('setActiveTab', 'monthly');

    expect($component->html())
        ->toContain('sm:grid-cols-2')
        ->not->toContain('Tampilkan')
        ->toContain('overflow-x-auto');
});

it('opens the implemented monthly daycare report tab without changing daily state', function () {
    Livewire::test(DaycareDailyReport::class, ['activeTab' => 'monthly'])
        ->assertSee('Belum ada transaksi Daycare pada periode yang dipilih.')
        ->assertSee('Bulan')
        ->assertSee('Tahun')
        ->assertDontSee('pengembangan berikutnya');
});

it('validates the date on the daily export and pdf endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.export', ['start_date' => 'bogus', 'end_date' => '2026-08-27']))
        ->assertSessionHasErrors('start_date');
    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => 'not-a-date']))
        ->assertSessionHasErrors('end_date');
});

it('rejects a daycare report end date before its start date', function () {
    Livewire::test(DaycareDailyReport::class, [
        'reportStartDate' => '2026-08-28',
        'reportEndDate' => '2026-08-28',
    ])
        ->set('reportEndDate', '2026-08-27')
        ->assertHasErrors(['reportEndDate' => 'after_or_equal'])
        ->assertSee('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.export', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-27',
        ]))
        ->assertSessionHasErrors('end_date');
});

it('exports a single Laporan Harian sheet matching the service totals', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'Rekening Harian', 'account_number' => '111111']);
    daycareReportPayment($user, $cash, '2026-08-26', 125_000);
    daycareReportPayment($user, $bank, '2026-08-28', 300_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $path = app(DaycareDailyReportSpreadsheet::class)->create($report);
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $reportRows = $sheets['Laporan Harian'];
    $periodRow = collect($reportRows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Periode');
    $cashHeader = collect($reportRows)->first(fn (array $row): bool => ($row[0] ?? null) === 'TUNAI / CASH');
    $transferHeader = collect($reportRows)->first(fn (array $row): bool => ($row[0] ?? null) === 'TRANSFER / DEBET');
    $bankRow = collect($reportRows)->first(fn (array $row): bool => ($row[1] ?? null) === $bank->optionLabel());
    $categoryRows = collect($reportRows)
        ->filter(fn (array $row): bool => ($row[0] ?? null) === 'SPP Daycare')
        ->values();
    $grandTotalRow = collect($reportRows)
        ->first(fn (array $row): bool => ($row[1] ?? null) === 'TOTAL PENERIMAAN');

    expect(array_keys($sheets))->toBe(['Laporan Harian'])
        ->and($periodRow[1])->toBe('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->and($cashHeader)->not->toBeNull()
        ->and($transferHeader)->not->toBeNull()
        ->and($bankRow[2])->toBe(300_000)
        ->and($categoryRows)->toHaveCount(2)
        ->and($categoryRows->pluck(1)->all())->toBe([1, 1])
        ->and($categoryRows->sum(2))->toBe(425_000)
        ->and($grandTotalRow[2])->toBe(425_000)
        ->and((float) $grandTotalRow[2])->toBe($report['grand_total']);

    $this->actingAs($user)
        ->get(route('daycare.report.daily.export', ['start_date' => '2026-08-26', 'end_date' => '2026-08-28']))
        ->assertOk()
        ->assertDownload('laporan-harian-daycare-2026-08-26-sampai-2026-08-28.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
