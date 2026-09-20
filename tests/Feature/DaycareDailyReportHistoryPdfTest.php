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
use App\Support\TransactionHistoryReport;
use Livewire\Livewire;

function daycareHistoryPayment(
    User $user,
    Bank $bank,
    string $date,
    int $amount,
    ?string $receiptNumber = null,
    ?DaycareChild $child = null,
    array $details = [],
): DaycarePayment {
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child?->id ?? DaycareChild::factory(),
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);

    if ($receiptNumber !== null) {
        $payment->forceFill(['receipt_number' => $receiptNumber])->saveQuietly();
    }

    if ($details === []) {
        $payment->details()->create([
            'description' => 'SPP Daycare',
            'amount' => $amount,
        ]);
    } else {
        foreach ($details as $detail) {
            $payment->details()->create($detail);
        }
    }

    return $payment;
}

function renderDaycareHistoryHtml(User $user, string $start, ?string $end = null): string
{
    $detailRows = app(DaycareDailyReportService::class)->detailRows($start, $end);
    $history = TransactionHistoryReport::groupDaily($detailRows);
    $printedAt = now()->settings(['locale' => 'id']);
    $report = app(DaycareDailyReportService::class)->generate($start, $end);
    $document = [
        'unit' => 'DAYCARE ANNUR',
        'jenjang' => null,
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'printed_by' => $user->name,
        'printed_at' => $printedAt->translatedFormat('d F Y'),
    ];

    return view('reports.daycare-daily-history-pdf', compact('report', 'document', 'history'))->render();
}

it('shows the Cetak Riwayat Transaksi button on the daycare report tab', function () {
    Livewire::test(DaycareDailyReport::class, ['activeTab' => 'daily'])
        ->assertSee('Cetak Riwayat Transaksi')
        ->assertSeeHtml('laporan/harian/riwayat.pdf');
});

it('protects and validates the daycare history PDF route', function () {
    $this->get(route('daycare.report.daily.riwayat.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.riwayat.pdf', ['start_date' => 'not-a-date', 'end_date' => '2026-08-27']))
        ->assertSessionHasErrors('start_date');
});

it('streams a valid F4B daycare history PDF inline with the selected date filename', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('daycare.report.daily.riwayat.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=riwayat-transaksi-daycare-2026-08-27.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('groups daycare history by bank with a single TUNAI group', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Riwayat']);

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create();

    daycareHistoryPayment($user, $bsi, '2026-08-27', 100_000, 'KWT-DRW-0001', $child);
    daycareHistoryPayment($user, $bca, '2026-08-27', 200_000, 'KWT-DRW-0002', $child);
    daycareHistoryPayment($user, $cash, '2026-08-27', 300_000, 'KWT-DRW-0003', $child);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('BANK: BSI — 111111')
        ->toContain('BANK: BCA — 222222')
        ->toContain('TUNAI / CASH')
        ->toContain('KWT-DRW-0001')
        ->toContain('KWT-DRW-0002')
        ->toContain('KWT-DRW-0003')
        ->toContain('DAYCARE ANNUR');
});

it('groups daycare history by payment detail with subtotals and grand total', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Riwayat', 'kelas' => 'Kelompok B']);
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);

    daycareHistoryPayment($user, $bsi, '2026-08-27', 750_000, 'KWT-DRW-SPP', $child, [
        ['description' => 'SPP Bulan Agustus', 'amount' => 550_000],
        ['description' => 'Ekskul Mewarnai', 'amount' => 200_000],
    ]);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('A. Ekskul Mewarnai</td>')
        ->toContain('B. SPP Bulan Agustus</td>')
        ->toContain('Total SPP Bulan Agustus')
        ->toContain('Total Ekskul Mewarnai')
        ->toContain('Rp 550.000')
        ->toContain('Rp 200.000')
        ->toContain('Total Bank')
        ->toContain('GRAND TOTAL')
        ->toContain('Rp 750.000');
});

