<?php

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\User;
use App\Services\DaycareMonthlyReportService;
use App\Support\DaycareReportDocument;

function daycareMonthlyPdfPayment(
    User $user,
    Bank $bank,
    DaycareChild $child,
    string $date,
    int $amount,
): DaycarePayment {
    return DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);
}

it('streams a valid F4B portrait monthly daycare PDF with the selected period filename', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.monthly.pdf', ['month' => 8, 'year' => 2026]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-bulanan-daycare-2026-08.pdf');

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('612.283 935.433')
        ->and(public_path('images/annur_logo2.png'))->toBeFile();
});

it('renders the service monthly bank and cash sections, totals and formal signatures without child names', function () {
    $user = User::factory()->create(['name' => 'Admin Daycare Bulanan']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Rahasia PDF Bulanan']);
    $cash = Bank::factory()->cash()->create(['name' => 'Tunai']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    daycareMonthlyPdfPayment($user, $cash, $child, '2026-08-01', 500_000);
    daycareMonthlyPdfPayment($user, $bank, $child, '2026-08-01', 150_000);
    daycareMonthlyPdfPayment($user, $bank, $child, '2026-08-03', 200_000);

    $report = app(DaycareMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $html = view('reports.daycare-monthly-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('LAPORAN BULANAN DAYCARE')
        ->toContain('(MONTHLY REPORT)')
        ->toContain('AGUSTUS 2026')
        ->toContain('DAYCARE')
        ->toContain('PENERIMAAN BANK')
        ->toContain('PENERIMAAN TUNAI')
        ->toContain('RINGKASAN TOTAL')
        ->toContain('@page { size: 216mm 330mm; margin: 6mm 6mm 8mm; }')
        ->toContain('<col style="width: 38.000000%">')
        ->toContain('<col style="width: 68.000000%">')
        ->toContain('<col style="width: 17%">')
        ->toContain('<col style="width: 15%">')
        ->toContain('01 Agt 2026')
        ->toContain('03 Agt 2026')
        ->toContain('BSI — 111111')
        ->toContain('Rp 500.000')
        ->toContain('Rp 350.000')
        ->toContain('Rp 850.000')
        ->toContain('TOTAL PENERIMAAN BANK')
        ->toContain('TOTAL PENERIMAAN TUNAI')
        ->toContain('GRAND TOTAL')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th.col-category, .report-table th.col-total, .report-table th.col-daily-total, .report-table th.cash-total { text-align: center; }')
        ->toContain('Menyetujui,')
        ->toContain('Direktur Keuangan')
        ->toContain('Nova Rabi\'ah Nurrohmah, SE')
        ->toContain('Mengetahui,')
        ->toContain('Kepala Tata Usaha')
        ->toContain('TU An-Nur')
        ->toContain('Windiarti, SE')
        ->toContain('Bekasi, 31 Agustus 2026')
        ->toContain('Arif Hamdani')
        ->not->toContain('Anak Rahasia PDF Bulanan');
});

it('renders the report-ending September date in the monthly right signature', function () {
    $report = app(DaycareMonthlyReportService::class)->generate(2026, 9);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'admin_name' => 'Administrator',
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => DaycareReportDocument::CITY.', '.$report['last_day']->locale('id')->translatedFormat('d F Y'),
            'report_creator_name' => 'Sekretaris September',
        ],
    ];
    $html = view('reports.daycare-monthly-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('Bekasi, 30 September 2026')
        ->toContain('TU An-Nur')
        ->toContain('Direktur Keuangan')
        ->toContain('Nova Rabi\'ah Nurrohmah, SE')
        ->toContain('Sekretaris September');
});

it('renders a safe empty monthly daycare PDF', function () {
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
        ->toContain('Belum ada rekening bank yang dikonfigurasi')
        ->toContain('Tidak ada transaksi')
        ->toContain('RINGKASAN TOTAL')
        ->toContain('GRAND TOTAL');
});
