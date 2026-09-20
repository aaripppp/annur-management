<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function makeDashboardPayment(Student $student, int $amount, ?Bank $bank = null): Payment
{
    $user = User::factory()->create();
    $bank ??= Bank::factory()->create();
    $billType = PaymentType::firstOrCreate(['name' => 'SPP'], [
        'is_active' => true,
        'is_auto_enrolled' => true,
        'is_required' => true,
    ]);

    $bill = StudentBill::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $billType->id, 'period_month' => 8, 'period_year' => 2026],
        ['amount' => $amount]
    );

    $payment = Payment::create([
        'receipt_number' => 'KWT-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => now()->toDateString(),
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $billType->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => $amount,
    ]);

    return $payment;
}

// ---------------------------------------------------------------------------
// 1–3: Pembayaran dibatalkan masih tampil di tabel (visible), status Dibatalkan
// ---------------------------------------------------------------------------

it('dashboard menampilkan pembayaran yang dibatalkan dalam tabel transaksi terbaru', function () {
    $student = makeBillStudent(8);
    $payment = makeDashboardPayment($student, 500000);

    $payment->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Pembatalan uji',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSee('Dibatalkan');
});

it('dashboard menampilkan status Lunas untuk pembayaran aktif', function () {
    $student = makeBillStudent(8);
    $payment = makeDashboardPayment($student, 1000000);

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSee('Lunas');
});

it('dashboard tidak menampilkan Lunas untuk pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    $payment = makeDashboardPayment($student, 500000);

    $payment->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Dibatalkan',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSee('Dibatalkan')
        ->assertDontSee('Lunas');
});

// ---------------------------------------------------------------------------
// 4–5: Total pemasukan / total transaksi mengecualikan yang dibatalkan
// ---------------------------------------------------------------------------

it('total pemasukan mengecualikan pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    $active = makeDashboardPayment($student, 1500000);
    $cancelled = makeDashboardPayment($student, 800000);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji total',
    ]);

    $totalActive = 1500000;

    Livewire::test(Dashboard::class)
        ->assertSee('Rp '.number_format($totalActive, 0, ',', '.'))
        ->assertDontSee('Rp '.number_format(1500000 + 800000, 0, ',', '.'));
});

it('total transaksi mengecualikan pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    makeDashboardPayment($student, 1000000);
    $cancelled = makeDashboardPayment($student, 500000);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('1 Transaksi');
});

// ---------------------------------------------------------------------------
// 6–7: Saldo bank dan total per bank mengecualikan pembayaran dibatalkan
// ---------------------------------------------------------------------------

it('saldo bank mengecualikan pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    $bank = Bank::factory()->create(['name' => 'BSI']);

    $active = makeDashboardPayment($student, 1000000, $bank);
    $cancelled = makeDashboardPayment($student, 500000, $bank);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji bank',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Rp '.number_format(1000000, 0, ',', '.'))
        ->assertDontSee('Rp '.number_format(1500000, 0, ',', '.'));
});

it('total per bank benar untuk kombinasi aktif dan dibatalkan', function () {
    $student = makeBillStudent(8);
    $bankBSI = Bank::factory()->create(['name' => 'BSI']);
    $bankBCA = Bank::factory()->create(['name' => 'BCA']);

    makeDashboardPayment($student, 1000000, $bankBSI);
    $cancelledBSI = makeDashboardPayment($student, 500000, $bankBSI);

    $cancelledBSI->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji',
    ]);

    makeDashboardPayment($student, 750000, $bankBCA);

    Livewire::test(Dashboard::class)
        ->assertSee('Rp '.number_format(1000000, 0, ',', '.'))
        ->assertSee('Rp '.number_format(750000, 0, ',', '.'));
});

// ---------------------------------------------------------------------------
// 8: Total harian/bulanan — tidak ada komponen dedicated; query langsung
// ---------------------------------------------------------------------------

it('query dashboard menghitung total pemasukan hanya dari pembayaran aktif', function () {
    $student = makeBillStudent(8);

    $a1 = makeDashboardPayment($student, 1000000);
    $a2 = makeDashboardPayment($student, 250000);
    $c = makeDashboardPayment($student, 500000);

    $c->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Query test',
    ]);

    $directSum = Payment::where('status', Payment::STATUS_ACTIVE)->sum('total_amount');

    expect((float) $directSum)->toBe(1250000.0);
});

// ---------------------------------------------------------------------------
// 9: StudentBill paid_amount mengecualikan detail dari pembayaran dibatalkan
// ---------------------------------------------------------------------------

it('student bill paid_amount mengecualikan detail dari pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    $payment = makeDashboardPayment($student, 970000);
    $studentBill = $payment->details()->first()->bill;

    expect($studentBill->refresh()->paid_amount)->toBe(970000.0);

    $payment->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji',
    ]);

    $billFresh = StudentBill::find($studentBill->id);

    expect($billFresh->paid_amount)->toBe(0.0)
        ->and($billFresh->remaining_amount)->toBe(970000.0);
});

// ---------------------------------------------------------------------------
// 10–11: Payment Index — baris dibatalkan tampil, filter status benar
// ---------------------------------------------------------------------------

it('payment index menampilkan baris pembayaran yang dibatalkan', function () {
    $student = makeBillStudent(8);
    $active = makeDashboardPayment($student, 1000000);
    $cancelled = makeDashboardPayment($student, 500000);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji index',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($active->receipt_number)
        ->assertSee($cancelled->receipt_number)
        ->assertSee('Dibatalkan');
});

it('filter status cancelled pada payment index hanya menampilkan pembayaran dibatalkan', function () {
    $student = makeBillStudent(8);
    $active = makeDashboardPayment($student, 1000000);
    $cancelled = makeDashboardPayment($student, 500000);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji filter',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'cancelled')
        ->assertSee($cancelled->receipt_number)
        ->assertDontSee($active->receipt_number);
});

it('filter status lunas pada payment index tidak menampilkan pembayaran dibatalkan', function () {
    $student = makeBillStudent(8);
    $active = makeDashboardPayment($student, 1000000);
    $cancelled = makeDashboardPayment($student, 500000);

    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Uji lunas filter',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'lunas')
        ->assertSee($active->receipt_number)
        ->assertDontSee($cancelled->receipt_number);
});

// ---------------------------------------------------------------------------
// 12: Skenario gabungan aktif + dibatalkan — total benar
// ---------------------------------------------------------------------------

it('total pemasukan gabungan aktif dan dibatalkan benar', function () {
    $student = makeBillStudent(8);
    $bank = Bank::factory()->create(['name' => 'Mandiri']);

    $p1 = makeDashboardPayment($student, 1000000, $bank);
    $p2 = makeDashboardPayment($student, 500000, $bank);
    $p3 = makeDashboardPayment($student, 750000, $bank);

    $p2->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => User::factory()->create()->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Gabungan',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Rp '.number_format(1750000, 0, ',', '.'))
        ->assertSee('2 Transaksi');
});
