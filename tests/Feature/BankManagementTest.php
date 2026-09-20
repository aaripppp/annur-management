<?php

use App\Livewire\BankManagement;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use Livewire\Livewire;

it('membuat bank baru', function () {
    Livewire::test(BankManagement::class)
        ->call('openModal')
        ->set('name', 'Bank Syariah Indonesia')
        ->set('account_number', '1234567890')
        ->set('account_name', 'Yayasan Annur')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $bank = Bank::where('name', 'Bank Syariah Indonesia')->first();

    expect($bank)->not->toBeNull()
        ->and($bank->type)->toBe(Bank::TYPE_BANK)
        ->and($bank->account_number)->toBe('1234567890')
        ->and($bank->is_active)->toBeTrue();
});

it('defaults existing-style Bank records to type bank', function () {
    $bank = Bank::query()->create([
        'name' => 'Bank Default',
        'account_number' => '100200300',
        'account_name' => 'Yayasan Annur',
        'is_active' => true,
    ]);

    expect($bank->fresh()->type)->toBe(Bank::TYPE_BANK)
        ->and($bank->isBank())->toBeTrue()
        ->and($bank->isCash())->toBeFalse()
        ->and($bank->reportingTypeLabel())->toBe('TRANSFER / DEBET');
});

it('membuat penerimaan tunai tanpa nomor dan pemilik rekening', function () {
    Livewire::test(BankManagement::class)
        ->call('openModal')
        ->assertSet('type', Bank::TYPE_BANK)
        ->set('name', 'Kas Loket')
        ->set('type', Bank::TYPE_CASH)
        ->set('account_number', '')
        ->set('account_name', '')
        ->call('save')
        ->assertHasNoErrors();

    $cash = Bank::query()->where('name', 'Kas Loket')->sole();

    expect($cash->type)->toBe(Bank::TYPE_CASH)
        ->and($cash->account_number)->toBeNull()
        ->and($cash->account_name)->toBeNull()
        ->and($cash->isCash())->toBeTrue()
        ->and($cash->paymentLabel())->toBe('Tunai')
        ->and($cash->reportingTypeLabel())->toBe('TUNAI / CASH');
});

it('menolak jenis penerimaan yang tidak valid', function () {
    Livewire::test(BankManagement::class)
        ->call('openModal')
        ->set('name', 'Metode Tidak Valid')
        ->set('type', 'crypto')
        ->call('save')
        ->assertHasErrors(['type']);

    expect(Bank::query()->where('name', 'Metode Tidak Valid')->exists())->toBeFalse();
});

it('bank tetap mewajibkan nomor dan pemilik rekening', function () {
    Livewire::test(BankManagement::class)
        ->call('openModal')
        ->set('name', 'Bank Wajib Rekening')
        ->set('type', Bank::TYPE_BANK)
        ->set('account_number', '')
        ->set('account_name', '')
        ->call('save')
        ->assertHasErrors(['account_number', 'account_name']);
});

it('mengubah rekening bank menjadi tunai membersihkan detail rekening', function () {
    $bank = Bank::factory()->create();

    Livewire::test(BankManagement::class)
        ->call('edit', $bank->id)
        ->assertSet('type', Bank::TYPE_BANK)
        ->set('type', Bank::TYPE_CASH)
        ->set('name', 'Tunai')
        ->call('save')
        ->assertHasNoErrors();

    expect($bank->fresh()->type)->toBe(Bank::TYPE_CASH)
        ->and($bank->fresh()->account_number)->toBeNull()
        ->and($bank->fresh()->account_name)->toBeNull();
});

it('keeps different Bank records independently identifiable', function () {
    $first = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111']);
    $second = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222']);

    expect($first->id)->not->toBe($second->id)
        ->and($first->displayLabel())->toBe('BSI — 111')
        ->and($second->displayLabel())->toBe('BSI — 222')
        ->and($first->optionLabel())->not->toBe($second->optionLabel());
});

