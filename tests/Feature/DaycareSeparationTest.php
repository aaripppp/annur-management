<?php

use App\Livewire\DaycareManagement;
use App\Livewire\DaycarePaymentCreate;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Livewire\Livewire;

it('membuat anak Daycare tidak memutasi tabel domain sekolah', function () {
    $before = [
        'students' => Student::query()->count(),
        'enrollments' => StudentAcademicEnrollment::query()->count(),
        'bills' => StudentBill::query()->count(),
        'settings' => StudentPaymentSetting::query()->count(),
        'payments' => Payment::query()->count(),
        'details' => PaymentDetail::query()->count(),
    ];

    $component = Livewire::test(DaycareManagement::class);
    foreach (validDaycareChildData() as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('save')->assertHasNoErrors();

    expect(DaycareChild::query()->count())->toBe(1)
        ->and(Student::query()->count())->toBe($before['students'])
        ->and(StudentAcademicEnrollment::query()->count())->toBe($before['enrollments'])
        ->and(StudentBill::query()->count())->toBe($before['bills'])
        ->and(StudentPaymentSetting::query()->count())->toBe($before['settings'])
        ->and(Payment::query()->count())->toBe($before['payments'])
        ->and(PaymentDetail::query()->count())->toBe($before['details']);
});

it('membuat pembayaran Daycare tidak membuat bill payment atau payment detail sekolah', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::actingAs($user)
        ->test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan Agustus')
        ->set('items.0.amount', '750000')
        ->set('bank_id', (string) $bank->id)
        ->set('payment_date', '2026-08-25')
        ->call('save')
        ->assertHasNoErrors();

    expect($child->payments()->count())->toBe(1)
        ->and(StudentBill::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentDetail::query()->count())->toBe(0)
        ->and(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentPaymentSetting::query()->count())->toBe(0);
});