it('resets the category letters per bank in the daycare history', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Angka']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Angka']);
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);

    daycareHistoryPayment($user, $bsi, '2026-08-27', 750_000, 'KWT-DANG-0001', $child, [
        ['description' => 'SPP Bulan Agustus', 'amount' => 550_000],
        ['description' => 'Ekskul Mewarnai', 'amount' => 200_000],
    ]);
    daycareHistoryPayment($user, $bca, '2026-08-27', 300_000, 'KWT-DANG-0002', $child, [
        ['description' => 'Uang Masuk Daycare', 'amount' => 300_000],
    ]);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('A. Ekskul Mewarnai</td>')
        ->toContain('B. SPP Bulan Agustus</td>')
        ->toContain('A. Uang Masuk Daycare</td>')
        ->not->toContain('C.');
});

it('removes the summary box and shows only bank groups and grand total', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Riwayat']);
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create();

    daycareHistoryPayment($user, $bsi, '2026-08-28', 500_000, 'KWT-DRW-B', $child);
    daycareHistoryPayment($user, $cash, '2026-08-26', 250_000, 'KWT-DRW-C', $child);

    $html = renderDaycareHistoryHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->not->toContain('Tunai / Cash')
        ->not->toContain('Transfer / Bank')
        ->not->toContain('Total Transaksi')
        ->toContain('Rp 250.000')
        ->toContain('Rp 500.000')
        ->toContain('Rp 750.000');
});

it('follows the selected date range on the daycare history PDF', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $cash = Bank::factory()->cash()->create();

    daycareHistoryPayment($user, $cash, '2026-08-25', 100_000, 'KWT-DRW-OUT');
    daycareHistoryPayment($user, $cash, '2026-08-26', 200_000, 'KWT-DRW-IN');

    $html = renderDaycareHistoryHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('KWT-DRW-IN')
        ->not->toContain('KWT-DRW-OUT');
});

it('never includes student payments in the daycare history PDF', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Riwayat']);
    $cash = Bank::factory()->cash()->create();
    $student = Student::factory()->create();

    $studentPayment = Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-LEAK',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $cash->id,
        'payment_date' => '2026-08-27',
        'total_amount' => 500_000,
        'payment_method' => 'cash',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $studentPayment->id,
        'payment_type_id' => makeBillType('SPP Leak')->id,
        'amount' => 500_000,
    ]);

    daycareHistoryPayment($user, $cash, '2026-08-27', 100_000, 'KWT-DRW-CLEAN', $child);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('KWT-DRW-CLEAN')
        ->not->toContain('KWT-STUDENT-LEAK');
});

it('renders daycare detail rows with receipt, date, child, unit and nominal', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Unik', 'kelas' => 'Kelompok A']);
    $cash = Bank::factory()->cash()->create();

    daycareHistoryPayment($user, $cash, '2026-08-27', 450_000, 'KWT-DRW-DET', $child);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('No. Kwitansi')
        ->toContain('Tanggal')
        ->toContain('Nama Anak')
        ->toContain('Unit/Kelas')
        ->toContain('Pembayaran')
        ->toContain('Nominal')
        ->toContain('KWT-DRW-DET')
        ->toContain('27/08/2026')
        ->toContain('Anak Unik')
        ->toContain('Kelompok A')
        ->toContain('SPP Daycare')
        ->toContain('Rp 450.000');
});

it('shows the tunai empty message when there are no cash transactions', function () {
    $user = User::factory()->create(['name' => 'Petugas Daycare Riwayat']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Riwayat']);
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);

    daycareHistoryPayment($user, $bsi, '2026-08-27', 300_000, 'KWT-DRW-BONLY', $child);

    $html = renderDaycareHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('TUNAI / CASH')
        ->toContain('Tidak ada transaksi tunai pada periode ini.')
        ->toContain('KWT-DRW-BONLY');
});
