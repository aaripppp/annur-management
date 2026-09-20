<?php

use App\Livewire\DaycarePaymentEntry;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Daycare payment summary cards + date filter
 |--------------------------------------------------------------------------
 |
 | The Daycare > Pembayaran page shows two aggregate cards (Total Pemasukan
 | Daycare and Jumlah Transaksi) driven by a date filter with quick presets
 | and a manual date range. The recap is scoped to the daycare payments table
 | only, sums the payment header total_amount once per transaction, and never
 | counts student or prospective-student payments.
 |
 */

function daycareSummaryPayment(string $date, int|float $totalAmount, array $attributes = []): DaycarePayment
{
    return DaycarePayment::factory()->create([
        'daycare_child_id' => DaycareChild::factory(),
        'payment_date' => $date,
        'total_amount' => $totalAmount,
        ...$attributes,
    ]);
}

function daycareSummaryDataSet(): void
{
    daycareSummaryPayment('2026-09-18', 300000, ['receipt_number' => 'KWT-SUM-A']);
    daycareSummaryPayment('2026-09-17', 200000, ['receipt_number' => 'KWT-SUM-B']);
    daycareSummaryPayment('2026-09-05', 100000, ['receipt_number' => 'KWT-SUM-C']);
    daycareSummaryPayment('2026-08-05', 400000, ['receipt_number' => 'KWT-SUM-D']);
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('renders the daycare summary section with both cards and a default empty total', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Ringkasan Pemasukan Daycare')
        ->assertSee('Total Pemasukan Daycare')
        ->assertSee('Jumlah Transaksi')
        ->assertSee('Rp 0')
        ->assertSee('0 Transaksi')
        ->assertSeeHtml('data-testid="daycare-summary-total-amount"')
        ->assertSeeHtml('data-testid="daycare-summary-total-count"')
        ->assertSet('summaryPreset', 'all');
});

it('shows the total daycare income across all payments by default', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 1.000.000');
});

it('shows the total transaction count across all payments by default', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('4 Transaksi');
});

it('initializes the summary filters empty with the all preset', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->assertSet('summaryPreset', 'all')
        ->assertSet('summaryStartDate', '')
        ->assertSet('summaryEndDate', '');
});

it('defaults the summary to all-time no matter the month boundary', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 1.000.000')
        ->assertSee('4 Transaksi');
});

it('today preset limits the summary to payments recorded today', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->assertSet('summaryStartDate', '2026-09-18')
        ->assertSet('summaryEndDate', '2026-09-18')
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('today preset excludes yesterday payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 200.000');
});

it('today preset excludes earlier month payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 100.000');
});

it('today preset excludes previous month payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->assertDontSee('Rp 400.000');
});

it('yesterday preset limits the summary to payments recorded yesterday', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'yesterday')
        ->assertSet('summaryStartDate', '2026-09-17')
        ->assertSet('summaryEndDate', '2026-09-17')
        ->assertSee('Rp 200.000')
        ->assertSee('1 Transaksi');
});

it('yesterday preset excludes today payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 300.000');
});

it('yesterday preset excludes earlier month payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 100.000');
});

it('yesterday preset excludes previous month payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'yesterday')
        ->assertDontSee('Rp 400.000');
});

it('this month preset combines all current month income', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'this_month')
        ->assertSet('summaryStartDate', '2026-09-01')
        ->assertSet('summaryEndDate', '2026-09-18')
        ->assertSee('Rp 600.000')
        ->assertSee('3 Transaksi');
});

it('this month preset includes payments recorded today', function () {
    daycareSummaryPayment('2026-09-18', 300000, ['receipt_number' => 'KWT-SUM-A']);
    daycareSummaryPayment('2026-08-05', 400000, ['receipt_number' => 'KWT-SUM-D']);

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('this month preset includes payments recorded yesterday', function () {
    daycareSummaryPayment('2026-09-17', 200000, ['receipt_number' => 'KWT-SUM-B']);
    daycareSummaryPayment('2026-08-05', 400000, ['receipt_number' => 'KWT-SUM-D']);

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 200.000')
        ->assertSee('1 Transaksi');
});

it('this month preset includes payments recorded earlier this month', function () {
    daycareSummaryPayment('2026-09-05', 100000, ['receipt_number' => 'KWT-SUM-C']);
    daycareSummaryPayment('2026-08-05', 400000, ['receipt_number' => 'KWT-SUM-D']);

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'this_month')
        ->assertSee('Rp 100.000')
        ->assertSee('1 Transaksi');
});

it('this month preset excludes previous month payments', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'this_month')
        ->assertDontSee('Rp 400.000');
});

