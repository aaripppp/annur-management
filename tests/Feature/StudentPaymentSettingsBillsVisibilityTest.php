<?php

use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentPaymentSettings;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

it('setting opsional ON: bill tampil sebagai tagihan aktif', function () {
    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);
    makeMonthlyBill($student, $osis, 50000);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $osis->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('OSIS')
        ->assertSee('Rp 50.000');
});

it('setting opsional OFF: bill tetap tampil di buku tagihan dan dapat dibayar', function () {
    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);
    makeMonthlyBill($student, $osis, 50000);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $osis->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate')
        ->call('toggle', $osis->id);

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->first();

    expect($bill)->not->toBeNull();

    $payment = Livewire::test(PaymentCreate::class);
    $payment->call('selectStudent', $student->id);

    $outstanding = collect($payment->get('outstandingBills'));

    // Bill adalah otoritatif: tetap tampil di PaymentCreate walau jenisnya tidak aktif.
    expect($outstanding)->toHaveCount(1)
        ->and($outstanding->contains('id', $bill->id))->toBeTrue();

    // Dan tetap tampil di buku tagihan.
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('OSIS')
        ->assertSee('Rp 50.000');
});

it('setting opsional diaktifkan lagi memakai ulang bill yang ada tanpa duplikat', function () {
    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);
    makeMonthlyBill($student, $osis, 50000);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $osis->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate')
        ->call('toggle', $osis->id)
        ->call('confirmActivate', $osis->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate');

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->count())->toBe(1);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->contains('payment_type_name', 'OSIS'))->toBeTrue();
});

it('bill historis yang sudah lunas tetap tersimpan dan tampil di periode asalnya', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $osis, 50000);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000004',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 50000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $osis->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 50000,
    ]);

    expect(StudentBill::find($bill->id))->not->toBeNull();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('OSIS')
        ->assertSee('Lunas');
});

it('bill tipe nonaktif tetap tampil sebagai tagihan outstanding untuk dibayar', function () {
    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $osis, 50000);

    // Tanpa setting aktif, tipe nonaktif -> bill tetap bisa dipilih lewat alur pembayaran.
    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding)->toHaveCount(1)
        ->and($outstanding->contains('id', $bill->id))->toBeTrue();
});