it('formats cash and missing account numbers safely', function () {
    $cash = Bank::factory()->cash()->create([
        'name' => 'Kas Utama',
        'account_number' => null,
    ]);
    $bankWithoutAccount = Bank::factory()->create([
        'name' => 'BSI Tanpa Rekening',
        'account_number' => null,
    ]);

    expect($cash->displayLabel())->toBe('Tunai')
        ->and($cash->displayAccountNumber())->toBeNull()
        ->and($cash->displayLabel())->not->toContain('—')
        ->and($bankWithoutAccount->displayLabel())->toBe('BSI Tanpa Rekening')
        ->and($bankWithoutAccount->displayAccountNumber())->toBeNull();
});

it('edit memuat bank yang benar berdasarkan ID', function () {
    Bank::factory()->create(['name' => 'Bank A']);
    $second = Bank::factory()->create(['name' => 'Bank B', 'account_number' => '222222']);

    Livewire::test(BankManagement::class)
        ->call('edit', $second->id)
        ->assertHasNoErrors()
        ->assertSet('isEditing', true)
        ->assertSet('bankId', $second->id)
        ->assertSet('name', 'Bank B')
        ->assertSet('account_number', '222222');
});

it('status bank memakai satu radio group dan reset ke default aktif', function () {
    $inactiveBank = Bank::factory()->create(['is_active' => false]);
    $component = Livewire::test(BankManagement::class)
        ->call('edit', $inactiveBank->id)
        ->assertSet('is_active', false);

    expect(substr_count($component->html(), 'name="is_active"'))->toBe(2)
        ->and(substr_count($component->html(), 'wire:model="is_active"'))->toBe(2);

    $component
        ->set('is_active', true)
        ->assertSet('is_active', true)
        ->set('is_active', false)
        ->assertSet('is_active', false)
        ->call('closeModal')
        ->call('openModal')
        ->assertSet('isEditing', false)
        ->assertSet('is_active', true);
});

it('mengupdate bank dan list refresh tanpa reload', function () {
    $bank = Bank::factory()->create(['name' => 'Bank C', 'account_number' => '333333', 'account_name' => 'Yayasan']);

    Livewire::test(BankManagement::class)
        ->call('edit', $bank->id)
        ->set('name', 'Bank D')
        ->set('account_number', '444444')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee('Bank C')
        ->assertSee('Bank D');

    $updated = Bank::find($bank->id);

    expect($updated->name)->toBe('Bank D')
        ->and($updated->account_number)->toBe('444444');
});

it('menghapus bank tanpa transaksi berhasil', function () {
    $bank = Bank::factory()->create(['name' => 'Bank E']);

    Livewire::test(BankManagement::class)
        ->call('confirmDelete', $bank->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete');

    expect(Bank::find($bank->id))->toBeNull();
});

it('menghapus bank yang masih punya transaksi pembayaran ditolak', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Bank F']);
    $student = makeBillStudent();
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    $bill = makeMonthlyBill($student, $type, 970000);

    Payment::create([
        'receipt_number' => 'REC-BANK-'.random_int(1000, 9999),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    Livewire::test(BankManagement::class)
        ->call('confirmDelete', $bank->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('masih memiliki transaksi pembayaran', false);

    expect(Bank::find($bank->id))->not->toBeNull();
});

it('menghapus satu bank tidak mengganggu bank lain', function () {
    $target = Bank::factory()->create(['name' => 'Bank G']);
    $other = Bank::factory()->create(['name' => 'Bank H']);

    Livewire::test(BankManagement::class)
        ->call('confirmDelete', $target->id)
        ->call('delete');

    expect(Bank::find($target->id))->toBeNull()
        ->and(Bank::find($other->id))->not->toBeNull();
});

it('wire:key pada baris list menggunakan ID bank', function () {
    $bank = Bank::factory()->create(['name' => 'Bank I']);

    Livewire::test(BankManagement::class)
        ->assertSee('bank-'.$bank->id, false);
});

it('validasi menolak nomor rekening kosong', function () {
    Livewire::test(BankManagement::class)
        ->call('openModal')
        ->set('name', 'Bank J')
        ->set('account_number', '')
        ->set('account_name', 'Yayasan')
        ->call('save')
        ->assertHasErrors(['account_number']);
});
