<?php

use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;

it('menghitung status tagihan Belum Bayar', function () {
    $student = Student::factory()->create();
    $type = PaymentType::factory()->create();

    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 1000000,
        'period_month' => 8,
        'period_year' => 2026,
    ]);

    expect($bill->status)->toBe(StudentBill::STATUS_UNPAID)
        ->and((float) $bill->remaining_amount)->toBe(1000000.0);
});

it('menghitung status Sebagian dan tunggakan dari bill_id', function () {
    $student = Student::factory()->create();
    $type = PaymentType::factory()->create();
    $bank = Bank::factory()->create();
    $user = User::factory()->create();

    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 5000000,
        'period_month' => null,
        'period_year' => null,
    ]);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => now()->toDateString(),
        'total_amount' => 1000000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => null,
        'period_year' => null,
        'amount' => 1000000,
    ]);

    $bill->refresh();

    expect((float) $bill->paid_amount)->toBe(1000000.0)
        ->and((float) $bill->remaining_amount)->toBe(4000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL)
        ->and((float) $bill->amount)->toBe(5000000.0);
});

it('menghitung status Lunas ketika total pembayaran mencapai tagihan', function () {
    $student = Student::factory()->create();
    $type = PaymentType::factory()->create();
    $bank = Bank::factory()->create();
    $user = User::factory()->create();

    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 1500000,
        'period_month' => 8,
        'period_year' => 2026,
    ]);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => now()->toDateString(),
        'total_amount' => 1500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 1500000,
    ]);

    $bill->refresh();

    expect($bill->status)->toBe(StudentBill::STATUS_PAID)
        ->and((float) $bill->remaining_amount)->toBe(0.0)
        ->and($bill->isSettled())->toBeTrue();
});
