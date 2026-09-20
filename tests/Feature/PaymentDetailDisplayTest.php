<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

/**
 * Buat pembayaran dengan description opsional.
 */
function createPaymentWithDescription(
    ?string $description = 'Pembayaran SPP, Ekskul, dan Jemputan Oktober'
): Payment {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-DISPLAY-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'description' => $description,
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $sppBill->id,
        'payment_type_id' => $sppBill->payment_type_id,
        'period_month' => $sppBill->period_month,
        'period_year' => $sppBill->period_year,
        'amount' => 970000,
    ]);

    return $payment;
}

// ---------------------------------------------------------------------------
// detail_display ACCESSOR — BASIC
// ---------------------------------------------------------------------------

it('returns exact description when description is set', function () {
    $payment = createPaymentWithDescription('Pembayaran SPP, Ekskul, dan Jemputan Oktober');

    expect($payment->detail_display)->toBe('Pembayaran SPP, Ekskul, dan Jemputan Oktober');
});

it('returns "-" when description is null', function () {
    $payment = createPaymentWithDescription(null);

    expect($payment->detail_display)->toBe('-');
});

it('returns "-" when description is empty string', function () {
    $payment = createPaymentWithDescription('');

    expect($payment->detail_display)->toBe('-');
});

it('returns "-" when description is whitespace only', function () {
    $payment = createPaymentWithDescription('   ');

    expect($payment->detail_display)->toBe('-');
});

it('preserves exact description text without modification', function () {
    $desc = 'SPP bulan Oktober dan November 2026';
    $payment = createPaymentWithDescription($desc);

    expect($payment->detail_display)->toBe($desc)
        ->and($payment->detail_display)->not->toContain('+');
});

// ---------------------------------------------------------------------------
// DASHBOARD — detail_display
// ---------------------------------------------------------------------------

it('dashboard displays exact description for payment with description', function () {
    $payment = createPaymentWithDescription('SPP dan Ekskul September');

    Livewire::test(Dashboard::class)
        ->assertSee('SPP dan Ekskul September');
});

it('dashboard displays "-" for payment with null description', function () {
    $payment = createPaymentWithDescription(null);

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSeeInOrder([$payment->receipt_number, '-']);
});

it('dashboard displays "-" for payment with empty description', function () {
    $payment = createPaymentWithDescription('');

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSeeInOrder([$payment->receipt_number, '-']);
});

it('dashboard displays "-" for payment with whitespace description', function () {
    $payment = createPaymentWithDescription('   ');

    Livewire::test(Dashboard::class)
        ->assertSee($payment->receipt_number)
        ->assertSeeInOrder([$payment->receipt_number, '-']);
});

// ---------------------------------------------------------------------------
// PAYMENT INDEX — detail_display
// ---------------------------------------------------------------------------

it('payment index displays exact description for payment with description', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = createPaymentWithDescription('SPP dan Jemputan Oktober');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('SPP dan Jemputan Oktober');
});

it('payment index displays "-" for payment with null description', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = createPaymentWithDescription(null);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSeeInOrder([$payment->receipt_number, '-']);
});

it('payment index displays "-" for payment with empty description', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = createPaymentWithDescription('');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSeeInOrder([$payment->receipt_number, '-']);
});

// ---------------------------------------------------------------------------
// DASHBOARD AND PAYMENT INDEX CONSISTENCY
// ---------------------------------------------------------------------------

it('dashboard and payment index display identical description', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = createPaymentWithDescription('Pembayaran SPP, Ekskul, dan Jemputan Oktober');

    $dashboard = Livewire::test(Dashboard::class);
    $index = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history');

    $dashboard->assertSee('Pembayaran SPP, Ekskul, dan Jemputan Oktober');
    $index->assertSee('Pembayaran SPP, Ekskul, dan Jemputan Oktober');
});

// ---------------------------------------------------------------------------
// PAYMENT CANCELLATION — description preserved
// ---------------------------------------------------------------------------

it('cancellation preserves description on the payment record', function () {
    $payment = createPaymentWithDescription('SPP Oktober');

    cancelPaymentDirectly($payment, User::factory()->create());

    $payment->refresh();

    expect($payment->description)->toBe('SPP Oktober')
        ->and($payment->detail_display)->toBe('SPP Oktober');
});

// ---------------------------------------------------------------------------
// PAYMENT CORRECTION — description not touched
// ---------------------------------------------------------------------------

it('correction does not alter payment description', function () {
    $payment = createPaymentWithDescription('SPP dan Ekskul');

    cancelPaymentDirectly($payment, User::factory()->create());

    $payment->refresh();

    expect($payment->description)->toBe('SPP dan Ekskul');
});

// ---------------------------------------------------------------------------
// EXISTING FILTERS — still working
// ---------------------------------------------------------------------------

it('search and filter still work alongside detail_display', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent(8);
    $student->update(['nama_lengkap' => 'Andi Pratama']);
    $bank = Bank::factory()->create();
    $catalog = manualAddCatalog(8);
    $sppBill = makeMonthlyBill($student, $catalog['SPP'], 970000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-SEARCH-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'description' => 'SPP Oktober',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $sppBill->id,
        'payment_type_id' => $sppBill->payment_type_id,
        'period_month' => $sppBill->period_month,
        'period_year' => $sppBill->period_year,
        'amount' => 970000,
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', 'Andi')
        ->assertSee($payment->receipt_number)
        ->assertSee('SPP Oktober');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', 'NONEXISTENT')
        ->assertDontSee($payment->receipt_number);
});
