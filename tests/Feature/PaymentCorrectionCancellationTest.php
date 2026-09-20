<?php

use App\Livewire\PaymentCorrection;
use App\Livewire\PaymentCreate;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

/**
 * Buat pembayaran langsung berisi satu detail per tagihan [bill_id => nominal].
 */
function createPaymentFromBills(Student $student, array $billAmounts): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => array_sum($billAmounts),
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    foreach ($billAmounts as $billId => $amount) {
        $bill = StudentBill::find($billId);

        $payment->details()->create([
            'bill_id' => $bill->id,
            'payment_type_id' => $bill->payment_type_id,
            'period_month' => $bill->period_month,
            'period_year' => $bill->period_year,
            'academic_year' => $bill->academic_year,
            'amount' => $amount,
            'description' => $bill->period_label,
        ]);
    }

    return $payment;
}

function cancelPaymentDirectly(Payment $payment, User $user): void
{
    $payment->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Pembatalan uji',
    ]);
}

// ---------------------------------------------------------------------------
// KOREKSI: RINCIAN PEMBAYARAN
// ---------------------------------------------------------------------------

it('koreksi menghapus tagihan yang salah dari rincian pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000, $osisBill->id => 5000]);

    expect($osisBill->refresh()->remaining_amount)->toBe(0.0);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Salah centang tagihan OSIS')
        ->call('save')
        ->assertRedirect(route('pembayaran.show', $payment->id));

    $payment->refresh();

    expect($payment->details()->count())->toBe(1)
        ->and($payment->details()->first()->bill_id)->toBe($sppBill->id)
        ->and((float) $payment->total_amount)->toBe(970000.0);

    $osisBill->refresh();
    $sppBill->refresh();

    expect($osisBill->remaining_amount)->toBe(5000.0)
        ->and($osisBill->status)->toBe(StudentBill::STATUS_UNPAID)
        ->and($sppBill->isSettled())->toBeTrue();
});

it('koreksi menghapus rincian yang tidak lagi dipilih dari database', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $ekskulBill = makeMonthlyBill($student, $catalog['Ekskul'], 52000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000, $ekskulBill->id => 52000]);
    $removedDetailId = $payment->details()->where('bill_id', $ekskulBill->id)->first()->id;

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Hapus Ekskul')
        ->call('save');

    expect(PaymentDetail::find($removedDetailId))->toBeNull();
});

it('koreksi menyesuaikan nominal detail pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 500000)
        ->call('gotoConfirm')
        ->set('reason', 'Nominal salah, seharusnya 500.000')
        ->call('save');

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(500000.0)
        ->and((float) $payment->details()->first()->amount)->toBe(500000.0);

    $sppBill->refresh();

    expect($sppBill->remaining_amount)->toBe(470000.0)
        ->and($sppBill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('koreksi memperbaiki bank penerima', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);
    $newBank = Bank::factory()->create();

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('bank_id', $newBank->id)
        ->call('gotoConfirm')
        ->set('reason', 'Salah bank tujuan')
        ->call('save');

    expect($payment->refresh()->bank_id)->toBe($newBank->id);
});

it('koreksi memperbaiki tanggal pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('payment_date', '2026-09-01')
        ->call('gotoConfirm')
        ->set('reason', 'Salah tanggal')
        ->call('save');

    expect($payment->refresh()->payment_date->format('Y-m-d'))->toBe('2026-09-01');
});

it('koreksi tidak mengubah siswa pemilik pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $otherStudent = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Test siswa tidak berubah')
        ->call('save');

    expect($payment->refresh()->student_id)->toBe($student->id)
        ->and($payment->student_id)->not->toBe($otherStudent->id);
});

it('koreksi wajib mengisi alasan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->call('gotoConfirm')
        ->call('save')
        ->assertHasErrors(['reason' => 'required']);
});

it('koreksi menghitung ulang total dari jumlah rincian', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000, $osisBill->id => 5000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id, $osisBill->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 500000)
        ->set('selectedBillAmounts.'.$osisBill->id, 5000)
        ->call('gotoConfirm')
        ->set('reason', 'Total dihitung ulang')
        ->call('save');

    $payment->refresh();

    expect($payment->details()->count())->toBe(2)
        ->and((float) $payment->total_amount)->toBe(505000.0);
});

