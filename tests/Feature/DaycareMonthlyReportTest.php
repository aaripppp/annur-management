<?php

use App\Livewire\DaycareDailyReport;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\DaycareDailyReportService;
use App\Services\DaycareMonthlyReportService;
use App\Services\DaycareMonthlyReportSpreadsheet;
use App\Services\DaycarePaymentDeletionService;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function daycareMonthlyPayment(
    User $user,
    Bank $bank,
    string $date,
    int $amount,
    ?DaycareChild $child = null,
): DaycarePayment {
    return DaycarePayment::factory()->create([
        'daycare_child_id' => $child?->id ?? DaycareChild::factory(),
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);
}

it('aggregates daycare payments into per-date bank and cash sections for the selected month', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Rahasia Bulanan']);
    $cash = Bank::factory()->cash()->create(['name' => 'Tunai']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);

    daycareMonthlyPayment($user, $cash, '2026-07-31', 900_000, $child);
    daycareMonthlyPayment($user, $cash, '2026-08-01', 100_000, $child);
    daycareMonthlyPayment($user, $cash, '2026-08-01', 50_000, $child);
    daycareMonthlyPayment($user, $bank, '2026-08-01', 300_000, $child);
    daycareMonthlyPayment($user, $bank, '2026-08-03', 200_000, $child);
    daycareMonthlyPayment($user, $cash, '2026-08-31', 400_000, $child);
    daycareMonthlyPayment($user, $bank, '2026-09-01', 800_000, $child);
    $deleted = daycareMonthlyPayment($user, $cash, '2026-08-15', 700_000, $child);
    app(DaycarePaymentDeletionService::class)->delete($deleted->id);

    $student = Student::factory()->create();
    Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-MONTHLY-ISOLATION',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $cash->id,
        'payment_date' => '2026-08-02',
        'total_amount' => 999_999,
        'payment_method' => 'cash',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);

    expect($report['month_label'])->toBe('Agustus 2026')
        ->and($report['month_label_upper'])->toBe('AGUSTUS 2026')
        ->and($report['category'])->toBe('DAYCARE')
        ->and($report['categories'])->toBe([['key' => 'daycare', 'name' => 'DAYCARE']])
        ->and(collect($report['bank']['dates'])->pluck('date_key')->all())
        ->toBe(['2026-08-01', '2026-08-03'])
        ->and($report['bank']['dates'][0]['banks'])->toHaveCount(1)
        ->and($report['bank']['dates'][0]['banks'][0]['bank_label'])->toBe('BSI — 111111')
        ->and($report['bank']['dates'][0]['banks'][0]['amounts']['daycare'])->toBe(300_000.0)
        ->and($report['bank']['dates'][0]['banks'][0]['total'])->toBe(300_000.0)
        ->and($report['bank']['dates'][0]['total'])->toBe(300_000.0)
        ->and($report['bank']['dates'][1]['total'])->toBe(200_000.0)
        ->and(collect($report['cash']['dates'])->pluck('date_key')->all())
        ->toBe(['2026-08-01', '2026-08-31'])
        ->and($report['cash']['dates'][0]['amounts']['daycare'])->toBe(150_000.0)
        ->and($report['cash']['dates'][0]['total'])->toBe(150_000.0)
        ->and($report['cash']['dates'][1]['total'])->toBe(400_000.0)
        ->and($report['bank']['category_totals']['daycare'])->toBe(500_000.0)
        ->and($report['cash']['category_totals']['daycare'])->toBe(550_000.0)
        ->and($report['bank']['total'])->toBe(500_000.0)
        ->and($report['cash']['total'])->toBe(550_000.0)
        ->and($report['grand_total'])->toBe(1_050_000.0)
        ->and($report['transaction_count'])->toBe(5)
        ->and($report['detail_count'])->toBe(5);
});

