<?php

use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function makeBillWithDiscount(int $amount = 5000000, int $discount = 500000): array
{
    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, $amount);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -$discount,
    ]);

    return [$student, $type, $bill];
}

function payBill(StudentBill $bill, int $amount): void
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => now()->toDateString(),
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => $amount,
    ]);
}

it('menambahkan diskon ke tagihan beserta created_by', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->assertSet('isAdjustmentOpen', true)
        ->set('adjustmentAmount', '500000')
        ->set('adjustmentReason', 'Potongan uang pangkal')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    $adjustment = BillAdjustment::where('bill_id', $bill->id)->first();

    expect($adjustment)->not->toBeNull()
        ->and($adjustment->type)->toBe(BillAdjustment::TYPE_DISCOUNT)
        ->and((float) $adjustment->amount)->toBe(-500000.0)
        ->and($adjustment->reason)->toBe('Potongan uang pangkal')
        ->and($adjustment->created_by)->toBe($user->id);
});

it('menghitung tagihan efektif dengan benar', function () {
    [$student, $type, $bill] = makeBillWithDiscount();

    $bill->refresh();

    expect((float) $bill->amount)->toBe(5000000.0)
        ->and((float) $bill->effective_amount)->toBe(4500000.0);
});

it('menghitung sisa tagihan setelah diskon dan pembayaran', function () {
    [$student, $type, $bill] = makeBillWithDiscount();

    payBill($bill, 2000000);

    $bill->refresh();

    expect((float) $bill->effective_amount)->toBe(4500000.0)
        ->and((float) $bill->paid_amount)->toBe(2000000.0)
        ->and((float) $bill->remaining_amount)->toBe(2500000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('menolak diskon yang membuat tagihan efektif negatif', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 100000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->set('adjustmentAmount', '500000')
        ->call('saveAdjustment')
        ->assertHasErrors(['adjustmentAmount']);

    expect(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0);
});

it('menolak diskon yang membuat tagihan efektif lebih rendah dari yang sudah dibayar', function () {
    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBill($bill, 4000000);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->set('adjustmentAmount', '2000000')
        ->call('saveAdjustment')
        ->assertHasErrors(['adjustmentAmount']);

    expect(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0);
});

it('mengizinkan diskon maksimal sampai total yang sudah dibayar', function () {
    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBill($bill, 4000000);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->set('adjustmentAmount', '1000000')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    $bill->refresh();

    expect((float) $bill->effective_amount)->toBe(4000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PAID);
});

it('mengedit diskon menghitung ulang tagihan efektif dan sisa', function () {
    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 5000000);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    payBill($bill, 2000000);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->assertSet('adjustmentAmount', '500000')
        ->set('adjustmentAmount', '1000000')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    $bill->refresh();

    expect((float) $bill->effective_amount)->toBe(4000000.0)
        ->and((float) $bill->paid_amount)->toBe(2000000.0)
        ->and((float) $bill->remaining_amount)->toBe(2000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('menghapus diskon mengembalikan tagihan efektif ke nominal awal', function () {
    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    $bill = makeMonthlyBill($student, $type, 5000000);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editAdjustment', $bill->id)
        ->call('removeAdjustment');

    $bill->refresh();

    expect(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0)
        ->and((float) $bill->effective_amount)->toBe(5000000.0)
        ->and((float) $bill->remaining_amount)->toBe(5000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_UNPAID);
});
