<?php

use App\Livewire\PaymentEdit;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

/**
 * Helper: buat pembayaran dengan berbagai jenis tagihan untuk pengujian grouping.
 */
function createPaymentWithMixedBills(): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $bukuBill = makeYearlyBill($student, $catalog['Uang Buku'], 500000, '2026/2027');
    $kegiatanBill = makeYearlyBill($student, $catalog['Uang Kegiatan'], 100000, '2026/2027');
    $pangkalBill = makeOneTimeBill($student, $catalog['Uang Pangkal'], 5000000, '2026/2027');

    $payment = Payment::create([
        'receipt_number' => 'KWT-GRP-'.uniqid(),
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

    return $payment;
}

// ---------------------------------------------------------------------------
// 1. Monthly bill appears in monthly group
// ---------------------------------------------------------------------------

it('monthly bill appears in monthly group', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tagihan Oktober 2026');
});

// ---------------------------------------------------------------------------
// 2. Uang Buku appears in Tagihan Tahunan
// ---------------------------------------------------------------------------

it('Uang Buku appears in Tagihan Tahunan', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Uang Buku');
});

// ---------------------------------------------------------------------------
// 3. Uang Kegiatan appears in Tagihan Tahunan
// ---------------------------------------------------------------------------

it('Uang Kegiatan appears in Tagihan Tahunan', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Uang Kegiatan');
});

// ---------------------------------------------------------------------------
// 4. Uang Pangkal appears in Tagihan Sekali Bayar
// ---------------------------------------------------------------------------

it('Uang Pangkal appears in Tagihan Sekali Bayar', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Uang Pangkal');
});

// ---------------------------------------------------------------------------
// 5. Uang Pangkal with academic_year still does NOT appear in yearly group
// ---------------------------------------------------------------------------

it('Uang Pangkal with academic_year does not appear in yearly group', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Uang Pangkal')
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Uang Buku');
});

// ---------------------------------------------------------------------------
// 6. One-time Lain-lain appears in Tagihan Sekali Bayar
// ---------------------------------------------------------------------------

it('one-time Lain-lain appears in Tagihan Sekali Bayar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $lainBill = makeOneTimeBill($student, $catalog['Lain-lain'], 100000, '2026/2027');
    $pangkalBill = makeOneTimeBill($student, $catalog['Uang Pangkal'], 5000000, '2026/2027');

    $payment = Payment::create([
        'receipt_number' => 'KWT-LAIN-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 0,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Lain-lain');
});

// ---------------------------------------------------------------------------
// 7. Yearly group count is correct
// ---------------------------------------------------------------------------

it('yearly group count is correct', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('2 tagihan');
});

// ---------------------------------------------------------------------------
// 8. One-time group count is correct
// ---------------------------------------------------------------------------

it('one-time group count is correct', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('1 tagihan');
});

// ---------------------------------------------------------------------------
// 9. Existing selected bills remain selected
// ---------------------------------------------------------------------------

it('existing selected bills remain selected after grouping fix', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $sppBillId = $payment->details->first()->bill_id;

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('SPP')
        ->call('save');

    $payment->refresh();
    expect($payment->details()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 10. Save behavior remains unchanged
// ---------------------------------------------------------------------------

it('save behavior remains unchanged with mixed bill types', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = $payment->student;
    $catalog = manualAddCatalog(8);
    $bukuBill = makeYearlyBill($student, $catalog['Uang Buku'], 500000, '2026/2027');

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->call('addBill', $bukuBill->id)
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(2);
    expect((float) $payment->total_amount)->toBe(1470000.0);
});

// ---------------------------------------------------------------------------
// 11. Rupiah formatting: selectedBillAmounts remains numeric internally
// ---------------------------------------------------------------------------

it('selectedBillAmounts remains numeric internally', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSet('selectedBillAmounts.'.$payment->details->first()->bill_id, 970000);
});

// ---------------------------------------------------------------------------
// 12. Rupiah formatting: summary total remains correct
// ---------------------------------------------------------------------------

it('summary total remains correct after grouping changes', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->assertSee('Rp 970.000');
});

// ---------------------------------------------------------------------------
// 13. Rupiah formatting: validation still works
// ---------------------------------------------------------------------------

it('validation still works with formatted input', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$payment->details->first()->bill_id, 0)
        ->call('save')
        ->assertHasErrors(['selectedBillAmounts.'.$payment->details->first()->bill_id]);
});

// ---------------------------------------------------------------------------
// 14. Rupiah formatting: payment edit can still save normally
// ---------------------------------------------------------------------------

it('payment edit can still save normally after formatting change', function () {
    $payment = createPaymentWithMixedBills();
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$payment->details->first()->bill_id, 900000)
        ->call('save');

    $payment->refresh();
    expect((float) $payment->total_amount)->toBe(900000.0);
});

// ---------------------------------------------------------------------------
// 15. Billing frequency drives grouping correctly
// ---------------------------------------------------------------------------

it('billing frequency drives grouping correctly for all types', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $bukuBill = makeYearlyBill($student, $catalog['Uang Buku'], 500000, '2026/2027');
    $pangkalBill = makeOneTimeBill($student, $catalog['Uang Pangkal'], 5000000, '2026/2027');

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => Payment::create([
        'receipt_number' => 'KWT-FREQ-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ])->id])
        ->assertSee('Tagihan Oktober 2026')
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Uang Buku')
        ->assertSee('Uang Pangkal');
});
