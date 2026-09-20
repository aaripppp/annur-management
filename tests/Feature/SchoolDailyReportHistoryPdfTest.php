<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolDailyReportService;
use App\Support\SchoolReportLevel;
use App\Support\TransactionHistoryReport;
use Livewire\Livewire;

/**
 * @param  list<array<string, mixed>>  $details
 */
function historyReportPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $date,
    array $details,
    string $receiptNumber,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => $receiptNumber,
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => array_sum(array_column($details, 'amount')),
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $date.' 09:00:00', 'updated_at' => $date.' 09:00:00'])->saveQuietly();

    foreach ($details as $detail) {
        PaymentDetail::query()->create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment;
}

function renderSchoolHistoryHtml(User $user, string $start, ?string $end = null, ?string $schoolLevel = null): string
{
    $report = app(SchoolDailyReportService::class)->generate(
        $start,
        $end,
        $schoolLevel !== null ? SchoolReportLevel::fromValue($schoolLevel) : null,
    );
    $history = TransactionHistoryReport::groupDaily($report['detail_rows']);
    $printedAt = now()->settings(['locale' => 'id']);
    $document = [
        'unit' => $report['unit_name'],
        'jenjang' => $schoolLevel !== null ? $schoolLevel : null,
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'printed_by' => $user->name,
        'printed_at' => $printedAt->translatedFormat('d F Y'),
    ];

    return view('reports.school-daily-history-pdf', compact('report', 'document', 'history'))->render();
}

it('shows the Cetak Riwayat Transaksi button on the daily report tab', function () {
    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'daily'])
        ->assertSee('Cetak Riwayat Transaksi')
        ->assertSeeHtml('laporan/harian/riwayat.pdf');
});

it('protects and validates the school history PDF route', function () {
    $this->get(route('laporan.harian.riwayat.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.riwayat.pdf', ['start_date' => 'not-a-date', 'end_date' => '2026-08-27']))
        ->assertSessionHasErrors('start_date');
});

it('streams a valid F4B school history PDF inline with the selected date filename', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.riwayat.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=riwayat-transaksi-sekolah-2026-08-27.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('groups school history by bank with transfer banks and a single TUNAI group', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat']);
    $spp = makeBillType('SPP');

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create(['name' => 'Kas', 'account_number' => '999999']);

    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ], 'KWT-RIW-0001');
    historyReportPayment($student, $bca, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ], 'KWT-RIW-0002');
    historyReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ], 'KWT-RIW-0003');

    $html = renderSchoolHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('BANK: BSI — 111111')
        ->toContain('BANK: BCA — 222222')
        ->toContain('TUNAI / CASH')
        ->toContain('KWT-RIW-0001')
        ->toContain('KWT-RIW-0002')
        ->toContain('KWT-RIW-0003');
});

it('groups school history by payment type with subtotals, bank totals and grand total', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat']);
    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create();

    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 250_000],
        ['payment_type_id' => $ekskul->id, 'amount' => 70_000],
    ], 'KWT-RIW-BSI');
    historyReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ], 'KWT-RIW-CASH');

    $html = renderSchoolHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('A. Ekskul</td>')
        ->toContain('B. SPP</td>')
        ->toContain('Total SPP')
        ->toContain('Total Ekskul')
        ->toContain('Rp 250.000')
        ->toContain('Rp 70.000')
        ->toContain('Rp 500.000')
        ->toContain('Total Bank')
        ->toContain('Total Tunai')
        ->toContain('GRAND TOTAL')
        ->toContain('Rp 820.000');
});

