<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentCancellationService;
use App\Services\PaymentDeletionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createStudentManualPayment(Student $student, Bank $bank, User $user, array $items = [['description' => 'Infaq', 'amount' => 500000]]): Payment
{
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-2026-'.fake()->unique()->numerify('######'),
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-26',
        'total_amount' => collect($items)->sum('amount'),
        'payment_method' => 'transfer',
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

it('removes the manual payment creation flow from the student payment page', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    $this->actingAs($user)
        ->get('/pembayaran/siswa/'.$student->id.'/manual')
        ->assertNotFound();

    $this->get(route('pembayaran.index', ['student' => $student]))
        ->assertOk()
        ->assertDontSee('/pembayaran/siswa/'.$student->id.'/manual', false);
});

it('uses manual detail descriptions in detail display and receipt surfaces', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $payment = createStudentManualPayment($student, $bank, $user, [
        ['description' => 'Infaq', 'amount' => 500000],
        ['description' => 'Donasi Kegiatan', 'amount' => 100000],
    ]);

    expect($payment->detail_display)->toBe('Infaq + Donasi Kegiatan')
        ->and($payment->settlement_status)->toBe('tercatat')
        ->and($payment->status_label)->toBe('Tercatat');

    Livewire::actingAs($user)
        ->test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('Pembayaran Manual')
        ->assertSee('Tercatat')
        ->assertSeeInOrder(['Infaq', 'Rp 600.000'])
        ->assertSee('Donasi Kegiatan')
        ->assertDontSee('Pembayaran SPP');

    $inlinePdf = $this->actingAs($user)->get(route('pembayaran.print', $payment));
    $downloadPdf = $this->get(route('pembayaran.pdf', $payment));

    $inlinePdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $downloadPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($inlinePdf->headers->get('content-disposition'))->toContain('inline')->toContain($payment->receipt_number.'.pdf')
        ->and($downloadPdf->headers->get('content-disposition'))->toContain('attachment')->toContain($payment->receipt_number.'.pdf')
        ->and(Payment::query()->count())->toBe(1)
        ->and($payment->refresh()->receipt_number)->not->toBeEmpty();
});

it('shows manual payments in shared history and Dashboard reporting', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true, 'name' => 'BSI']);
    createStudentManualPayment($student, $bank, $user, [
        ['description' => 'Infaq', 'amount' => 500000],
        ['description' => 'Donasi', 'amount' => 100000],
        ['description' => 'Sumbangan', 'amount' => 50000],
    ]);

    Livewire::actingAs($user)
        ->test(PaymentIndex::class)
        ->set('activeTab', 'history')
        ->assertSee('Manual')
        ->assertSee('Infaq + 2 lainnya')
        ->assertSee('Tercatat')
        ->assertSee('Rp 650.000');

    Livewire::test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 650000.0)
        ->assertViewHas('totalTransaksi', 1)
        ->assertSee('Siswa')
        ->assertSee('Manual')
        ->assertSee('Infaq + 2 lainnya')
        ->assertSee('Rp 650.000');
});

it('excludes cancelled manual payments from active reporting without changing bills', function () {
    $user = User::factory()->create();
    $student = makeBillStudent();
    $bill = makeMonthlyBill($student, makeBillType('SPP'), 1000000);
    $payment = createStudentManualPayment($student, Bank::factory()->create(['is_active' => true]), $user);

    app(PaymentCancellationService::class)->cancel($payment->id, 'Transfer dibatalkan wali', $user->id);

    expect($bill->refresh()->paid_amount)->toBe(0.0)
        ->and($bill->remaining_amount)->toBe(1000000.0)
        ->and($payment->refresh()->status_label)->toBe('Dibatalkan');

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 0.0)
        ->assertViewHas('totalTransaksi', 0);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('tidak mengubah tagihan siswa')
        ->assertDontSee('Saldo tagihan terkait telah dikembalikan');
});

it('blocks bill edit and correction flows for manual payments', function () {
    $user = User::factory()->create();
    $payment = createStudentManualPayment(
        Student::factory()->create(),
        Bank::factory()->create(['is_active' => true]),
        $user,
    );

    $this->actingAs($user)
        ->get(route('pembayaran.edit', $payment))
        ->assertForbidden();

    $this->get(route('pembayaran.koreksi', $payment))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(PaymentIndex::class)
        ->set('activeTab', 'history')
        ->assertDontSee(route('pembayaran.edit', $payment), false)
        ->assertSee(route('pembayaran.manual.edit', $payment), false);

    expect($payment->refresh()->details)->toHaveCount(1);
});

it('permanently deletes manual payment and proof without altering StudentBill', function () {
    Storage::fake('public');
    Storage::disk('public')->put('receipts/manual-proof.pdf', 'proof');
    $user = User::factory()->create();
    $student = makeBillStudent();
    $bill = makeMonthlyBill($student, makeBillType('SPP'), 1000000);
    $payment = createStudentManualPayment($student, Bank::factory()->create(['is_active' => true]), $user);
    $payment->update(['receipt' => 'receipts/manual-proof.pdf']);

    app(PaymentDeletionService::class)->delete($payment->id);

    expect(Payment::query()->whereKey($payment)->exists())->toBeFalse()
        ->and($bill->refresh()->paid_amount)->toBe(0.0)
        ->and($bill->remaining_amount)->toBe(1000000.0);
    Storage::disk('public')->assertMissing('receipts/manual-proof.pdf');
});