it('koreksi mempertahankan struktur satu pembayaran dengan banyak detail', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $ekskulBill = makeMonthlyBill($student, $catalog['Ekskul'], 52000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000, $ekskulBill->id => 52000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id, $ekskulBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Hanya perbaikan nominal')
        ->call('save');

    expect(Payment::where('student_id', $student->id)->count())->toBe(1)
        ->and($payment->refresh()->details()->count())->toBe(2);
});

it('koreksi menambah tagihan baru dengan bill_id dan periode asli', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id, $jemputanBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Tambahkan tagihan Jemputan')
        ->call('save');

    $payment->refresh();
    $details = $payment->details()->get();

    expect($details->pluck('bill_id')->all())->toEqualCanonicalizing([$sppBill->id, $jemputanBill->id])
        ->and((float) $payment->total_amount)->toBe(1470000.0);

    $jemputanDetail = $details->firstWhere('bill_id', $jemputanBill->id);

    expect($jemputanDetail->period_month)->toBe(10)
        ->and($jemputanDetail->period_year)->toBe(2026)
        ->and($jemputanDetail->academic_year)->toBeNull()
        ->and($jemputanDetail->bill_id)->toBe($jemputanBill->id);
});

it('koreksi mempertahankan bill_id detail yang tidak berubah', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->call('gotoConfirm')
        ->set('reason', 'Perbaiki nominal')
        ->call('save');

    expect($payment->refresh()->details()->first()->bill_id)->toBe($sppBill->id);
});

it('koreksi menolak nominal melebihi sisa tagihan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 1100000)
        ->call('gotoConfirm')
        ->set('reason', 'Test overpay')
        ->call('save')
        ->assertHasErrors(['selectedBillAmounts.'.$sppBill->id]);

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(970000.0);
});

it('koreksi menolak tanpa tagihan terpilih', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [])
        ->call('gotoConfirm')
        ->assertHasErrors(['selectedBillIds']);
});

it('koreksi tidak dapat dilakukan pada pembayaran yang sudah dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);
    cancelPaymentDirectly($payment, $user);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// PEMBATALAN
// ---------------------------------------------------------------------------

it('pembatalan mewajibkan alasan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->call('cancelPayment')
        ->assertHasErrors(['cancelReason' => 'required']);
});

it('pembatalan menandai pembayaran dibatalkan dan mengembalikan saldo tagihan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    expect($sppBill->refresh()->isSettled())->toBeTrue();

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Transfer dibatalkan oleh wali')
        ->call('cancelPayment');

    $payment->refresh();

    expect($payment->status)->toBe(Payment::STATUS_CANCELLED)
        ->and($payment->cancelled_by)->toBe($user->id)
        ->and($payment->cancelled_at)->not->toBeNull()
        ->and($payment->cancellation_reason)->toBe('Transfer dibatalkan oleh wali')
        ->and($payment->details()->count())->toBe(1);

    $sppBill->refresh();

    expect($sppBill->paid_amount)->toBe(0.0)
        ->and($sppBill->remaining_amount)->toBe(970000.0)
        ->and($sppBill->status)->toBe(StudentBill::STATUS_UNPAID);
});

it('pembatalan tetap mempertahankan detail pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Pembatalan uji')
        ->call('cancelPayment');

    expect($payment->details()->count())->toBe(1);
});

it('pembayaran yang dibatalkan tidak bisa dibatalkan ulang', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    cancelPaymentDirectly($payment, $user);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Coba batalkan lagi')
        ->call('cancelPayment')
        ->assertHasErrors(['cancelReason']);
});

