<?php

use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function makeTodayHistoryPayment(Student $student, Bank $bank, User $user, string $receiptNumber, string $createdAt, string $paymentDate = '2026-08-26'): Payment
{
    $payment = Payment::create([
        'receipt_number' => $receiptNumber,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => 200000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $payment->refresh();
}

function makeTodayHistoryStudent(array $overrides = []): Student
{
    return Student::factory()->create(array_merge([
        'nama_lengkap' => 'Aisyah Rahmadani',
        'nama_panggilan' => 'Aisyah',
        'nis' => 'TODAY-HIST-001',
    ], $overrides));
}

it('initializes start date and end date empty in history tab', function () {
    Livewire::test(PaymentIndex::class)
        ->assertSet('startDate', '')
        ->assertSet('endDate', '');
});

it('history default shows all transactions regardless of recorded date', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-TODAY', now()->toDateString().' 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-YESTERDAY', now()->subDay()->toDateString().' 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-TODAY')
        ->assertSee('KWT-YESTERDAY');
});

it('shows today recorded transaction even when payment_date is last month and TANGGAL TF uses payment_date', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-PAY-DATE-OLD', now()->toDateString().' 09:00:00', '2026-07-01');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-PAY-DATE-OLD')
        ->assertSee('01 Jul 2026');
});

it('hides a transaction recorded outside the range even when payment_date is inside it', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-CREATED-26', '2026-08-26 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-CREATED-TODAY', now()->toDateString().' 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-26')
        ->set('endDate', '2026-08-26')
        ->assertSee('KWT-CREATED-26')
        ->assertDontSee('KWT-CREATED-TODAY');
});

it('filters using created_at instead of payment_date', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-FILTER-S', '2026-08-26 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-FILTER-OUT', now()->toDateString().' 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-FILTER-S')
        ->assertDontSee('KWT-FILTER-OUT');
});

it('start date only filters created_at from that day forward', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-START-BEFORE', '2026-08-01 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-START-AFTER', '2026-08-20 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-15')
        ->assertSet('endDate', '')
        ->assertSee('KWT-START-AFTER')
        ->assertDontSee('KWT-START-BEFORE');
});

it('end date only filters created_at up to that day', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-END-BEFORE', '2026-08-01 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-END-AFTER', '2026-08-20 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('endDate', '2026-08-15')
        ->assertSet('startDate', '')
        ->assertSee('KWT-END-BEFORE')
        ->assertDontSee('KWT-END-AFTER');
});

it('start date boundary is inclusive on created_at', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-BOUNDARY-START', '2026-08-26 00:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-26')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-BOUNDARY-START');
});

it('end date boundary is inclusive on created_at', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-BOUNDARY-END', '2026-08-31 23:59:59');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-BOUNDARY-END');
});

it('records at the last second of today still show in the default view', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-END-OF-TODAY', now()->toDateString().' 23:59:59');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-END-OF-TODAY');
});

it('clearing start date keeps it empty', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-RESET-START', now()->toDateString().' 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->assertSet('startDate', '2026-08-01')
        ->set('startDate', '')
        ->assertSet('startDate', '')
        ->assertSee('KWT-RESET-START');
});

it('clearing end date keeps it empty', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-RESET-END', now()->toDateString().' 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('endDate', '2026-12-31')
        ->assertSet('endDate', '2026-12-31')
        ->set('endDate', '')
        ->assertSet('endDate', '')
        ->assertSee('KWT-RESET-END');
});

it('orders recorded transactions by created_at descending', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-ORDER-EARLY', now()->toDateString().' 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-ORDER-LATE', now()->toDateString().' 10:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-ORDER-LATE', 'KWT-ORDER-EARLY']);
});

it('paginates transaction records recorded today', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    for ($i = 1; $i <= 15; $i++) {
        makeTodayHistoryPayment(
            $student,
            $bank,
            $user,
            'KWT-PAGE-T-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            now()->toDateString().' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
        );
    }

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-PAGE-T-15')
        ->assertDontSee('KWT-PAGE-T-05')
        ->call('gotoPage', 2)
        ->assertSeeInOrder(['KWT-PAGE-T-05', 'KWT-PAGE-T-01'])
        ->assertDontSee('KWT-PAGE-T-15');
});

it('history tab opened via query string shows all transactions', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeTodayHistoryStudent();

    makeTodayHistoryPayment($student, $bank, $user, 'KWT-QUERY-TODAY', now()->toDateString().' 09:00:00');
    makeTodayHistoryPayment($student, $bank, $user, 'KWT-QUERY-YESTERDAY', now()->subDay()->toDateString().' 09:00:00');

    Livewire::withQueryParams(['tab' => 'history'])
        ->test(PaymentIndex::class)
        ->assertSet('activeTab', 'history')
        ->assertSee('KWT-QUERY-TODAY')
        ->assertSee('KWT-QUERY-YESTERDAY');
});
