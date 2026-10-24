<?php

use App\Livewire\PaymentCorrection;
use App\Livewire\PaymentEdit;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Pembayaran eksternal berisi satu detail untuk sebuah tagihan.
 */
function n6CreateExternalPayment(Student $student, Bank $bank, User $user, string $date, StudentBill $bill, float $amount, string $status = Payment::STATUS_ACTIVE): Payment
{
    $payment = Payment::create([
        'receipt_number' => 'KWT-N6X-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
        'description' => $bill->period_label,
    ]);

    return $payment;
}

/**
 * Siswa dengan sejumlah tagihan SPP lintas periode, pembayaran pertama dilunasi
 * oleh pembayaran yang akan diedit. Sisa tagihan outstanding.
 *
 * @return array{payment: Payment, student: Student, bills: array<int, StudentBill>}
 */
function n6CreateBulkScenario(int $billCount): array
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $type = manualAddCatalog(8)['SPP'];

    $bills = [];
    for ($i = 0; $i < $billCount; $i++) {
        $bills[] = makeMonthlyBill($student, $type, 100000 + $i, ($i % 12) + 1, 2024 + intdiv($i, 12));
    }

    $first = $bills[0];

    $payment = Payment::create([
        'receipt_number' => 'KWT-N6-BULK-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => $first->amount,
        'payment_method' => 'transfer',
        'description' => 'Pembayaran uji pertumbuhan query',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $first->id,
        'payment_type_id' => $first->payment_type_id,
        'period_month' => $first->period_month,
        'period_year' => $first->period_year,
        'amount' => $first->amount,
        'description' => $first->period_label,
    ]);

    return ['payment' => $payment, 'student' => $student, 'bills' => $bills];
}

/**
 * Skenario kaya untuk uji ekuivalensi kandidat tagihan:
 *  - A  SPP Oktober 2026 lunas oleh pembayaran yang akan diedit.
 *  - B  SPP November 2026 dibayar sebagian (500000) oleh pembayaran eksternal.
 *  - C  SPP Desember 2026 belum dibayar + diskon -70000 => efektif 900000.
 *  - D  OSIS Oktober 2026 hanya "terbayar" lewat pembayaran yang dibatalkan.
 *  - E  Jemputan Oktober 2026 lunas eksternal => bukan kandidat (sisa 0).
 *
 * @return array{payment: Payment, student: Student, bills: array<string, StudentBill>}
 */
function n6CreateScenario(): array
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $billA = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);
    $billB = makeMonthlyBill($student, $catalog['SPP'], 970000, 11, 2026);
    $billC = makeMonthlyBill($student, $catalog['SPP'], 970000, 12, 2026);
    $billD = makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026);
    $billE = makeMonthlyBill($student, $catalog['Jemputan'], 550000, 10, 2026);

    BillAdjustment::create([
        'bill_id' => $billC->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -70000,
        'reason' => 'Diskon uji N6',
    ]);

    $payment = Payment::create([
        'receipt_number' => 'KWT-N6-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-05',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'description' => 'Pembayaran N6',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $billA->id,
        'payment_type_id' => $catalog['SPP']->id,
        'period_month' => $billA->period_month,
        'period_year' => $billA->period_year,
        'amount' => 970000,
        'description' => $billA->period_label,
    ]);

    n6CreateExternalPayment($student, $bank, $user, '2026-11-05', $billB, 500000);
    n6CreateExternalPayment($student, $bank, $user, '2026-10-06', $billE, 550000);

    $cancelled = n6CreateExternalPayment($student, $bank, $user, '2026-10-07', $billD, 5000);
    $cancelled->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Pembatalan uji N6',
    ]);

    return [
        'payment' => $payment,
        'student' => $student,
        'bills' => compact('billA', 'billB', 'billC', 'billD', 'billE'),
    ];
}

function n6CandidateBills(object $component, Payment $payment): array
{
    $method = new ReflectionMethod($component, 'buildCandidateBills');
    $method->setAccessible(true);

    return $method->invoke($component, $payment);
}

// ---------------------------------------------------------------------------
// PERTUMBUHAN QUERY: EDIT (Pertumbuhan tetap, bukan per tagihan)
// ---------------------------------------------------------------------------