it('pembayaran yang dibatalkan tidak dihitung sebagai pelunasan tagihan', function () {
    $user = User::factory()->create();

    $student = makeBillStudent(8);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$bill->id => 970000]);

    expect($bill->refresh()->isSettled())->toBeTrue();

    cancelPaymentDirectly($payment, $user);

    $freshBill = StudentBill::find($bill->id);

    expect($freshBill->paid_amount)->toBe(0.0)
        ->and($freshBill->remaining_amount)->toBe(970000.0)
        ->and($freshBill->isSettled())->toBeFalse();

    // Jalur eager-load (PaymentCreate) juga mengabaikan detail pembatalan.
    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    expect(collect($component->get('outstandingBills'))->pluck('id')->all())->toBe([$bill->id]);
});

// ---------------------------------------------------------------------------
// INDEX
// ---------------------------------------------------------------------------

it('index menampilkan pembayaran dibatalkan dengan badge status', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);
    cancelPaymentDirectly($payment, $user);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSee('Dibatalkan');
});

it('filter status dibatalkan menampilkan hanya pembayaran yang dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $activePayment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    $ekskulBill = makeMonthlyBill($student, $catalog['Ekskul'], 52000, 10, 2026);
    $cancelledPayment = createPaymentFromBills($student, [$ekskulBill->id => 52000]);
    cancelPaymentDirectly($cancelledPayment, $user);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'cancelled')
        ->assertSee($cancelledPayment->receipt_number)
        ->assertDontSee($activePayment->receipt_number);
});

it('filter lunas tidak menampilkan pembayaran yang dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $activePayment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    $ekskulBill = makeMonthlyBill($student, $catalog['Ekskul'], 52000, 10, 2026);
    $cancelledPayment = createPaymentFromBills($student, [$ekskulBill->id => 52000]);
    cancelPaymentDirectly($cancelledPayment, $user);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'lunas')
        ->assertSee($activePayment->receipt_number)
        ->assertDontSee($cancelledPayment->receipt_number);
});

it('kwitansi menampilkan status dibatalkan pada pembayaran yang dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);
    cancelPaymentDirectly($payment, $user);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('Dibatalkan')
        ->assertSee($payment->cancellation_reason);
});

// ---------------------------------------------------------------------------
// LOG AUDIT
// ---------------------------------------------------------------------------

it('koreksi mencatat log audit dengan snapshot sebelum dan sesudah', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id])
        ->set('selectedBillAmounts.'.$sppBill->id, 500000)
        ->call('gotoConfirm')
        ->set('reason', 'Nominal diperbaiki')
        ->call('save');

    $log = PaymentCorrectionLog::where('payment_id', $payment->id)
        ->where('action', PaymentCorrectionLog::ACTION_CORRECT)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->reason)->toBe('Nominal diperbaiki')
        ->and($log->corrected_by)->toBe($user->id)
        ->and($log->before_data['receipt_number'])->toBe($payment->receipt_number)
        ->and((float) $log->before_data['total_amount'])->toBe(970000.0)
        ->and((float) $log->before_data['details'][0]['amount'])->toBe(970000.0)
        ->and((float) $log->after_data['total_amount'])->toBe(500000.0)
        ->and((float) $log->after_data['details'][0]['amount'])->toBe(500000.0);
});

it('pembatalan mencatat log audit dengan aksi cancel', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Transfer dibatalkan wali')
        ->call('cancelPayment');

    $log = PaymentCorrectionLog::where('payment_id', $payment->id)
        ->where('action', PaymentCorrectionLog::ACTION_CANCEL)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->reason)->toBe('Transfer dibatalkan wali')
        ->and($log->corrected_by)->toBe($user->id)
        ->and($log->before_data['status'])->toBe(Payment::STATUS_ACTIVE)
        ->and($log->before_data['receipt_number'])->toBe($payment->receipt_number);
});

// ---------------------------------------------------------------------------
// REGRESSION: SUMMARY LIVE REACTIVITY
// ---------------------------------------------------------------------------

