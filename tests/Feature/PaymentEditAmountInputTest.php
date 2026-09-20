<?php

use App\Livewire\PaymentEdit;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: buat pembayaran dengan satu bill untuk pengujian amount input.
 */
function createAmountTestPayment(int $amount = 300000): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $bill = makeMonthlyBill($student, $catalog['SPP'], $amount, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-AMT-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $catalog['SPP']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => $amount,
        'description' => 'Oktober 2026',
    ]);

    return $payment;
}

// ---------------------------------------------------------------------------
// 1. Existing amount initializes numeric state
// ---------------------------------------------------------------------------

it('initializes numeric selectedBillAmounts from existing amount', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSet("selectedBillAmounts.{$billId}", 300000);
});

// ---------------------------------------------------------------------------
// 2. Alpine $wire.set updates selectedBillAmounts directly
// ---------------------------------------------------------------------------

it('$wire.set updates selectedBillAmounts with numeric value', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->assertSet("selectedBillAmounts.{$billId}", 100000);
});

// ---------------------------------------------------------------------------
// 3. selectedBillAmounts stays numeric (no formatting)
// ---------------------------------------------------------------------------

it('selectedBillAmounts remains pure numeric after set', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->assertSet("selectedBillAmounts.{$billId}", 100000);
});

// ---------------------------------------------------------------------------
// 4. Save works with direct numeric set
// ---------------------------------------------------------------------------

it('saves correctly with direct numeric set', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->call('save');

    $detail = PaymentDetail::where('payment_id', $payment->id)->first();
    expect((int) $detail->amount)->toBe(100000);
});

// ---------------------------------------------------------------------------
// 5. PaymentDetail.amount = 100000 after save
// ---------------------------------------------------------------------------

it('saves PaymentDetail.amount as 100000', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->call('save');

    $detail = PaymentDetail::where('payment_id', $payment->id)->first();
    expect((int) $detail->amount)->toBe(100000);
});

// ---------------------------------------------------------------------------
// 6. Payment.total_amount = 100000 after save
// ---------------------------------------------------------------------------

it('saves Payment.total_amount as 100000', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->call('save');

    $payment->refresh();
    expect((int) $payment->total_amount)->toBe(100000);
});

// ---------------------------------------------------------------------------
// 7. Multi-bill total remains correct
// ---------------------------------------------------------------------------

it('multi-bill total remains correct after amount change', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-MULTI-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 1020000,
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
        'bill_id' => $jemputanBill->id,
        'payment_type_id' => $catalog['Jemputan']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 50000,
        'description' => 'Oktober 2026',
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$sppBill->id}", 900000)
        ->set("selectedBillAmounts.{$jemputanBill->id}", 40000)
        ->assertSet("selectedBillAmounts.{$sppBill->id}", 900000)
        ->assertSet("selectedBillAmounts.{$jemputanBill->id}", 40000)
        ->call('save');

    $payment->refresh();
    expect((int) $payment->total_amount)->toBe(940000);
});

// ---------------------------------------------------------------------------
// 8. Set to 0 results in validation error on save
// ---------------------------------------------------------------------------

it('validation rejects zero amount on save', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 0)
        ->call('save')
        ->assertHasErrors();
});

// ---------------------------------------------------------------------------
// 9. Existing formatted display renders in HTML
// ---------------------------------------------------------------------------

it('renders formatted amount in HTML for Alpine initial display', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('300.000');
});

// ---------------------------------------------------------------------------
// 10. Same bill_id preserved after edit
// ---------------------------------------------------------------------------

it('same bill_id preserved after amount change', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $originalBillId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$originalBillId}", 100000)
        ->call('save');

    $afterBillId = $payment->details()->first()->bill_id;
    expect($afterBillId)->toBe($originalBillId);
});

// ---------------------------------------------------------------------------
// 11. No duplicate PaymentDetail created
// ---------------------------------------------------------------------------

it('no duplicate PaymentDetail created after amount change', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000)
        ->call('save');

    expect($payment->details()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 12. Save uses current selectedBillAmounts value
// ---------------------------------------------------------------------------

it('save uses current selectedBillAmounts value', function () {
    $payment = createAmountTestPayment(300000);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $billId = $payment->details->first()->bill_id;

    $component = Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set("selectedBillAmounts.{$billId}", 100000);

    $component->assertSet("selectedBillAmounts.{$billId}", 100000);
    $component->call('save');

    $payment->refresh();
    expect((float) $payment->total_amount)->toBe(100000.0);
});