it('edit render keeps SELECT counts flat as candidate bills grow', function () {
    $totals = [];
    $adjustments = [];

    foreach ([1, 10, 25] as $count) {
        $payment = n6CreateBulkScenario($count)['payment'];

        $edit = new PaymentEdit;
        $edit->mount($payment->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $edit->render();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $totals[$count] = count($log);
        $adjustments[$count] = count(array_filter(
            $log,
            fn (array $query) => str_contains($query['query'], 'bill_adjustments'),
        ));
    }

    expect($totals[10])->toBe($totals[1])
        ->and($totals[25])->toBe($totals[1])
        ->and($adjustments[10])->toBe($adjustments[1])
        ->and($adjustments[25])->toBe($adjustments[1])
        ->and($adjustments[25])->toBeLessThanOrEqual(2);
});

// ---------------------------------------------------------------------------
// PERTUMBUHAN QUERY: KOREKSI
// ---------------------------------------------------------------------------

it('correction render keeps SELECT counts flat as candidate bills grow', function () {
    $totals = [];

    foreach ([1, 10, 25] as $count) {
        $payment = n6CreateBulkScenario($count)['payment'];

        $correction = new PaymentCorrection;
        $correction->mount($payment->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $correction->render();
        $totals[$count] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }

    expect($totals[10])->toBe($totals[1])
        ->and($totals[25])->toBe($totals[1]);
});

it('correction buildCandidateBills fires bill_adjustments exactly once', function () {
    $scenario = n6CreateScenario();
    $correction = new PaymentCorrection;
    $correction->mount($scenario['payment']->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $candidates = n6CandidateBills($correction, $scenario['payment']);
    $adjustments = count(array_filter(
        DB::getQueryLog(),
        fn (array $query) => str_contains($query['query'], 'bill_adjustments'),
    ));
    DB::disableQueryLog();

    expect($candidates)->toHaveCount(4)
        ->and($adjustments)->toBe(1);
});

// ---------------------------------------------------------------------------
// EKUIVALENSI KANDIDAT TAGIHAN: jalur eager-loading vs jalur askesor asli
// ---------------------------------------------------------------------------

it('candidate bills keep exact eligibility and amounts versus accessor path', function () {
    $scenario = n6CreateScenario();
    $edit = new PaymentEdit;
    $edit->mount($scenario['payment']->id);

    $candidates = collect(n6CandidateBills($edit, $scenario['payment']))->keyBy('id');
    $bills = $scenario['bills'];

    expect($candidates)->toHaveCount(4)
        ->and($candidates)->toHaveKey($bills['billA']->id)
        ->and($candidates)->toHaveKey($bills['billB']->id)
        ->and($candidates)->toHaveKey($bills['billC']->id)
        ->and($candidates)->toHaveKey($bills['billD']->id)
        ->and($candidates)->not->toHaveKey($bills['billE']->id);

    foreach ($candidates as $row) {
        $bill = StudentBill::with('paymentType')->find($row['id']);

        $current = $scenario['payment']->details->firstWhere('bill_id', $bill->id);
        $currentAmount = $current ? (float) $current->amount : 0.0;

        expect($row['amount'])->toBe((float) $bill->effective_amount)
            ->and($row['remaining_amount'])->toBe((float) $bill->remaining_amount)
            ->and($row['current_amount'])->toBe($currentAmount)
            ->and($row['max_amount'])->toBe(round((float) $bill->remaining_amount + $currentAmount, 2));
    }
});

it('edit persisted candidates match accessor path when an amount changes', function () {
    $scenario = n6CreateScenario();
    $edit = new PaymentEdit;
    $edit->mount($scenario['payment']->id);

    $before = collect(n6CandidateBills($edit, $scenario['payment']))->keyBy('id');
    $sppBillId = $scenario['bills']['billA']->id;

    $edit->updatedSelectedBillIds([$sppBillId]);

    $after = collect(n6CandidateBills($edit, $scenario['payment']))->keyBy('id');

    expect($after[$sppBillId]['remaining_amount'])->toBe($before[$sppBillId]['remaining_amount'])
        ->and($after[$sppBillId]['max_amount'])->toBe($before[$sppBillId]['max_amount']);
});

// ---------------------------------------------------------------------------
// EVENT HANDLER: JUMLAH QUERY TETAP
// ---------------------------------------------------------------------------

it('updatedSelectedBillIds issues no bill fetch when amounts are already seeded', function () {
    $payment = n6CreateBulkScenario(10)['payment'];

    $edit = new PaymentEdit;
    $edit->mount($payment->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $edit->updatedSelectedBillIds($edit->selectedBillIds);

    expect(count(DB::getQueryLog()))->toBe(2);
    DB::disableQueryLog();
});

it('updatedSelectedBillIds batches new-bill seeding with a constant query count', function () {
    $scenario = n6CreateBulkScenario(15);
    $payment = $scenario['payment'];
    $unselected = array_values(array_filter(
        array_map(fn (StudentBill $bill) => $bill->id, $scenario['bills']),
        fn (int $id) => ! in_array($id, $payment->details->pluck('bill_id')->all(), true),
    ));

    $one = new PaymentEdit;
    $one->mount($payment->id);
    $one->selectedBillIds = array_merge($one->selectedBillIds, [$unselected[0]]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $one->updatedSelectedBillIds($one->selectedBillIds);
    $queriesForOne = count(DB::getQueryLog());
    DB::disableQueryLog();

    $ten = new PaymentEdit;
    $ten->mount($payment->id);
    $ten->selectedBillIds = array_merge($ten->selectedBillIds, array_slice($unselected, 0, 10));
    DB::flushQueryLog();
    DB::enableQueryLog();
    $ten->updatedSelectedBillIds($ten->selectedBillIds);
    $queriesForTen = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesForTen)->toBe($queriesForOne)
        ->and($ten->selectedBillAmounts[$unselected[1]] ?? null)->toBe(
            (int) round((float) StudentBill::find($unselected[1])->remaining_amount)
        );
});

it('addBill seeds the default amount from the accessor with a fixed query count', function () {
    $scenario = n6CreateBulkScenario(10);
    $payment = $scenario['payment'];
    $target = $scenario['bills'][1];

    $edit = new PaymentEdit;
    $edit->mount($payment->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $edit->addBill($target->id);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $expected = (int) round((float) StudentBill::find($target->id)->remaining_amount);

    expect($queries)->toBeLessThanOrEqual(7)
        ->and((int) $edit->selectedBillAmounts[$target->id])->toBe($expected);
});

// ---------------------------------------------------------------------------
// VALIDASI & TRANSaksi TOTAL (perilaku tetap)
// ---------------------------------------------------------------------------

it('edit save recalculates the payment total and records the log', function () {
    $scenario = n6CreateScenario();
    $payment = $scenario['payment'];
    $sppBillId = $scenario['bills']['billA']->id;

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillAmounts.'.$sppBillId, 900000)
        ->call('save')
        ->assertHasNoErrors();

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(900000.0)
        ->and(PaymentCorrectionLog::where('payment_id', $payment->id)
            ->where('action', PaymentCorrectionLog::ACTION_CORRECT)
            ->where('reason', 'Edit pembayaran')
            ->exists())->toBeTrue();
});

it('edit save rejects an amount above the remaining bill', function () {
    $scenario = n6CreateScenario();
    $payment = $scenario['payment'];

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$scenario['bills']['billC']->id])
        ->set('selectedBillAmounts.'.$scenario['bills']['billC']->id, 900001)
        ->call('save')
        ->assertHasErrors('selectedBillAmounts.'.$scenario['bills']['billC']->id);
});

it('correction save recalculates the total, persists the reason, and adds a bill', function () {
    $scenario = n6CreateScenario();
    $payment = $scenario['payment'];
    $sppBillId = $scenario['bills']['billA']->id;
    $billCId = $scenario['bills']['billC']->id;

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('selectedBillIds', [$sppBillId, $billCId])
        ->set('selectedBillAmounts.'.$sppBillId, 970000)
        ->set('selectedBillAmounts.'.$billCId, 700000)
        ->set('reason', 'Alokasi pembayaran salah periode')
        ->call('save')
        ->assertHasNoErrors();

    $payment->refresh();

    expect((float) $payment->total_amount)->toBe(1670000.0)
        ->and(PaymentCorrectionLog::where('payment_id', $payment->id)
            ->where('action', PaymentCorrectionLog::ACTION_CORRECT)
            ->where('reason', 'Alokasi pembayaran salah periode')
            ->exists())->toBeTrue();
});

it('edit and correction mount reject cancelled payments', function () {
    $scenario = n6CreateScenario();
    $payment = $scenario['payment'];
    $user = User::factory()->create();
    $payment->update([
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Pembatalan uji N6',
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentEdit::class, ['id' => $payment->id])->assertStatus(403);
    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])->assertStatus(403);
});
