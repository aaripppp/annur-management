<?php

use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Student payment summary cards + date filter
 |--------------------------------------------------------------------------
 |
 | The Pembayaran > Pembayaran Siswa tab shows two aggregate cards (Total
 | Pemasukan Siswa and Jumlah Transaksi) driven by a date filter with quick
 | presets and a manual date range. The recap merges active Student payments
 | from the payments table with active Formulir payments from a Prospect who
 | has already been converted into a Student. Cancelled, unconverted
 | prospect, and Daycare payments are never counted.
 |
 */

function studentSummaryPayment(string $date, int|float $totalAmount, array $attributes = []): Payment
{
    $student = Student::factory()->create();
    $bank = Bank::factory()->create();
    $user = User::factory()->create();

    return Payment::query()->create([
        'receipt_number' => 'KWT-SISWA-SUM-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $totalAmount,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
        ...$attributes,
    ]);
}

function studentSummaryProspectivePayment(string $date, int|float $totalAmount, bool $converted = true, array $attributes = []): ProspectiveStudentPayment
{
    $bank = Bank::factory()->create();

    if ($converted) {
        $prospect = ProspectiveStudent::factory()->converted()->create([
            'converted_student_id' => Student::factory()->create()->id,
        ]);
    } else {
        $prospect = ProspectiveStudent::factory()->create();
    }

    return ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $prospect->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $totalAmount,
        'status' => ProspectiveStudentPayment::STATUS_ACTIVE,
        ...$attributes,
    ]);
}

function studentSummaryDataSet(): void
{
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryPayment('2026-09-17', 200000);
    studentSummaryPayment('2026-09-05', 100000);
    studentSummaryPayment('2026-08-05', 400000);
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('renders the student summary section with both cards, controls and a default empty total', function () {
    Livewire::test(PaymentIndex::class)
        ->assertSee('Ringkasan Pemasukan Siswa')
        ->assertSee('Total Pemasukan Siswa')
        ->assertSee('Jumlah Transaksi')
        ->assertSee('Semua Hari')
        ->assertSee('Hari Ini')
        ->assertSee('Kemarin')
        ->assertSee('Bulan Ini')
        ->assertSee('Rp 0')
        ->assertSee('0 Transaksi')
        ->assertSeeHtml('data-testid="student-summary-total-amount"')
        ->assertSeeHtml('data-testid="student-summary-total-count"')
        ->assertSet('summaryPreset', 'all');
});

it('shows the total student income across all payments by default', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 1.000.000');
});

it('shows the total transaction count across all payments by default', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->assertSee('4 Transaksi');
});

it('initializes the summary filters empty with the all preset', function () {
    Livewire::test(PaymentIndex::class)
        ->assertSet('summaryPreset', 'all')
        ->assertSet('summaryStartDate', '')
        ->assertSet('summaryEndDate', '');
});

it('defaults the summary to all-time no matter the month boundary', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 1.000.000')
        ->assertSee('4 Transaksi');
});

it('today preset limits the summary to payments recorded today', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->assertSet('summaryStartDate', '2026-09-18')
        ->assertSet('summaryEndDate', '2026-09-18')
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('today preset excludes yesterday payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 200.000');
});

it('today preset excludes earlier month payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 100.000');
});

it('today preset excludes previous month payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 400.000');
});

it('yesterday preset limits the summary to payments recorded yesterday', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'yesterday')
        ->assertSet('summaryStartDate', '2026-09-17')
        ->assertSet('summaryEndDate', '2026-09-17')
        ->assertSee('Rp 200.000')
        ->assertSee('1 Transaksi');
});

it('yesterday preset excludes today payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 300.000');
});

it('yesterday preset excludes earlier month payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 100.000');
});

it('yesterday preset excludes previous month payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 400.000');
});

it('this month preset combines all current month income', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'this_month')
        ->assertSet('summaryStartDate', '2026-09-01')
        ->assertSet('summaryEndDate', '2026-09-18')
        ->assertSee('Rp 600.000')
        ->assertSee('3 Transaksi');
});

it('this month preset includes payments recorded today', function () {
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryPayment('2026-08-05', 400000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('this month preset includes payments recorded earlier this month', function () {
    studentSummaryPayment('2026-09-05', 100000);
    studentSummaryPayment('2026-08-05', 400000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 100.000')
        ->assertSee('1 Transaksi');
});

it('this month preset excludes previous month payments', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'this_month')
        ->assertDontSee('Rp 400.000');
});

it('manual start date filters the summary from that day forward', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryStartDate', '2026-09-17')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 500.000')
        ->assertSee('2 Transaksi');
});

it('manual end date filters the summary up to that day', function () {
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryPayment('2026-09-17', 200000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryEndDate', '2026-09-17')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 200.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 300.000');
});

it('manual date range is inclusive on both ends', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryStartDate', '2026-09-01')
        ->set('summaryEndDate', '2026-09-30')
        ->assertSee('Rp 600.000')
        ->assertSee('3 Transaksi');
});

it('applying a manual date switches the preset to manual and uses only that range', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->set('summaryStartDate', '')
        ->set('summaryEndDate', '2026-08-31')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 400.000')
        ->assertSee('1 Transaksi');
});

it('clearing both manual dates returns the summary to all-time', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->set('summaryStartDate', '')
        ->set('summaryEndDate', '')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 1.000.000')
        ->assertSee('4 Transaksi');
});

