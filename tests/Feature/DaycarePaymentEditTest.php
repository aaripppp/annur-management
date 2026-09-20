<?php

use App\Livewire\Dashboard;
use App\Livewire\DaycarePaymentEdit;
use App\Livewire\DaycarePaymentShow;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeEditableDaycarePayment(array $attributes = [], array $details = []): DaycarePayment
{
    $child = $attributes['child'] ?? DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Fauzan', 'kelas' => 'C']);
    $bank = $attributes['bank'] ?? Bank::factory()->create(['name' => 'BCA', 'is_active' => true]);
    unset($attributes['child'], $attributes['bank']);
    $details = $details ?: [
        ['description' => 'Penitipan Agustus', 'amount' => 750000],
        ['description' => 'Kegiatan', 'amount' => 125000],
    ];
    $payment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-2026-EDIT01',
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => array_sum(array_column($details, 'amount')),
        'notes' => 'Catatan lama',
        ...$attributes,
    ]);

    foreach ($details as $detail) {
        DaycarePaymentDetail::factory()->create([
            'daycare_payment_id' => $payment->id,
            ...$detail,
        ]);
    }

    return $payment;
}

it('route edit Daycare dilindungi autentikasi', function () {
    $payment = makeEditableDaycarePayment();

    $this->get(route('daycare.payment.edit', $payment))->assertRedirect(route('login'));
});

it('memuat pembayaran dan seluruh nilai lama ke form edit', function () {
    $payment = makeEditableDaycarePayment();

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->assertSet('payment_id', $payment->id)
        ->assertSet('daycare_child_id', $payment->daycare_child_id)
        ->assertSet('receipt_number', 'KWT-DC-2026-EDIT01')
        ->assertSet('items.0.description', 'Penitipan Agustus')
        ->assertSet('items.0.amount', 750000)
        ->assertSet('items.1.description', 'Kegiatan')
        ->assertSet('bank_id', (string) $payment->bank_id)
        ->assertSet('payment_date', '2026-08-25')
        ->assertSet('notes', 'Catatan lama')
        ->assertSee('Edit Pembayaran Daycare')
        ->assertSee('KWT-DC-2026-EDIT01');
});

it('dapat menambah menghapus item pertama dan menjaga item tersisa', function () {
    $payment = makeEditableDaycarePayment();
    $component = Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment]);
    $secondKey = $component->get('itemKeys.1');

    $component
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->assertSet('items.0.description', 'Kegiatan')
        ->assertSet('itemKeys.0', $secondKey)
        ->call('addItem')
        ->assertCount('items', 2)
        ->assertSet('items.1.description', '');
});

it('dapat menghapus item tengah dan tidak dapat menghapus item terakhir', function () {
    $payment = makeEditableDaycarePayment(details: [
        ['description' => 'Pertama', 'amount' => 100000],
        ['description' => 'Tengah', 'amount' => 200000],
        ['description' => 'Terakhir', 'amount' => 300000],
    ]);

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->call('removeItem', 1)
        ->assertSet('items.0.description', 'Pertama')
        ->assertSet('items.1.description', 'Terakhir')
        ->call('removeItem', 1)
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->assertSet('items.0.description', 'Pertama');
});

