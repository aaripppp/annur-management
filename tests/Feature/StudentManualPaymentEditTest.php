<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Livewire\StudentManualPaymentEdit;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeEditableStudentManualPayment(Student $student, Bank $bank, User $user, array $items = []): Payment
{
    $items = $items ?: [
        ['description' => 'Infaq', 'amount' => 500000],
        ['description' => 'Donasi', 'amount' => 100000],
    ];
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-2026-'.fake()->unique()->numerify('######'),
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-26',
        'total_amount' => collect($items)->sum('amount'),
        'payment_method' => 'transfer',
        'description' => 'Catatan awal',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    $payment->details()->createMany(collect($items)->map(fn (array $item): array => [
        'bill_id' => null,
        'payment_type_id' => null,
        'description' => $item['description'],
        'amount' => $item['amount'],
    ])->all());

    return $payment;
}

it('loads manual edit with existing values and exposes the entry point', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $payment = makeEditableStudentManualPayment($student, $bank, $user);

    $this->actingAs($user)
        ->get(route('pembayaran.manual.edit', $payment))
        ->assertOk()
        ->assertSee('Edit Pembayaran Manual Siswa')
        ->assertSee('Infaq')
        ->assertSee('Donasi')
        ->assertSee('Simpan Perubahan');

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->assertSet('payment_id', $payment->id)
        ->assertSet('student_id', $student->id)
        ->assertSet('bank_id', (string) $bank->id)
        ->assertSet('payment_date', '2026-08-26')
        ->assertSet('notes', 'Catatan awal')
        ->assertSet('items.0', ['description' => 'Infaq', 'amount' => 500000])
        ->assertSet('items.1', ['description' => 'Donasi', 'amount' => 100000]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee(route('pembayaran.manual.edit', $payment), false);
});

it('rejects bill and cancelled manual payments from the manual editor', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $billPayment = makeEditableStudentManualPayment($student, $bank, $user);
    $billPayment->update(['payment_kind' => Payment::KIND_BILL]);
    $cancelledPayment = makeEditableStudentManualPayment($student, $bank, $user);
    $cancelledPayment->update(['status' => Payment::STATUS_CANCELLED]);

    $this->actingAs($user)
        ->get(route('pembayaran.manual.edit', $billPayment))
        ->assertForbidden();

    $this->get(route('pembayaran.manual.edit', $cancelledPayment))
        ->assertForbidden();

    Livewire::test(PaymentShow::class, ['id' => $cancelledPayment->id])
        ->assertDontSee(route('pembayaran.manual.edit', $cancelledPayment), false);
});

it('maintains stable rows while adding and removing first middle and final items', function () {
    $user = User::factory()->create();
    $payment = makeEditableStudentManualPayment(
        Student::factory()->create(),
        Bank::factory()->create(['is_active' => true]),
        $user,
        [
            ['description' => 'Infaq', 'amount' => 500000],
            ['description' => 'Donasi', 'amount' => 100000],
            ['description' => 'Sumbangan', 'amount' => 75000],
        ],
    );

    $component = Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment]);
    $keys = $component->get('itemKeys');

    $component->call('removeItem', 1)
        ->assertSet('items.0.description', 'Infaq')
        ->assertSet('items.1.description', 'Sumbangan')
        ->assertSet('itemKeys.0', $keys[0])
        ->assertSet('itemKeys.1', $keys[2])
        ->call('removeItem', 0)
        ->assertSet('items.0.description', 'Sumbangan')
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->call('addItem')
        ->assertCount('items', 2)
        ->assertSet('items.1', ['description' => '', 'amount' => '']);
});

