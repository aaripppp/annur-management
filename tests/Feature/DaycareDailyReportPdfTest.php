<?php

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\User;
use App\Services\DaycareDailyReportService;

function daycarePdfReportPayment(User $user, Bank $bank, string $date, int $amount, ?DaycareChild $child = null): DaycarePayment
{
    return DaycarePayment::factory()->create([
        'daycare_child_id' => $child?->id ?? DaycareChild::factory(),
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);
}

function renderDaycareDailyPdfHtml(User $user, string $start, string $end): string
{
    $report = app(DaycareDailyReportService::class)->generate($start, $end);
    $localizedDate = $report['end_date']->settings(['locale' => 'id']);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];

    return view('reports.daycare-daily-pdf', compact('report', 'document'))->render();
}

it('protects and validates the daycare daily report PDF route', function () {
    $this->get(route('daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.pdf', ['start_date' => 'not-a-date', 'end_date' => '2026-08-27']))
        ->assertSessionHasErrors('start_date');
});

it('streams a valid F4B PDF inline with the selected date filename and logo', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-harian-daycare-2026-08-27.pdf');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and(public_path('images/annur_logo2.png'))->toBeFile();
});

it('renders grouped cash and bank sections with the student report accounting structure', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare PDF']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Rahasia PDF']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);

    daycarePdfReportPayment($user, $cash, '2026-08-26', 500_000, $child);
    daycarePdfReportPayment($user, $bank, '2026-08-28', 150_000);

    $report = app(DaycareDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $localizedDate = $report['end_date']->settings(['locale' => 'id']);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $html = view('reports.daycare-daily-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('LAPORAN HARIAN DAYCARE')
        ->toContain('DAYCARE ANNUR')
        ->toContain('Periode')
        ->toContain('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->toContain('TUNAI / CASH')
        ->toContain('TRANSFER / DEBET')
        ->toContain('BNI — 111111')
        ->toContain('Penerimaan (Debet / transfer)')
        ->toContain('Rincian')
        ->toContain('Terima')
        ->toContain('Keluar')
        ->toContain('Saldo')
        ->toContain('SPP Daycare')
        ->toContain('Rp 650.000')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th.col-amount { text-align: center; }')
        ->toContain('Menyetujui,')
        ->toContain('Direktur Keuangan')
        ->toContain('Nova Rabi\'ah Nurrohmah, SE')
        ->toContain('Mengetahui,')
        ->toContain('Kepala Tata Usaha')
        ->toContain('TU An-Nur')
        ->toContain('Windiarti, SE')
        ->toContain('Bekasi, 28 Agustus 2026')
        ->toContain('Arif Hamdani')
        ->not->toContain('Anak Rahasia PDF');

    expect(substr_count($html, 'SPP Daycare'))->toBe(2);

    $this->actingAs($user)
        ->get(route('daycare.report.daily.pdf', [
            'start_date' => '2026-08-26',
            'end_date' => '2026-08-28',
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-harian-daycare-2026-08-26-sampai-2026-08-28.pdf');
});

it('blanks zero count and amount for an empty cash section with no transactions', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare PDF']);
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    daycarePdfReportPayment($user, $bank, '2026-08-28', 150_000);

    $html = renderDaycareDailyPdfHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('TUNAI / CASH')
        ->toContain('Tidak ada transaksi')
        ->toContain('TRANSFER / DEBET')
        ->toContain('BNI — 111111')
        ->toContain('SPP Daycare')
        ->toContain('Rp 150.000')
        ->not->toContain('class="col-detail">0</td>')
        ->not->toContain('class="col-amount">Rp 0</td>');
});

it('blanks zero count and amount for an empty transfer section with no transactions', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare PDF']);
    $cash = Bank::factory()->cash()->create();
    daycarePdfReportPayment($user, $cash, '2026-08-26', 500_000);

    $html = renderDaycareDailyPdfHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('TRANSFER / DEBET')
        ->toContain('Tidak ada transaksi')
        ->toContain('TUNAI / CASH')
        ->toContain('SPP Daycare')
        ->toContain('Rp 500.000')
        ->not->toContain('class="col-detail">0</td>')
        ->not->toContain('class="col-amount">Rp 0</td>');
});

it('keeps real counts and amounts when both channels have transactions', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare PDF']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    daycarePdfReportPayment($user, $cash, '2026-08-26', 500_000);
    daycarePdfReportPayment($user, $bank, '2026-08-28', 150_000);

    $html = renderDaycareDailyPdfHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('TUNAI / CASH')
        ->toContain('TRANSFER / DEBET')
        ->toContain('SPP Daycare')
        ->toContain('Rp 500.000')
        ->toContain('Rp 150.000')
        ->toContain('TOTAL PENERIMAAN')
        ->toContain('>2</td>')
        ->not->toContain('Tidak ada transaksi');
});

it('keeps the entirely empty report understandable without meaningless zeros', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare PDF']);

    $html = renderDaycareDailyPdfHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('TUNAI / CASH')
        ->toContain('TRANSFER / DEBET')
        ->toContain('Tidak ada transaksi')
        ->toContain('TOTAL PENERIMAAN')
        ->not->toContain('class="col-detail">0</td>')
        ->not->toContain('class="col-amount">Rp 0</td>')
        ->and(substr_count($html, 'Tidak ada transaksi'))->toBe(2);
});