it('counts a multi-detail payment header as a single transaction', function () {
    $payment = studentSummaryPayment('2026-09-18', 300000);
    $bill = StudentBill::factory()->create(['student_id' => $payment->student_id]);

    foreach ([
        ['amount' => 100000],
        ['amount' => 100000],
        ['amount' => 100000],
    ] as $detail) {
        PaymentDetail::create([
            'payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'payment_type_id' => $bill->payment_type_id,
            'period_month' => $bill->period_month,
            'period_year' => $bill->period_year,
            'amount' => $detail['amount'],
        ]);
    }

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('summarizes the header total_amount without double counting details', function () {
    $payment = studentSummaryPayment('2026-09-18', 50000);
    $bill = StudentBill::factory()->create(['student_id' => $payment->student_id]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => 100000,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => 100000,
    ]);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 50.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 200.000');
});

it('excludes cancelled payments from the student summary', function () {
    studentSummaryPayment('2026-09-18', 900000, ['status' => Payment::STATUS_CANCELLED]);
    studentSummaryPayment('2026-09-18', 300000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 900.000');
});

it('excludes unconverted prospective student payments from the student summary', function () {
    studentSummaryProspectivePayment('2026-09-18', 888888, converted: false);

    studentSummaryPayment('2026-09-18', 300000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 1.188.888');
});

it('excludes daycare payments from the student summary', function () {
    DaycarePayment::factory()->create([
        'payment_date' => '2026-09-18',
        'total_amount' => 777777,
    ]);

    studentSummaryPayment('2026-09-18', 300000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 1.077.777');
});

it('rejects a summary range whose end date precedes the start date', function () {
    studentSummaryDataSet();

    Livewire::test(PaymentIndex::class)
        ->set('summaryStartDate', '2026-09-20')
        ->set('summaryEndDate', '2026-09-10')
        ->assertHasErrors(['summaryEndDate'])
        ->assertSee('Tanggal akhir tidak boleh sebelum tanggal mulai.')
        ->assertSee('Rp 0')
        ->assertSee('0 Transaksi');
});

it('keeps the summary section hidden on the riwayat tab', function () {
    studentSummaryPayment('2026-09-18', 300000);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Riwayat Transaksi')
        ->assertDontSee('Total Pemasukan Siswa')
        ->assertDontSee('Ringkasan Pemasukan Siswa');
});

it('includes a converted prospective Formulir payment in the student summary', function () {
    studentSummaryProspectivePayment('2026-09-18', 350000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 350.000')
        ->assertSee('1 Transaksi');
});

it('combines student income and converted Formulir income into the total', function () {
    studentSummaryPayment('2026-09-18', 1035000);
    studentSummaryProspectivePayment('2026-09-18', 350000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 1.385.000')
        ->assertSee('2 Transaksi');
});

it('counts transaction headers from both sources', function () {
    studentSummaryPayment('2026-09-18', 1035000);
    studentSummaryPayment('2026-09-17', 200000);
    studentSummaryProspectivePayment('2026-09-18', 350000);
    studentSummaryProspectivePayment('2026-09-05', 100000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 1.685.000')
        ->assertSee('4 Transaksi');
});

it('excludes cancelled prospective payments from the student summary', function () {
    studentSummaryProspectivePayment('2026-09-18', 900000, attributes: ['status' => ProspectiveStudentPayment::STATUS_CANCELLED]);
    studentSummaryProspectivePayment('2026-09-18', 350000);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 350.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 900.000');
});

it('today filter applies to both student and converted Formulir sources', function () {
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryProspectivePayment('2026-09-18', 350000);
    studentSummaryProspectivePayment('2026-09-17', 100000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'today')
        ->assertSee('Rp 650.000')
        ->assertSee('2 Transaksi')
        ->assertDontSee('Rp 100.000');
});

it('yesterday filter applies to both student and converted Formulir sources', function () {
    studentSummaryPayment('2026-09-17', 200000);
    studentSummaryProspectivePayment('2026-09-17', 350000);
    studentSummaryProspectivePayment('2026-09-18', 100000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'yesterday')
        ->assertSee('Rp 550.000')
        ->assertSee('2 Transaksi')
        ->assertDontSee('Rp 100.000');
});

it('this month filter applies to both student and converted Formulir sources', function () {
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryPayment('2026-09-05', 100000);
    studentSummaryProspectivePayment('2026-09-17', 350000);
    studentSummaryProspectivePayment('2026-08-05', 800000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 750.000')
        ->assertSee('3 Transaksi')
        ->assertDontSee('Rp 800.000');
});

it('manual date range applies to both student and converted Formulir sources', function () {
    studentSummaryPayment('2026-09-18', 300000);
    studentSummaryPayment('2026-08-05', 400000);
    studentSummaryProspectivePayment('2026-09-17', 350000);
    studentSummaryProspectivePayment('2026-08-01', 250000);

    Livewire::test(PaymentIndex::class)
        ->set('summaryStartDate', '2026-09-01')
        ->set('summaryEndDate', '2026-09-30')
        ->assertSee('Rp 650.000')
        ->assertSee('2 Transaksi')
        ->assertDontSee('Rp 400.000')
        ->assertDontSee('Rp 250.000');
});

it('counts a converted Formulir payment header once despite multiple details', function () {
    $payment = studentSummaryProspectivePayment('2026-09-18', 350000);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'amount' => 150000,
    ]);
    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'amount' => 200000,
    ]);

    Livewire::test(PaymentIndex::class)
        ->assertSee('Rp 350.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 700.000');
});

it('never creates a normal Payment row for a Formulir payment', function () {
    studentSummaryProspectivePayment('2026-09-18', 350000);

    $component = Livewire::test(PaymentIndex::class);

    expect(Payment::query()->count())->toBe(0);
    $component->assertSee('Rp 350.000')
        ->assertSee('1 Transaksi');
});
