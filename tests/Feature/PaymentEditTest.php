<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentCreate;
use App\Livewire\PaymentEdit;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: buat pembayaran aktif dengan detail untuk pengujian edit.
 *
 * @param  array<int, array{type: PaymentType, amount: int}>  $details
 */
function createEditablePayment(array $details = []): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    if (empty($details)) {
        $details = [
            ['type' => $catalog['SPP'], 'amount' => 970000],
            ['type' => $catalog['Jemputan'], 'amount' => 50000],
        ];
    }

    $bills = [];
    foreach ($details as $item) {
        $bill = makeMonthlyBill($student, $item['type'], $item['amount'], 10, 2026);
        $bills[] = ['type' => $item['type'], 'bill' => $bill, 'amount' => $item['amount']];
    }

    $totalAmount = array_sum(array_column($details, 'amount'));

    $payment = Payment::create([
        'receipt_number' => 'KWT-EDIT-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => $totalAmount,
        'payment_method' => 'transfer',
        'description' => 'Pembayaran SPP dan Jemputan Oktober',
        'created_by' => $user->id,
    ]);

    foreach ($bills as $item) {
        $payment->details()->create([
            'bill_id' => $item['bill']->id,
            'payment_type_id' => $item['type']->id,
            'period_month' => $item['bill']->period_month,
            'period_year' => $item['bill']->period_year,
            'amount' => $item['amount'],
            'description' => $item['bill']->period_label,
        ]);
    }

    return $payment;
}

// ---------------------------------------------------------------------------
// 1. Active payment can be edited
// ---------------------------------------------------------------------------

it('active payment can be edited', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertStatus(200)
        ->assertSee('Edit Pembayaran');
});

// ---------------------------------------------------------------------------
// 2. Cancelled payment cannot be edited
// ---------------------------------------------------------------------------

it('cancelled payment cannot be edited', function () {
    $payment = createEditablePayment();
    cancelPaymentDirectly($payment, User::factory()->create());
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// 3. Payment amount can be changed
// ---------------------------------------------------------------------------

it('payment amount can be changed', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $sppBillId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppBillId, 950000)
        ->call('save');

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(1000000.0);
});

// ---------------------------------------------------------------------------
// 4. Payment total recalculates
// ---------------------------------------------------------------------------

it('payment total recalculates after amount change', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $details = $payment->details()->get();
    $sppDetail = $details->first();
    $jemputanDetail = $details->last();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppDetail->bill_id, 900000)
        ->set('selectedBillAmounts.'.$jemputanDetail->bill_id, 40000)
        ->call('save');

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(940000.0);

    expect((float) $payment->details()->sum('amount'))->toBe(940000.0);
});

// ---------------------------------------------------------------------------
// 5. Removing a payment detail works
// ---------------------------------------------------------------------------

it('removing a payment detail works', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $details = $payment->details()->get();
    $jemputanDetail = $details->last();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('removeBill', $jemputanDetail->bill_id)
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(1);
    expect((float) $payment->total_amount)->toBe(970000.0);
});

// ---------------------------------------------------------------------------
// 6. Removing a detail restores bill remaining amount
// ---------------------------------------------------------------------------

it('removing a detail restores bill remaining amount', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $details = $payment->details()->get();
    $jemputanDetail = $details->last();
    $jemputanBill = $jemputanDetail->bill;

    expect($jemputanBill->remaining_amount)->toBe(0.0);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('removeBill', $jemputanDetail->bill_id)
        ->call('save');

    $jemputanBill->refresh();

    expect($jemputanBill->remaining_amount)->toBe(50000.0);
});

// ---------------------------------------------------------------------------
// 7. Adding an existing bill works
// ---------------------------------------------------------------------------

