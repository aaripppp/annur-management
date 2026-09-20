<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentCorrection;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// PAYMENT SETTLEMENT STATUS — SINGLE SOURCE OF TRUTH
// ---------------------------------------------------------------------------

it('active payment with fully paid bill returns Lunas', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    expect($payment->settlement_status)->toBe('lunas')
        ->and($payment->status_label)->toBe('Lunas');
});

it('active payment with partially paid bill returns Tunggakan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 500000]);

    expect($payment->settlement_status)->toBe('tunggakan')
        ->and($payment->status_label)->toBe('Tunggakan');
});

it('active payment with unpaid remainder returns Tunggakan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $payment = createPaymentFromBills($student, [$jemputanBill->id => 50000]);

    expect($payment->settlement_status)->toBe('lunas')
        ->and($payment->status_label)->toBe('Lunas');

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id])
        ->set('selectedBillAmounts.'.$jemputanBill->id, 45000)
        ->call('gotoConfirm')
        ->set('reason', 'Koreksi nominal')
        ->call('save');

    $payment->refresh();

    expect($payment->settlement_status)->toBe('tunggakan')
        ->and($payment->status_label)->toBe('Tunggakan');
});

it('cancelled payment returns Dibatalkan regardless of bill status', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    cancelPaymentDirectly($payment, $user);

    expect($payment->status_label)->toBe('Dibatalkan');
});

it('correction from 50k to 45k changes status_label from Lunas to Tunggakan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $payment = createPaymentFromBills($student, [$jemputanBill->id => 50000]);

    expect($payment->status_label)->toBe('Lunas');

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id])
        ->set('selectedBillAmounts.'.$jemputanBill->id, 45000)
        ->call('gotoConfirm')
        ->set('reason', 'Koreksi nominal')
        ->call('save');

    $payment->refresh();

    expect($payment->settlement_status)->toBe('tunggakan')
        ->and($payment->status_label)->toBe('Tunggakan');

    $jemputanBill->refresh();

    expect($jemputanBill->paid_amount)->toBe(45000.0)
        ->and($jemputanBill->remaining_amount)->toBe(5000.0);
});

it('multi-bill payment fully paid returns Lunas', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    $payment = createPaymentFromBills($student, [
        $sppBill->id => 970000,
        $osisBill->id => 5000,
    ]);

    expect($payment->settlement_status)->toBe('lunas')
        ->and($payment->status_label)->toBe('Lunas');
});

it('multi-bill payment partially paid returns Tunggakan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    $payment = createPaymentFromBills($student, [
        $sppBill->id => 970000,
        $osisBill->id => 3000,
    ]);

    expect($payment->settlement_status)->toBe('tunggakan')
        ->and($payment->status_label)->toBe('Tunggakan');
});

it('cancelled payment is never treated as Lunas', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    expect($sppBill->refresh()->isSettled())->toBeTrue();

    cancelPaymentDirectly($payment, $user);

    $sppBill->refresh();

    expect($sppBill->paid_amount)->toBe(0.0)
        ->and($sppBill->remaining_amount)->toBe(970000.0)
        ->and($sppBill->isSettled())->toBeFalse();

    expect($payment->refresh()->status_label)->toBe('Dibatalkan');
});

// ---------------------------------------------------------------------------
// VIEW CONSISTENCY — DASHBOARD / INDEX / SHOW
// ---------------------------------------------------------------------------

it('dashboard shows correct status label for active fully paid payment', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(Dashboard::class)
        ->assertSee('Lunas')
        ->assertSee($payment->receipt_number);
});

it('dashboard shows Tunggakan for active partially paid payment', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 500000]);

    Livewire::test(Dashboard::class)
        ->assertSee('Tunggakan')
        ->assertSee($payment->receipt_number);
});

it('dashboard shows Dibatalkan for cancelled payment', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);
    cancelPaymentDirectly($payment, $user);

    Livewire::test(Dashboard::class)
        ->assertSee('Dibatalkan')
        ->assertSee($payment->receipt_number);
});

it('dashboard status matches payment show after correction', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $payment = createPaymentFromBills($student, [$jemputanBill->id => 50000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id])
        ->set('selectedBillAmounts.'.$jemputanBill->id, 45000)
        ->call('gotoConfirm')
        ->set('reason', 'Koreksi nominal')
        ->call('save');

    $payment->refresh();

    expect($payment->status_label)->toBe('Tunggakan');

    Livewire::test(Dashboard::class)
        ->assertSeeHtml('Tunggakan')
        ->assertSee($payment->receipt_number);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSeeHtml('Tunggakan')
        ->assertSee($payment->receipt_number);
});

it('payment index shows correct status label', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $activePayment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $partialPayment = createPaymentFromBills($student, [$jemputanBill->id => 45000]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Lunas')
        ->assertSee($activePayment->receipt_number);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Tunggakan')
        ->assertSee($partialPayment->receipt_number);
});

// ---------------------------------------------------------------------------
// FINANCIAL INTEGRITY — CORRECTION FLOW
// ---------------------------------------------------------------------------

it('existing financial aggregation remains correct after correction', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    expect($sppBill->refresh()->isSettled())->toBeTrue()
        ->and((float) $sppBill->paid_amount)->toBe(970000.0)
        ->and((float) $sppBill->remaining_amount)->toBe(0.0);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 500000)
        ->call('gotoConfirm')
        ->set('reason', 'Nominal salah')
        ->call('save');

    $payment->refresh();
    $sppBill->refresh();

    expect((float) $payment->total_amount)->toBe(500000.0)
        ->and((float) $sppBill->paid_amount)->toBe(500000.0)
        ->and((float) $sppBill->remaining_amount)->toBe(470000.0)
        ->and($sppBill->status)->toBe('partial')
        ->and($payment->settlement_status)->toBe('tunggakan')
        ->and($payment->status_label)->toBe('Tunggakan');
});

it('existing payment creation flow still works correctly', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $bank = Bank::factory()->create();

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->settlement_status)->toBe('lunas')
        ->and($payment->status_label)->toBe('Lunas');

    $sppBill->refresh();

    expect($sppBill->isSettled())->toBeTrue();
});