it('manual start date filters the summary from that day forward', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryStartDate', '2026-09-17')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 500.000')
        ->assertSee('2 Transaksi');
});

it('manual end date filters the summary up to that day', function () {
    daycareSummaryPayment('2026-09-18', 300000, ['receipt_number' => 'KWT-SUM-A']);
    daycareSummaryPayment('2026-09-17', 200000, ['receipt_number' => 'KWT-SUM-B']);

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryEndDate', '2026-09-17')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 200.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 300.000');
});

it('manual date range is inclusive on both ends', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryStartDate', '2026-09-01')
        ->set('summaryEndDate', '2026-09-30')
        ->assertSee('Rp 600.000')
        ->assertSee('3 Transaksi');
});

it('applying a manual date switches the preset to manual and uses only that range', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->set('summaryStartDate', '')
        ->set('summaryEndDate', '2026-08-31')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 400.000')
        ->assertSee('1 Transaksi');
});

it('clearing both manual dates returns the summary to all-time', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryPreset', 'today')
        ->set('summaryStartDate', '')
        ->set('summaryEndDate', '')
        ->assertSet('summaryPreset', 'manual')
        ->assertSee('Rp 1.000.000')
        ->assertSee('4 Transaksi');
});

it('counts a multi-detail payment header as a single transaction', function () {
    $payment = daycareSummaryPayment('2026-09-18', 300000);

    foreach ([
        ['description' => 'Kegiatan Pagi', 'amount' => 100000],
        ['description' => 'Kegiatan Siang', 'amount' => 100000],
        ['description' => 'Kegiatan Sore', 'amount' => 100000],
    ] as $detail) {
        DaycarePaymentDetail::factory()->create([
            'daycare_payment_id' => $payment->id,
            ...$detail,
        ]);
    }

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('summarizes the header total_amount without double counting details', function () {
    $payment = daycareSummaryPayment('2026-09-18', 50000);

    DaycarePaymentDetail::factory()->create(['daycare_payment_id' => $payment->id, 'amount' => 100000]);
    DaycarePaymentDetail::factory()->create(['daycare_payment_id' => $payment->id, 'amount' => 100000]);

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 50.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 200.000');
});

it('has no cancelled status concept on daycare payments to exclude', function () {
    expect(Schema::hasColumn('daycare_payments', 'status'))->toBeFalse();

    daycareSummaryPayment('2026-09-18', 300000);

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});

it('excludes student payments from the daycare summary', function () {
    $student = Student::factory()->create();

    Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-SUM',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-09-18',
        'total_amount' => 999999,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);

    daycareSummaryPayment('2026-09-18', 300000);

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 1.299.999');
});

it('excludes prospective student payments from the daycare summary', function () {
    ProspectiveStudentPayment::factory()->create([
        'payment_date' => '2026-09-18',
        'total_amount' => 888888,
    ]);

    daycareSummaryPayment('2026-09-18', 300000);

    Livewire::test(DaycarePaymentEntry::class)
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi')
        ->assertDontSee('Rp 1.188.888');
});

it('rejects a summary range whose end date precedes the start date', function () {
    daycareSummaryDataSet();

    Livewire::test(DaycarePaymentEntry::class)
        ->set('summaryStartDate', '2026-09-20')
        ->set('summaryEndDate', '2026-09-10')
        ->assertHasErrors(['summaryEndDate'])
        ->assertSee('Tanggal akhir tidak boleh sebelum tanggal mulai.')
        ->assertSee('Rp 0')
        ->assertSee('0 Transaksi');
});

it('keeps the summary section visible on the riwayat tab', function () {
    daycareSummaryPayment('2026-09-18', 300000);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Total Pemasukan Daycare')
        ->assertSee('Rp 300.000')
        ->assertSee('1 Transaksi');
});
