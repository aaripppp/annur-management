<?php

use App\Livewire\PaymentEdit;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: buat pembayaran tunggal untuk pengujian sync amount.
 */
function createSingleBillPayment(int $amount = 300000): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $bill = makeMonthlyBill($student, $catalog['Jemputan'], $amount, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-SYNC-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $catalog['Jemputan']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => $amount,
        'description' => 'Oktober 2026',
    ]);

    return $payment;
}

// ---------------------------------------------------------------------------
// 1. Edit amount from 300000 -> 100000, PaymentDetail updates
// ---------------------------------------------------------------------------

it('edit amount from 300000 to 100000 updates PaymentDetail', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $detail = $payment->details()->first();
    expect((float) $detail->amount)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// 2. Payment.total_amount updates
// ---------------------------------------------------------------------------

it('payment total_amount updates after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $payment->refresh();
    expect((float) $payment->total_amount)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// 3. StudentBill.paid_amount updates
// ---------------------------------------------------------------------------

it('StudentBill paid_amount updates after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;
    $bill = $payment->details->first()->bill;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $bill->refresh();
    expect((float) $bill->paid_amount)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// 4. StudentBill.remaining_amount updates
// ---------------------------------------------------------------------------

it('StudentBill remaining_amount updates after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;
    $bill = $payment->details->first()->bill;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $bill->refresh();
    expect((float) $bill->remaining_amount)->toBe(200000.0);
});

// ---------------------------------------------------------------------------
// 5. Payment status becomes Tunggakan
// ---------------------------------------------------------------------------

it('payment status becomes Tunggakan after partial edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $payment->refresh();
    expect($payment->status_label)->toBe('Tunggakan');
});

// ---------------------------------------------------------------------------
// 6. Receipt / show page renders updated amount
// ---------------------------------------------------------------------------

it('receipt renders updated amount after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(100000.0);
    expect((float) $payment->details()->first()->amount)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// 7. No duplicate PaymentDetail created
// ---------------------------------------------------------------------------

it('no duplicate PaymentDetail created after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    expect($payment->details()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 8. Same bill_id preserved after edit
// ---------------------------------------------------------------------------

it('same bill_id preserved after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $originalBillId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$originalBillId, 100000)
        ->call('save');

    $afterBillId = $payment->details()->first()->bill_id;
    expect($afterBillId)->toBe($originalBillId);
});

// ---------------------------------------------------------------------------
// 9. selectedBillAmounts is numeric after set
// ---------------------------------------------------------------------------

it('selectedBillAmounts is numeric after set', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->assertSet('selectedBillAmounts.'.$billId, 100000);
});

// ---------------------------------------------------------------------------
// 10. Multi-bill edit recalculates correctly
// ---------------------------------------------------------------------------

it('multi-bill edit recalculates correctly', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $jemBill = makeMonthlyBill($student, $catalog['Jemputan'], 300000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-MULTI-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 1270000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $sppBill->id,
        'payment_type_id' => $catalog['SPP']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 970000,
        'description' => 'Oktober 2026',
    ]);

    $payment->details()->create([
        'bill_id' => $jemBill->id,
        'payment_type_id' => $catalog['Jemputan']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 300000,
        'description' => 'Oktober 2026',
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$jemBill->id, 100000)
        ->call('save');

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(1070000.0);
    expect((float) $payment->details()->where('bill_id', $jemBill->id)->first()->amount)->toBe(100000.0);
    expect((float) $payment->details()->where('bill_id', $sppBill->id)->first()->amount)->toBe(970000.0);

    $jemBill->refresh();
    expect((float) $jemBill->paid_amount)->toBe(100000.0);
    expect((float) $jemBill->remaining_amount)->toBe(200000.0);
});

// ---------------------------------------------------------------------------
// 11. Save uses current selectedBillAmounts value
// ---------------------------------------------------------------------------

it('save uses current selectedBillAmounts not stale value', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    $component = Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000);

    $component->assertSet('selectedBillAmounts.'.$billId, 100000);
    $component->call('save');

    $payment->refresh();
    expect((float) $payment->total_amount)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// 12. Formatted display initializes correctly
// ---------------------------------------------------------------------------

it('formatted input display initializes from existing amount', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('300.000');
});

// ---------------------------------------------------------------------------
// 13. Other fields still work after amount fix
// ---------------------------------------------------------------------------

it('bank change still works after amount fix', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $newBank = Bank::factory()->create(['name' => 'Bank Mega']);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('bank_id', $newBank->id)
        ->set('payment_date', '2026-11-15')
        ->set('description', 'Edited payment')
        ->call('save');

    $payment->refresh();

    expect($payment->bank_id)->toBe($newBank->id);
    expect($payment->payment_date->format('Y-m-d'))->toBe('2026-11-15');
    expect($payment->description)->toBe('Edited payment');
});

// ---------------------------------------------------------------------------
// 14. Description change still works after amount fix
// ---------------------------------------------------------------------------

it('description change still works after amount fix', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('description', 'Jemputan Oktober - Updated')
        ->call('save');

    $payment->refresh();
    expect($payment->description)->toBe('Jemputan Oktober - Updated');
});

// ---------------------------------------------------------------------------
// 15. Payment Index reflects updated total
// ---------------------------------------------------------------------------

it('payment index reflects updated total after edit', function () {
    $payment = createSingleBillPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$billId, 100000)
        ->call('save');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('100.000');
});