it('summary total updates immediately when bill amount is changed', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);

    $payment = createPaymentFromBills($student, [$jemputanBill->id => 50000]);

    $component = Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id]);

    expect($component->get('selectedBillAmounts')[$jemputanBill->id])->toBe(50000);

    $component->assertSeeHtml('Total Pembayaran')
        ->assertSeeHtml('font-numeric-data">Rp 50.000</span>');

    $component->set('selectedBillAmounts.'.$jemputanBill->id, 45000);

    expect($component->get('selectedBillAmounts')[$jemputanBill->id])->toBe(45000);

    $component->assertSeeHtml('font-numeric-data">Rp 45.000</span>');
});

it('summary total recalculates when one of multiple bills changes amount', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = createPaymentFromBills($student, [
        $jemputanBill->id => 50000,
        $sppBill->id => 970000,
    ]);

    $component = Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id, $sppBill->id]);

    expect($component->get('selectedBillAmounts')[$jemputanBill->id])->toBe(50000)
        ->and($component->get('selectedBillAmounts')[$sppBill->id])->toBe(970000);

    $component->assertSeeHtml('font-numeric-data">Rp 1.020.000</span>');

    $component->set('selectedBillAmounts.'.$jemputanBill->id, 45000);

    expect($component->get('selectedBillAmounts')[$jemputanBill->id])->toBe(45000);

    $component->assertSeeHtml('font-numeric-data">Rp 1.015.000</span>');

    $component->set('selectedBillAmounts.'.$jemputanBill->id, 40000);

    expect($component->get('selectedBillAmounts')[$jemputanBill->id])->toBe(40000);

    $component->assertSeeHtml('font-numeric-data">Rp 1.010.000</span>');
});

it('unchecking a bill removes its amount from the summary total', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 50000, 10, 2026);
    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = createPaymentFromBills($student, [
        $jemputanBill->id => 50000,
        $sppBill->id => 970000,
    ]);

    $component = Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$jemputanBill->id, $sppBill->id]);

    expect($component->get('selectedBillAmounts'))->toHaveKeys([$jemputanBill->id, $sppBill->id]);

    $component->assertSeeHtml('font-numeric-data">Rp 1.020.000</span>');

    $component->set('selectedBillIds', [$sppBill->id]);

    $remainingKeys = array_keys($component->get('selectedBillAmounts'));

    expect($remainingKeys)->not->toContain($jemputanBill->id);

    $component->assertSeeHtml('font-numeric-data">Rp 970.000</span>');
});

it('checking a new bill adds its default amount to the summary total', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $osisBill = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);

    $payment = createPaymentFromBills($student, [$sppBill->id => 970000]);

    $component = Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBill->id]);

    expect($component->get('selectedBillAmounts')[$sppBill->id])->toBe(970000);

    $component->assertSeeHtml('font-numeric-data">Rp 970.000</span>');

    $component->set('selectedBillIds', [$sppBill->id, $osisBill->id]);

    expect($component->get('selectedBillAmounts')[$osisBill->id])->toBe(5000);

    $component->assertSeeHtml('font-numeric-data">Rp 975.000</span>');
});

it('confirmation step shows the updated total, not the original', function () {
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
        ->assertSeeHtml('Total Pembayaran')
        ->assertSeeHtml('font-numeric-data">Rp 45.000</span>');
});

it('save persists corrected amount and creates audit log', function () {
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
        ->set('reason', 'Koreksi nominal Jemputan')
        ->call('save')
        ->assertRedirect(route('pembayaran.show', $payment->id));

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(45000.0);

    $detail = $payment->details()->where('bill_id', $jemputanBill->id)->first();

    expect($detail)->not->toBeNull()
        ->and((float) $detail->amount)->toBe(45000.0);

    $jemputanBill->refresh();

    expect($jemputanBill->paid_amount)->toBe(45000.0)
        ->and($jemputanBill->remaining_amount)->toBe(5000.0);

    $log = PaymentCorrectionLog::where('payment_id', $payment->id)
        ->where('action', PaymentCorrectionLog::ACTION_CORRECT)
        ->first();

    expect($log)->not->toBeNull()
        ->and((float) $log->before_data['total_amount'])->toBe(50000.0)
        ->and((float) $log->after_data['total_amount'])->toBe(45000.0)
        ->and((float) $log->after_data['details'][0]['amount'])->toBe(45000.0);
});