it('mengedit header detail dan menghitung ulang total tanpa membuat transaksi baru', function () {
    $creator = User::factory()->create();
    $oldBank = Bank::factory()->create(['is_active' => true]);
    $newBank = Bank::factory()->cash()->create(['is_active' => true]);
    $payment = makeEditableDaycarePayment([
        'bank' => $oldBank,
        'created_by' => $creator->id,
        'created_at' => '2026-08-20 09:00:00',
    ]);
    $originalId = $payment->id;
    $originalReceipt = $payment->receipt_number;
    $originalChildId = $payment->daycare_child_id;
    $originalCreator = $payment->created_by;
    $originalCreatedAt = $payment->created_at;

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->assertSee('Tunai')
        ->set('items', [
            ['description' => 'Penitipan September', 'amount' => '1.000.000'],
            ['description' => 'Kegiatan Renang', 'amount' => '250.000'],
        ])
        ->set('bank_id', (string) $newBank->id)
        ->set('payment_date', '2026-09-05')
        ->set('notes', 'Catatan terbaru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('daycare.payment.show', $payment));

    $payment->refresh()->load('details');

    expect(DaycarePayment::query()->count())->toBe(1)
        ->and($payment->id)->toBe($originalId)
        ->and($payment->receipt_number)->toBe($originalReceipt)
        ->and($payment->daycare_child_id)->toBe($originalChildId)
        ->and($payment->created_by)->toBe($originalCreator)
        ->and($payment->created_at->equalTo($originalCreatedAt))->toBeTrue()
        ->and($payment->bank_id)->toBe($newBank->id)
        ->and($payment->payment_date->format('Y-m-d'))->toBe('2026-09-05')
        ->and($payment->notes)->toBe('Catatan terbaru')
        ->and($payment->total_amount)->toBe('1250000.00')
        ->and($payment->details->pluck('description')->all())->toBe(['Penitipan September', 'Kegiatan Renang']);
});

it('menolak detail invalid dan bank nonaktif tanpa mengubah transaksi', function () {
    $payment = makeEditableDaycarePayment();
    $inactiveBank = Bank::factory()->create(['is_active' => false]);

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->set('items.0.description', '')
        ->set('items.0.amount', '0')
        ->set('bank_id', (string) $inactiveBank->id)
        ->call('save')
        ->assertHasErrors([
            'items.0.description' => 'required',
            'items.0.amount' => 'gt',
            'bank_id' => 'exists',
        ]);

    expect($payment->refresh()->total_amount)->toBe('875000.00')
        ->and($payment->details()->count())->toBe(2);
});

it('mempertahankan bukti lama ketika tidak ada pengganti', function () {
    Storage::fake('public');
    Storage::disk('public')->put('daycare-payment-proofs/old.pdf', 'old');
    $payment = makeEditableDaycarePayment(['proof_path' => 'daycare-payment-proofs/old.pdf']);

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->set('notes', 'Tanpa ganti bukti')
        ->call('save')
        ->assertHasNoErrors();

    expect($payment->refresh()->proof_path)->toBe('daycare-payment-proofs/old.pdf');
    Storage::disk('public')->assertExists('daycare-payment-proofs/old.pdf');
});

it('mengganti bukti dan membersihkan file lama dengan aman', function () {
    Storage::fake('public');
    Storage::disk('public')->put('daycare-payment-proofs/old.pdf', 'old');
    $payment = makeEditableDaycarePayment(['proof_path' => 'daycare-payment-proofs/old.pdf']);

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->set('proof', UploadedFile::fake()->image('new-proof.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $newPath = $payment->refresh()->proof_path;
    expect($newPath)->not->toBe('daycare-payment-proofs/old.pdf')
        ->and($newPath)->toStartWith('daycare-payment-proofs/');
    Storage::disk('public')->assertMissing('daycare-payment-proofs/old.pdf');
    Storage::disk('public')->assertExists($newPath);
});

it('detail dan receipt HTML langsung mencerminkan nilai hasil edit', function () {
    $user = User::factory()->create();
    $newBank = Bank::factory()->create(['name' => 'BSI', 'is_active' => true]);
    $payment = makeEditableDaycarePayment(['created_by' => $user->id]);

    Livewire::actingAs($user)->test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->set('items', [['description' => 'Penitipan September', 'amount' => 990000]])
        ->set('bank_id', (string) $newBank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment->refresh()])
        ->assertSee('KWT-DC-2026-EDIT01')
        ->assertSee('Penitipan September')
        ->assertSee('Rp 990.000')
        ->assertSee('BSI')
        ->assertSee('10 September 2026');

    $receiptResponse = $this->actingAs($user)->get(route('daycare.payment.print', $payment));

    $receiptResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($receiptResponse->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain('KWT-DC-2026-EDIT01.pdf');
});

it('edit bank dan nominal memperbarui rekap Dashboard secara alami', function () {
    $oldBank = Bank::factory()->create(['name' => 'BCA', 'is_active' => true]);
    $newBank = Bank::factory()->create(['name' => 'BSI', 'is_active' => true]);
    $payment = makeEditableDaycarePayment(['bank' => $oldBank]);

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment])
        ->set('items', [['description' => 'Paket Baru', 'amount' => 1200000]])
        ->set('bank_id', (string) $newBank->id)
        ->call('save')
        ->assertHasNoErrors();

    $dashboard = Livewire::withQueryParams([
        'periode' => Dashboard::PERIOD_CUSTOM,
        'dari' => '2026-08-25',
        'sampai' => '2026-08-25',
    ])->test(Dashboard::class);
    $bankTotals = $dashboard->viewData('bankTotals');

    expect($dashboard->viewData('totalPemasukan'))->toBe(1200000.0)
        ->and($bankTotals->get($oldBank->id)['daycare_total'])->toBe(0.0)
        ->and($bankTotals->get($newBank->id)['daycare_total'])->toBe(1200000.0);
});