it('resets letters per bank and row numbers per payment category', function () {
    $user = User::factory()->create(['name' => 'Petugas Angka']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Angka']);
    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $formulir = makeBillType('Formulir');

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bca = Bank::factory()->create(['name' => 'BCA', 'account_number' => '222222']);

    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $ekskul->id, 'amount' => 70_000],
    ], 'KWT-ANG-0001');
    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 250_000],
    ], 'KWT-ANG-0002');
    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ], 'KWT-ANG-0003');
    historyReportPayment($student, $bca, $user, '2026-08-27', [
        ['payment_type_id' => $formulir->id, 'amount' => 350_000],
    ], 'KWT-ANG-0004');
    historyReportPayment($student, $bca, $user, '2026-08-27', [
        ['payment_type_id' => $formulir->id, 'amount' => 50_000],
    ], 'KWT-ANG-0005');

    $html = renderSchoolHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('A. Ekskul</td>')
        ->toContain('B. SPP</td>')
        ->toContain('A. Formulir</td>')
        ->not->toContain('C.');

    expect(substr_count($html, '<td class="col-no">1</td>'))->toBe(3)
        ->and(substr_count($html, '<td class="col-no">2</td>'))->toBe(2)
        ->and(substr_count($html, '<td class="col-no">3</td>'))->toBe(0);
});

it('removes the summary box and shows only bank groups, category totals and grand total', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat']);
    $spp = makeBillType('SPP');

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create();

    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 550_000],
    ], 'KWT-RIW-BSI');
    historyReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ], 'KWT-RIW-CASH');

    $html = renderSchoolHistoryHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->not->toContain('Tunai / Cash')
        ->not->toContain('Transfer / Bank')
        ->not->toContain('Total Transaksi')
        ->toContain('Total SPP (Tunai):')
        ->toContain('Total SPP (BSI):')
        ->toContain('Rp 200.000')
        ->toContain('Rp 550.000')
        ->toContain('Rp 750.000');
});

it('follows the selected date range on the school history PDF', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat']);
    $spp = makeBillType('SPP');
    $cash = Bank::factory()->cash()->create();

    historyReportPayment($student, $cash, $user, '2026-08-25', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ], 'KWT-RIW-OUTSIDE');
    historyReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ], 'KWT-RIW-INSIDE');

    $html = renderSchoolHistoryHtml($user, '2026-08-26', '2026-08-28');

    expect($html)
        ->toContain('KWT-RIW-INSIDE')
        ->not->toContain('KWT-RIW-OUTSIDE')
        ->not->toContain('Rp 300.000');
});

it('follows the selected jenjang filter on the school history PDF', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    $spp = makeBillType('SPP');
    $cash = Bank::factory()->cash()->create();

    historyReportPayment($sdStudent, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ], 'KWT-RIW-SD');
    historyReportPayment($smpStudent, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ], 'KWT-RIW-SMP');

    $html = renderSchoolHistoryHtml($user, '2026-08-27', '2026-08-27', 'SMP');

    expect($html)
        ->toContain('KWT-RIW-SMP')
        ->not->toContain('KWT-RIW-SD');
});

it('renders detail rows with receipt, date, student, class, payment type and nominal', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create([
        'nama_lengkap' => 'Siswa Detail',
        'class_id' => SchoolClass::query()->create(['name' => '8B', 'level' => 8])->id,
    ]);
    $spp = makeBillType('SPP');
    $cash = Bank::factory()->cash()->create();

    historyReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 450_000],
    ], 'KWT-RIW-DET');

    $html = renderSchoolHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('No. Kwitansi')
        ->toContain('Tanggal')
        ->toContain('Nama Siswa')
        ->toContain('Kelas')
        ->toContain('Pembayaran')
        ->toContain('Nominal')
        ->toContain('KWT-RIW-DET')
        ->toContain('27/08/2026')
        ->toContain('Siswa Detail')
        ->toContain('8B')
        ->toContain('Rp 450.000');
});

it('shows the tunai empty message when there are no cash transactions', function () {
    $user = User::factory()->create(['name' => 'Petugas Riwayat']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat']);
    $spp = makeBillType('SPP');
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);

    historyReportPayment($student, $bsi, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ], 'KWT-RIW-BONLY');

    $html = renderSchoolHistoryHtml($user, '2026-08-27');

    expect($html)
        ->toContain('TUNAI / CASH')
        ->toContain('Tidak ada transaksi tunai pada periode ini.')
        ->toContain('KWT-RIW-BONLY');
});

it('passes the school_level param through the page button', function () {
    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'daily'])
        ->assertSeeHtml('school_level=all')
        ->set('schoolLevel', 'SMP')
        ->assertSeeHtml('school_level=SMP');
});