it('adding an existing bill works', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = $payment->student;
    $catalog = manualAddCatalog(8);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('addBill', $osisBill->id)
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(3);
    expect((float) $payment->total_amount)->toBe(1025000.0);

    $osisBill->refresh();
    expect($osisBill->remaining_amount)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// 8. Adding Jemputan works
// ---------------------------------------------------------------------------

it('adding Jemputan works', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-JEM-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 970000,
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

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('addBill', $jemputanBill->id)
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(2);
    expect((float) $payment->total_amount)->toBe(1470000.0);

    $jemputanBill->refresh();
    expect($jemputanBill->remaining_amount)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// 9. Adding Lain-lain works
// ---------------------------------------------------------------------------

it('adding Lain-lain works', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $lainBill = makeMonthlyBill($student, $catalog['Lain-lain'], 100000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-LAIN-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 970000,
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

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('addBill', $lainBill->id)
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(2);
    expect((float) $payment->total_amount)->toBe(1070000.0);
});

// ---------------------------------------------------------------------------
// 10. Student cannot be changed
// ---------------------------------------------------------------------------

it('student cannot be changed', function () {
    $payment = createEditablePayment();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee($payment->student->nama_lengkap)
        ->assertDontSee('wire:model.live="student_id"');
});

// ---------------------------------------------------------------------------
// 11. Bank can be changed
// ---------------------------------------------------------------------------

it('bank can be changed', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $newBank = Bank::factory()->cash()->create();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tunai')
        ->set('bank_id', $newBank->id)
        ->call('save');

    $payment->refresh();

    expect($payment->bank_id)->toBe($newBank->id);
});

// ---------------------------------------------------------------------------
// 12. Date can be changed
// ---------------------------------------------------------------------------

it('date can be changed', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('payment_date', '2026-11-15')
        ->call('save');

    $payment->refresh();

    expect($payment->payment_date->format('Y-m-d'))->toBe('2026-11-15');
});

// ---------------------------------------------------------------------------
// 13. Description can be changed
// ---------------------------------------------------------------------------

it('description can be changed', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('description', 'SPP dan Jemputan bulan Oktober 2026')
        ->call('save');

    $payment->refresh();

    expect($payment->description)->toBe('SPP dan Jemputan bulan Oktober 2026');
});

// ---------------------------------------------------------------------------
// 14. Dashboard displays updated description
// ---------------------------------------------------------------------------

it('dashboard displays updated description after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('description', 'SPP Oktober - Edit')
        ->call('save');

    Livewire::test(Dashboard::class)
        ->assertSee('SPP Oktober - Edit');
});

// ---------------------------------------------------------------------------
// 15. Payment Index displays updated description
// ---------------------------------------------------------------------------

it('payment index displays updated description after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('description', 'SPP Oktober - Updated')
        ->call('save');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('SPP Oktober - Updated');
});

// ---------------------------------------------------------------------------
// 16. Payment status changes from Lunas to Tunggakan
// ---------------------------------------------------------------------------

it('payment status changes from Lunas to Tunggakan when edited below bill total', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    // Initially Lunas (full payment)
    expect($payment->status_label)->toBe('Lunas');

    $details = $payment->details()->get();
    $sppDetail = $details->first();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppDetail->bill_id, 900000)
        ->call('save');

    $payment->refresh();

    expect($payment->status_label)->toBe('Tunggakan');
});

// ---------------------------------------------------------------------------
// 17. Payment status changes from Tunggakan to Lunas
// ---------------------------------------------------------------------------

it('payment status changes from Tunggakan to Lunas when fully paid', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-TUNG-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $sppBill->id,
        'payment_type_id' => $catalog['SPP']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 500000,
        'description' => 'Oktober 2026',
    ]);

    expect($payment->status_label)->toBe('Tunggakan');

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 970000)
        ->call('save');

    $payment->refresh();

    expect($payment->status_label)->toBe('Lunas');
});

// ---------------------------------------------------------------------------
// 18. StudentBill paid_amount recalculates
// ---------------------------------------------------------------------------

it('StudentBill paid_amount recalculates after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $details = $payment->details()->get();
    $sppDetail = $details->first();
    $sppBill = $sppDetail->bill;

    expect($sppBill->paid_amount)->toBe(970000.0);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppDetail->bill_id, 900000)
        ->call('save');

    $sppBill->refresh();

    expect($sppBill->paid_amount)->toBe(900000.0);
});

