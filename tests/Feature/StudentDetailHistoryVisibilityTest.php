<?php

use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

it('menampilkan tagihan belum lunas dari jenis pembayaran aktif di section bulanan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('SPP')
        ->assertSee('Belum Bayar');
});

it('tagihan belum lunas dari jenis pembayaran tidak aktif tetap tampil karena bill otoritatif', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);
    makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Jemputan')
        ->assertSee('Belum Bayar');
});

it('menempatkan tagihan lunas di periode asalnya, bukan di riwayat terpisah', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $billSpp = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000020',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 1500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $billSpp->id,
        'payment_type_id' => $spp->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 1500000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('SPP')
        ->assertSee('Lunas')
        ->assertDontSee('Sebagian')
        ->assertDontSee('Riwayat Tagihan');
});

it('menampilkan tagihan lunas di kartu periode asalnya', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000021',
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
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('SPP')
        ->assertSee('Lunas')
        ->assertDontSee('Belum Bayar')
        ->assertDontSee('Riwayat Tagihan');
});

it('bill belum lunas tetap tampil saat jenis pembayaran diaktifkan kembali, tanpa duplikat', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    $setting = makeActiveSetting($student, $jemputan, false);
    makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Jemputan')
        ->assertSee('Belum Bayar');

    $setting->update(['is_active' => true]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Jemputan')
        ->assertSee('Belum Bayar');

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(1);
});

it('tidak menghapus student_bill pada perubahan visibilitas', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student]);

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(2);
});
