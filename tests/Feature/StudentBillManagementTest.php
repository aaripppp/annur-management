<?php

use App\Enums\BillFrequency;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function payBillForManagement(StudentBill $bill, int $amount): void
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

it('membuka modal edit dengan nominal tagihan efektif terisi', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->assertSet('isEditOpen', true)
        ->assertSet('editBillId', $bill->id)
        ->assertSet('editAmount', '5000000')
        ->assertSet('editPaidAmount', 0.0);
});

it('mengubah nominal tagihan yang belum dibayar secara bebas', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', '4500000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    $bill->refresh();

    expect((float) $bill->amount)->toBe(4500000.0)
        ->and((float) $bill->effective_amount)->toBe(4500000.0)
        ->and($bill->updated_by)->toBe($user->id);
});

it('menolak menurunkan nominal di bawah jumlah yang sudah dibayar', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBillForManagement($bill, 4000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', '3000000')
        ->call('saveEditBill')
        ->assertHasErrors(['editAmount']);

    $bill->refresh();

    expect((float) $bill->amount)->toBe(5000000.0);
});

it('mengizinkan nominal sama dengan jumlah yang sudah dibayar', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBillForManagement($bill, 4000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', '4000000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    $bill->refresh();

    expect((float) $bill->amount)->toBe(4000000.0)
        ->and((float) $bill->paid_amount)->toBe(4000000.0)
        ->and((float) $bill->remaining_amount)->toBe(0.0)
        ->and($bill->isSettled())->toBeTrue();
});

it('tidak mengubah rincian pembayaran saat nominal diedit', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBillForManagement($bill, 2000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', '6000000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    $bill->refresh();

    expect((float) $bill->paid_amount)->toBe(2000000.0)
        ->and($bill->paymentDetails()->count())->toBe(1)
        ->and((float) $bill->paymentDetails()->first()->amount)->toBe(2000000.0);
});

it('membake diskon lama ke nominal saat diedit', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->assertSet('editAmount', '4500000')
        ->set('editAmount', '4600000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    $bill->refresh();

    expect(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0)
        ->and((float) $bill->amount)->toBe(4600000.0)
        ->and((float) $bill->effective_amount)->toBe(4600000.0);
});

it('menolak nominal tagihan negatif saat edit', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', '-1000')
        ->call('saveEditBill')
        ->assertHasErrors(['editAmount']);

    $bill->refresh();

    expect((float) $bill->amount)->toBe(5000000.0);
});

it('menambahkan tagihan bulanan secara manual', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    makeBillRate($type, 8, 1750000);
    makeActiveSetting($student, $type);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '1750000')
        ->set('addMonth', '9')
        ->set('addYear', '2026')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)->first();

    expect($bill)->not->toBeNull()
        ->and((float) $bill->amount)->toBe(1750000.0)
        ->and($bill->period_month)->toBe(9)
        ->and($bill->period_year)->toBe(2026)
        ->and($bill->academic_year)->toBeNull();
});

it('menolak tagihan bulanan duplikat untuk periode yang sama', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    makeBillRate($type, 8, 1750000);
    makeActiveSetting($student, $type);
    makeMonthlyBill($student, $type, 1750000, 9, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '1750000')
        ->set('addMonth', '9')
        ->set('addYear', '2026')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(1);
});

it('menambahkan tagihan tahunan secara manual', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $type);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '2500000')
        ->set('addAcademicYear', '2026/2027')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)->first();

    expect($bill)->not->toBeNull()
        ->and($bill->academic_year)->toBe('2026/2027')
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and((float) $bill->amount)->toBe(2500000.0);
});

it('menolak tagihan tahunan duplikat untuk tahun ajaran yang sama', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $type);

    StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 2500000,
        'academic_year' => '2026/2027',
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '2500000')
        ->set('addAcademicYear', '2026/2027')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(1);
});

it('menambahkan tagihan sekali bayar secara manual', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('Uang Pangkal');
    makeBillRate($type, 8, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $type);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '10000000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)->first();

    expect($bill)->not->toBeNull()
        ->and((float) $bill->amount)->toBe(10000000.0)
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and($bill->academic_year)->toBeNull();
});

it('menolak tagihan sekali bayar ganda yang belum lunas', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('Uang Pangkal');
    makeBillRate($type, 8, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $type);

    StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 10000000,
        'billing_frequency' => BillFrequency::OneTime->value,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAmount', '10000000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(1);
});

it('menolak menambah tagihan tanpa jenis pembayaran atau nominal', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->call('saveAddBill')
        ->assertHasErrors(['addPaymentTypeId', 'addAmount']);

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(0);
});

it('menghapus tagihan yang belum dibayar', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('isDeleteOpen', true)
        ->call('deleteBill')
        ->assertHasNoErrors();

    expect(StudentBill::find($bill->id))->toBeNull();
});

it('menghapus tagihan beserta penyesuaiannya saat belum dibayar', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    BillAdjustment::create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasNoErrors();

    expect(StudentBill::find($bill->id))->toBeNull()
        ->and(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0);
});

it('menolak menghapus tagihan yang sudah memiliki pembayaran', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 5000000);

    payBillForManagement($bill, 1000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasErrors(['deleteConfirm']);

    expect(StudentBill::find($bill->id))->not->toBeNull()
        ->and(Payment::where('student_id', $student->id)->count())->toBe(1);
});

it('menampilkan tombol tambah tagihan dan kolom aksi edit/hapus', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP');
    makeBillRate($type, 8, 1750000);
    makeActiveSetting($student, $type);
    $bill = makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tambah Tagihan')
        ->assertSee('Edit')
        ->assertSee('Hapus')
        ->assertSee('Sudah Dibayar')
        ->assertDontSee('Edit Penyesuaian')
        ->assertDontSee('Tagihan Efektif')
        ->assertDontSee('Tagihan Awal');
});

it('header hanya menampilkan identitas siswa dan aksi sekunder Lengkapi Tagihan', function () {
    Livewire::actingAs(User::factory()->create());

    $student = makeBillStudent(8);
    $type = makeBillType('SPP', auto: true, required: true);
    makeBillRate($type, 8, 1750000);
    makeActiveSetting($student, $type);
    makeMonthlyBill($student, $type, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lengkapi Tagihan')
        ->assertSee('NIS '.$student->nis)
        ->assertSee('Tambah Tagihan')
        ->assertSeeHtml('wire:click="openBillbook"')
        ->assertDontSeeHtml('title="Pengaturan Pembayaran"')
        ->assertDontSeeHtml("siswa/{$student->id}/pembayaran")
        ->assertDontSeeHtml('wire:click="openGenerateUntil"')
        ->assertDontSeeHtml('wire:click="generateBills"')
        ->assertDontSeeHtml('wire:click="openAddBill"');
});