// ---------------------------------------------------------------------------
// 19. StudentBill remaining_amount recalculates
// ---------------------------------------------------------------------------

it('StudentBill remaining_amount recalculates after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $details = $payment->details()->get();
    $sppDetail = $details->first();
    $sppBill = $sppDetail->bill;

    expect($sppBill->remaining_amount)->toBe(0.0);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppDetail->bill_id, 900000)
        ->call('save');

    $sppBill->refresh();

    expect($sppBill->remaining_amount)->toBe(70000.0);
});

// ---------------------------------------------------------------------------
// 20. PaymentDetail bill_id remains correct
// ---------------------------------------------------------------------------

it('PaymentDetail bill_id remains correct after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $originalBillIds = $payment->details()->pluck('bill_id')->toArray();

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('save');

    $payment->refresh();
    $afterBillIds = $payment->details()->pluck('bill_id')->toArray();

    expect($afterBillIds)->toEqualCanonicalizing($originalBillIds);
});

// ---------------------------------------------------------------------------
// 21. Payment total equals sum of active details
// ---------------------------------------------------------------------------

it('payment total equals sum of active details after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts', [
            $payment->details->first()->bill_id => 950000,
            $payment->details->last()->bill_id => 30000,
        ])
        ->call('save');

    $payment->refresh();

    $detailSum = $payment->details()->sum('amount');

    expect((float) $payment->total_amount)->toBe((float) $detailSum);
    expect((float) $payment->total_amount)->toBe(980000.0);
});

// ---------------------------------------------------------------------------
// 22. Existing payment receipt remains correct
// ---------------------------------------------------------------------------

it('existing payment receipt remains correct after edit', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $originalReceiptNumber = $payment->receipt_number;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts', [
            $payment->details->first()->bill_id => 950000,
        ])
        ->call('removeBill', $payment->details->last()->bill_id)
        ->call('save');

    $payment->refresh();

    expect($payment->receipt_number)->toBe($originalReceiptNumber);
    expect($payment->details()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 23. Cancelled payment remains excluded from financial aggregates
// ---------------------------------------------------------------------------

it('cancelled payment remains excluded from financial aggregates', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();

    $totalBefore = Payment::where('status', Payment::STATUS_ACTIVE)->sum('total_amount');

    cancelPaymentDirectly($payment, $user);

    $totalAfter = Payment::where('status', Payment::STATUS_ACTIVE)->sum('total_amount');

    expect((float) $totalAfter)->toBe((float) $totalBefore - 1020000.0);
});

// ---------------------------------------------------------------------------
// 24. Existing payment creation still works
// ---------------------------------------------------------------------------

it('existing payment creation still works', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id])
        ->set('bank_id', Bank::factory()->create()->id)
        ->set('payment_date', '2026-10-05')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();

    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe(Payment::STATUS_ACTIVE);
});

// ---------------------------------------------------------------------------
// 25. Existing cancellation still works
// ---------------------------------------------------------------------------

it('existing cancellation still works', function () {
    $payment = createEditablePayment();
    $user = User::factory()->create();

    cancelPaymentDirectly($payment, $user);

    $payment->refresh();

    expect($payment->isCancelled())->toBeTrue();
    expect($payment->status_label)->toBe('Dibatalkan');
});

// ---------------------------------------------------------------------------
// 26. Existing StudentBill calculations still work
// ---------------------------------------------------------------------------

it('existing StudentBill calculations still work', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    expect($sppBill->paid_amount)->toBe(0.0);
    expect($sppBill->remaining_amount)->toBe(970000.0);
    expect($sppBill->status)->toBe('unpaid');

    $payment = Payment::create([
        'receipt_number' => 'KWT-CALC-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $sppBill->id,
        'payment_type_id' => $catalog['SPP']->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 500000,
        'description' => 'Oktober 2026',
    ]);

    $sppBill->refresh();

    expect($sppBill->paid_amount)->toBe(500000.0);
    expect($sppBill->remaining_amount)->toBe(470000.0);
    expect($sppBill->status)->toBe('partial');
});