it('updates manual header and synchronizes details without creating payment or audit records', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $oldBank = Bank::factory()->create(['is_active' => true]);
    $newBank = Bank::factory()->cash()->create(['is_active' => true]);
    $payment = makeEditableStudentManualPayment($student, $oldBank, $user);
    $paymentId = $payment->id;
    $receiptNumber = $payment->receipt_number;
    $createdAt = $payment->created_at;

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->assertSee('Tunai')
        ->set('bank_id', (string) $newBank->id)
        ->set('payment_date', '2026-09-01')
        ->set('notes', '  Catatan diperbarui  ')
        ->set('items', [
            ['description' => '  Infaq Masjid  ', 'amount' => '750.000'],
            ['description' => 'Kegiatan Sosial', 'amount' => '125.000'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pembayaran.show', $payment));

    $payment->refresh()->load('details');

    expect(Payment::query()->count())->toBe(1)
        ->and($payment->id)->toBe($paymentId)
        ->and($payment->receipt_number)->toBe($receiptNumber)
        ->and($payment->payment_kind)->toBe(Payment::KIND_MANUAL)
        ->and($payment->student_id)->toBe($student->id)
        ->and($payment->created_by)->toBe($user->id)
        ->and($payment->created_at->equalTo($createdAt))->toBeTrue()
        ->and($payment->bank_id)->toBe($newBank->id)
        ->and($payment->payment_date->format('Y-m-d'))->toBe('2026-09-01')
        ->and($payment->description)->toBe('Catatan diperbarui')
        ->and((float) $payment->total_amount)->toBe(875000.0)
        ->and((float) $payment->details->sum('amount'))->toBe(875000.0)
        ->and($payment->details->pluck('description')->all())->toBe(['Infaq Masjid', 'Kegiatan Sosial'])
        ->and($payment->details->pluck('bill_id')->all())->toBe([null, null])
        ->and($payment->details->pluck('payment_type_id')->all())->toBe([null, null])
        ->and(PaymentCorrectionLog::query()->count())->toBe(0);
});

it('keeps manual edit isolated from StudentBill and updates Dashboard and history projections', function () {
    $user = User::factory()->create();
    $student = makeBillStudent();
    $bill = makeMonthlyBill($student, makeBillType('SPP'), 1000000);
    $oldBank = Bank::factory()->create(['is_active' => true, 'name' => 'BCA']);
    $newBank = Bank::factory()->create(['is_active' => true, 'name' => 'BSI']);
    $payment = makeEditableStudentManualPayment($student, $oldBank, $user);
    $billCount = $student->bills()->count();

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->set('bank_id', (string) $newBank->id)
        ->set('payment_date', '2026-09-02')
        ->set('items', [['description' => 'Infaq Masjid', 'amount' => 750000]])
        ->call('save')
        ->assertHasNoErrors();

    expect($student->bills()->count())->toBe($billCount)
        ->and($bill->refresh()->paid_amount)->toBe(0.0)
        ->and($bill->remaining_amount)->toBe(1000000.0);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 750000.0)
        ->assertViewHas('totalTransaksi', 1)
        ->assertViewHas('bankTotals', fn ($totals): bool => (float) $totals[$oldBank->id]['combined_total'] === 0.0
            && (float) $totals[$newBank->id]['combined_total'] === 750000.0)
        ->assertSee('Infaq Masjid')
        ->assertSee('Manual');

    Livewire::test(PaymentIndex::class)
        ->set('activeTab', 'history')
        ->assertSee('Infaq Masjid')
        ->assertSee('BSI')
        ->assertSee('02 Sep 2026')
        ->assertSee('Rp 750.000');
});

it('renders the latest manual data on detail and existing receipt routes', function () {
    $user = User::factory()->create();
    $payment = makeEditableStudentManualPayment(
        Student::factory()->create(),
        Bank::factory()->create(['is_active' => true]),
        $user,
    );
    $receiptNumber = $payment->receipt_number;

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->set('items', [['description' => 'Infaq Masjid', 'amount' => 750000]])
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee($receiptNumber)
        ->assertSee('Infaq Masjid')
        ->assertSee('Catatan awal')
        ->assertSee('Rp 750.000');

    $inlinePdf = $this->actingAs($user)->get(route('pembayaran.print', $payment));
    $downloadPdf = $this->get(route('pembayaran.pdf', $payment));

    $inlinePdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $downloadPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(Payment::query()->count())->toBe(1)
        ->and($payment->refresh()->receipt_number)->toBe($receiptNumber);
});

it('keeps existing proof when no replacement is uploaded', function () {
    Storage::fake('public');
    Storage::disk('public')->put('receipts/original.pdf', 'original');
    $user = User::factory()->create();
    $payment = makeEditableStudentManualPayment(
        Student::factory()->create(),
        Bank::factory()->create(['is_active' => true]),
        $user,
    );
    $payment->update(['receipt' => 'receipts/original.pdf']);

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->set('items.0.amount', 600000)
        ->call('save')
        ->assertHasNoErrors();

    expect($payment->refresh()->receipt)->toBe('receipts/original.pdf');
    Storage::disk('public')->assertExists('receipts/original.pdf');
});

it('replaces proof after a successful manual edit', function () {
    Storage::fake('public');
    Storage::disk('public')->put('receipts/original.pdf', 'original');
    $user = User::factory()->create();
    $payment = makeEditableStudentManualPayment(
        Student::factory()->create(),
        Bank::factory()->create(['is_active' => true]),
        $user,
    );
    $payment->update(['receipt' => 'receipts/original.pdf']);

    Livewire::actingAs($user)
        ->test(StudentManualPaymentEdit::class, ['payment' => $payment])
        ->set('proof', UploadedFile::fake()->image('replacement.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $newProof = $payment->refresh()->receipt;
    expect($newProof)->not->toBe('receipts/original.pdf')->toStartWith('receipts/');
    Storage::disk('public')->assertMissing('receipts/original.pdf');
    Storage::disk('public')->assertExists($newProof);
});
