<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

it('menghitung ringkasan tagihan dari student_bills + payment_details', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);

    $billSpp = makeMonthlyBill($student, $spp, 1500000);
    $billJemputan = makeMonthlyBill($student, $jemputan, 550000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $jemputan);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000001',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 300000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $billJemputan->id,
        'payment_type_id' => $jemputan->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 300000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Rp 2.050.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 1.750.000')
        ->assertSee('Sebagian')
        ->assertSee('Belum Bayar');
});

it('menandai tagihan lunas ketika total pembayaran mencapai tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);

    $bill = makeMonthlyBill($student, $spp, 1500000);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000002',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 1500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 1500000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lunas')
        ->assertSee('Rp 0');
});

it('membuat tagihan lewat aksi generate di halaman detail', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('generateBills');

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(1);
});

it('merefresh daftar tagihan saat menerima event student-payment-settings-updated', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component->assertSee('Belum ada tagihan untuk siswa ini');

    makeActiveSetting($student, $jemputan);
    makeMonthlyBill($student, $jemputan, 550000);

    $component->dispatch('student-payment-settings-updated', studentId: $student->id);

    $component->assertSee('Jemputan')
        ->assertSee('Rp 550.000');
});

it('merefresh daftar tagihan saat menerima event payment-saved', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $jemputan, 550000);

    makeActiveSetting($student, $jemputan);

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component->assertSee('Belum Bayar');

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000003',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 300000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $jemputan->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 300000,
    ]);

    $component->dispatch('payment-saved', studentId: $student->id);

    $component->assertSee('Sebagian')
        ->assertSee('Rp 250.000');
});