it('renders the monthly workspace without child names or student categories', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Privat Monthly']);
    $cash = Bank::factory()->cash()->create();
    daycareMonthlyPayment($user, $cash, '2026-08-05', 250_000, $child);

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
    ])
        ->assertSee('Agustus 2026')
        ->assertSee('DAYCARE')
        ->assertSee('PENERIMAAN BANK')
        ->assertSee('PENERIMAAN TUNAI')
        ->assertSee('RINGKASAN TOTAL')
        ->assertSee('05 Agustus 2026')
        ->assertSee('Rp 250.000')
        ->assertSee('Bulan')
        ->assertSee('Tahun')
        ->assertDontSee('Tampilkan')
        ->assertSee('laporan/bulanan.xlsx')
        ->assertSee('laporan/bulanan/pdf')
        ->assertDontSee('Anak Privat Monthly')
        ->assertDontSee('SPP Siswa');
});

it('handles an empty daycare month and preserves the existing daily calculation', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    daycareMonthlyPayment($user, $cash, '2026-08-27', 175_000);

    $dailyBefore = app(DaycareDailyReportService::class)->generate('2026-08-27');

    Livewire::test(DaycareDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 9,
        'reportYear' => 2026,
    ])
        ->assertSee('Belum ada transaksi Daycare pada periode yang dipilih.')
        ->assertSee('Rp 0');

    $dailyAfter = app(DaycareDailyReportService::class)->generate('2026-08-27');

    expect($dailyAfter['transaction_count'])->toBe($dailyBefore['transaction_count'])
        ->and($dailyAfter['grand_total'])->toBe($dailyBefore['grand_total']);
});

it('protects and validates daycare monthly export endpoints', function () {
    $this->get(route('daycare.report.monthly.export', ['month' => 8, 'year' => 2026]))
        ->assertRedirect(route('login'));
    $this->get(route('daycare.report.monthly.pdf', ['month' => 8, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.monthly.export', ['month' => 0, 'year' => 2026]))
        ->assertSessionHasErrors('month');
    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.monthly.pdf', ['month' => 8, 'year' => 1999]))
        ->assertSessionHasErrors('year');
});

it('exports the same daycare monthly bank cash and totals as the service', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Tunai']);
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    daycareMonthlyPayment($user, $cash, '2026-08-02', 125_000);
    daycareMonthlyPayment($user, $bank, '2026-08-02', 300_000);
    daycareMonthlyPayment($user, $cash, '2026-08-04', 75_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $path = app(DaycareMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;
    $reader->open($path);

    try {
        $sheet = $reader->getSheetIterator()->current();
        $rows = collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->all();
    } finally {
        $reader->close();
        unlink($path);
    }

    $period = collect($rows)->first(fn (array $row): bool => str_starts_with((string) ($row[0] ?? ''), 'PERIODE :'));
    $category = collect($rows)->first(fn (array $row): bool => str_starts_with((string) ($row[0] ?? ''), 'KATEGORI :'));
    $bankRow = collect($rows)->first(fn (array $row): bool => ($row[1] ?? null) === 'BCA — 222222');
    $ringkasanIndex = collect($rows)->search(fn (array $row): bool => ($row[0] ?? null) === 'RINGKASAN TOTAL');

    expect($ringkasanIndex)->not->toBeFalse();

    $bankSummary = $rows[$ringkasanIndex + 1] ?? null;
    $cashSummary = $rows[$ringkasanIndex + 2] ?? null;
    $grandSummary = $rows[$ringkasanIndex + 3] ?? null;

    expect($sheet->getName())->toBe('Laporan Bulanan')
        ->and($period[0])->toBe('PERIODE : AGUSTUS 2026')
        ->and($category[0])->toBe('KATEGORI : DAYCARE')
        ->and($bankRow[0])->toContain('02 Agustus 2026')
        ->and((float) $bankRow[2])->toBe(300_000.0)
        ->and((float) $bankRow[3])->toBe(300_000.0)
        ->and((float) $bankRow[4])->toBe(300_000.0)
        ->and((float) $bankSummary[1])->toBe($report['bank']['total'])
        ->and((float) $cashSummary[1])->toBe($report['cash']['total'])
        ->and((float) $grandSummary[1])->toBe($report['grand_total'])
        ->and(collect($rows)->flatten()->contains(125_000))->toBeTrue()
        ->and(collect($rows)->flatten()->contains(75_000))->toBeTrue()
        ->and(collect($rows)->flatten()->contains('Anak'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('daycare.report.monthly.export', ['month' => 8, 'year' => 2026]))
        ->assertOk()
        ->assertDownload('laporan-bulanan-daycare-2026-08.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
