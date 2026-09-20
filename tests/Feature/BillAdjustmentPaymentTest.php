<?php

use App\Livewire\PaymentCreate;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use Livewire\Livewire;

it('PaymentCreate menampilkan tagihan efektif setelah diskon', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 5000000);

    makeActiveSetting($student, $spp);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 2000000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 2000000,
    ]);

    $component = Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'))
        ->firstWhere('id', $bill->id);

    expect($outstanding)->not->toBeNull()
        ->and($outstanding['amount'])->toBe(4500000.0)
        ->and($outstanding['paid_amount'])->toBe(2000000.0)
        ->and($outstanding['remaining_amount'])->toBe(2500000.0);
});

it('membayar nominal efektif menandai tagihan lunas lewat pemilihan tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::actingAs($user);

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 5000000);

    makeActiveSetting($student, $spp);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    $component = Livewire::test(PaymentCreate::class);

    $component->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id]);

    expect($component->get('selectedBillAmounts'))->toBe([$bill->id => 4500000]);

    $component->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->where('bill_id', $bill->id)
        ->first();

    $bill->refresh();

    expect($detail)->not->toBeNull()
        ->and($bill->isSettled())->toBeTrue()
        ->and((float) $bill->paid_amount)->toBe(4500000.0)
        ->and((float) $bill->remaining_amount)->toBe(0.0);
});

it('pembayaran menghormati tagihan efektif setelah diskon', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::actingAs($user);

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 5000000);

    makeActiveSetting($student, $spp);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->first();

    $bill->refresh();

    expect($detail)->not->toBeNull()
        ->and($detail->bill_id)->toBe($bill->id)
        ->and($bill->isSettled())->toBeTrue();
});

it('kwitansi tetap berfungsi setelah pembayaran tagihan berdiskon', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 5000000);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000777',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-15',
        'total_amount' => 4500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 4500000,
    ]);

    $bill->refresh();

    expect($bill->isSettled())->toBeTrue();

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('KWT-2026-000777')
        ->assertSee('No. Kwitansi');
});
