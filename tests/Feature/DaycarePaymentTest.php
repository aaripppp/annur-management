<?php

use App\Livewire\DaycareDetail;
use App\Livewire\DaycarePaymentCreate;
use App\Livewire\DaycarePaymentShow;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createDaycareTransaction(DaycareChild $child, array $attributes = [], array $details = []): DaycarePayment
{
    $payment = DaycarePayment::factory()->create(array_merge([
        'daycare_child_id' => $child->id,
        'total_amount' => array_sum(array_column($details, 'amount')) ?: 100000,
    ], $attributes));

    foreach ($details ?: [['description' => 'Pembayaran Daycare', 'amount' => 100000]] as $detail) {
        DaycarePaymentDetail::factory()->create([
            'daycare_payment_id' => $payment->id,
            ...$detail,
        ]);
    }

    return $payment;
}

it('memulai form dengan tepat satu item kosong', function () {
    $child = DaycareChild::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->assertSet('items', [['description' => '', 'amount' => '']]);
});

it('dapat menambah item pembayaran', function () {
    $child = DaycareChild::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->call('addItem')
        ->assertCount('items', 2)
        ->assertSet('items.1.description', '')
        ->assertSet('items.1.amount', '');
});

it('merender seluruh item sebagai row dalam satu container rincian', function () {
    $child = DaycareChild::factory()->create();

    $component = Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items', [
            ['description' => 'Penitipan Bulan Agustus', 'amount' => 1500000],
            ['description' => 'Kegiatan Daycare', 'amount' => 500000],
        ])
        ->assertSee('Penitipan Bulan Agustus')
        ->assertSee('Kegiatan Daycare')
        ->assertSeeHtml('data-testid="daycare-total-amount"')
        ->assertSee('Rp 2.000.000');

    expect(substr_count($component->html(), 'Rincian Pembayaran'))->toBe(1)
        ->and(substr_count($component->html(), 'data-testid="daycare-payment-items"'))->toBe(1)
        ->and(substr_count($component->html(), 'data-testid="daycare-payment-item-row"'))->toBe(2)
        ->and($component->html())->toContain('whitespace-nowrap');
});

it('dapat menghapus item dan merapikan indeks', function () {
    $child = DaycareChild::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Item Pertama')
        ->call('addItem')
        ->set('items.1.description', 'Item Kedua')
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->assertSet('items.0.description', 'Item Kedua');
});

it('menghapus item pertama mempertahankan nilai dan identitas item kedua', function () {
    $child = DaycareChild::factory()->create();
    $component = Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan Agustus')
        ->set('items.0.amount', 750000)
        ->call('addItem')
        ->set('items.1.description', 'Kegiatan Daycare')
        ->set('items.1.amount', 100000);

    $remainingKey = $component->get('itemKeys.1');

    $component
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->assertSet('items.0.description', 'Kegiatan Daycare')
        ->assertSet('items.0.amount', 100000)
        ->assertSet('itemKeys.0', $remainingKey)
        ->assertSee('Kegiatan Daycare')
        ->assertDontSee('Penitipan Agustus')
        ->assertSee('Rp 100.000');

    expect($component->instance()->totalAmount())->toBe(100000)
        ->and(substr_count($component->html(), 'data-testid="daycare-payment-item-row"'))->toBe(1)
        ->and($component->html())->toContain('wire:model.live.debounce.300ms="items.0.description"');

    $component
        ->call('addItem')
        ->assertCount('items', 2)
        ->assertSet('items.0.description', 'Kegiatan Daycare')
        ->assertSet('items.0.amount', 100000)
        ->assertSet('items.1.description', '')
        ->assertSet('items.1.amount', '');
});

it('menghapus item tengah mempertahankan kedua item tetangga', function () {
    $child = DaycareChild::factory()->create();
    $component = Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Item Pertama')
        ->set('items.0.amount', 750000)
        ->call('addItem')
        ->set('items.1.description', 'Item Tengah')
        ->set('items.1.amount', 100000)
        ->call('addItem')
        ->set('items.2.description', 'Item Terakhir')
        ->set('items.2.amount', 50000);

    $firstKey = $component->get('itemKeys.0');
    $lastKey = $component->get('itemKeys.2');

    $component
        ->call('removeItem', 1)
        ->assertCount('items', 2)
        ->assertSet('items.0.description', 'Item Pertama')
        ->assertSet('items.0.amount', 750000)
        ->assertSet('items.1.description', 'Item Terakhir')
        ->assertSet('items.1.amount', 50000)
        ->assertSet('itemKeys.0', $firstKey)
        ->assertSet('itemKeys.1', $lastKey)
        ->assertSee('Item Pertama')
        ->assertSee('Item Terakhir')
        ->assertDontSee('Item Tengah');

    expect($component->instance()->totalAmount())->toBe(800000);
});

it('tidak dapat menghapus item terakhir', function () {
    $child = DaycareChild::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->call('removeItem', 0)
        ->assertSet('items', [['description' => '', 'amount' => '']]);
});

it('memformat dan menormalisasi nominal Rupiah', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    $component = Livewire::test(DaycarePaymentCreate::class, ['child' => $child]);

    expect($component->instance()->formatAmount('750000'))->toBe('750.000')
        ->and($component->instance()->formatAmount('125.000'))->toBe('125.000');

    $component
        ->set('items.0.description', 'Penitipan Agustus')
        ->set('items.0.amount', '750.000')
        ->set('bank_id', (string) $bank->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(DaycarePaymentDetail::query()->sole()->amount)->toBe('750000.00');
});

it('menyimpan satu transaksi dengan dua detail dan total hasil hitung server', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'is_active' => true]);

    $component = Livewire::actingAs($user)
        ->test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items', [
            ['description' => ' Penitipan Bulan Agustus ', 'amount' => '750000'],
            ['description' => 'Kegiatan Daycare', 'amount' => '125000'],
        ])
        ->set('bank_id', (string) $bank->id)
        ->set('payment_date', '2026-08-25')
        ->set('notes', '')
        ->call('save')
        ->assertHasNoErrors();

    $payment = DaycarePayment::query()->with('details')->sole();
    $component->assertRedirect(route('daycare.payment.show', $payment));

    expect($payment->total_amount)->toBe('875000.00')
        ->and($payment->details)->toHaveCount(2)
        ->and($payment->details->pluck('description')->all())->toBe(['Penitipan Bulan Agustus', 'Kegiatan Daycare'])
        ->and($payment->details->sum(fn (DaycarePaymentDetail $detail): float => (float) $detail->amount))->toEqual(875000)
        ->and($payment->daycare_child_id)->toBe($child->id)
        ->and($payment->bank_id)->toBe($bank->id)
        ->and($payment->payment_date->format('Y-m-d'))->toBe('2026-08-25')
        ->and($payment->notes)->toBeNull()
        ->and($payment->proof_path)->toBeNull()
        ->and($payment->created_by)->toBe($user->id);
});

it('menyimpan pembayaran Daycare melalui penerimaan tunai aktif', function () {
    $child = DaycareChild::factory()->create();
    $cash = Bank::factory()->cash()->create(['is_active' => true]);

    Livewire::actingAs(User::factory()->create())
        ->test(DaycarePaymentCreate::class, ['child' => $child])
        ->assertSee('Tunai')
        ->set('items.0.description', 'Penitipan Tunai')
        ->set('items.0.amount', 500000)
        ->set('bank_id', (string) $cash->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(DaycarePayment::query()->sole()->bank_id)->toBe($cash->id);
});

it('menolak deskripsi item kosong tanpa menyimpan transaksi', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.amount', '100000')
        ->set('bank_id', (string) $bank->id)
        ->call('save')
        ->assertHasErrors(['items.0.description' => 'required']);

    expect(DaycarePayment::query()->count())->toBe(0);
});

it('menolak nominal kosong nol dan negatif', function (string $amount, string $rule) {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan')
        ->set('items.0.amount', $amount)
        ->set('bank_id', (string) $bank->id)
        ->call('save')
        ->assertHasErrors(['items.0.amount' => $rule]);

    expect(DaycarePayment::query()->count())->toBe(0);
})->with([
    ['', 'required'],
    ['0', 'gt'],
    ['-1000', 'gt'],
]);

it('satu item invalid membatalkan seluruh transaksi', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items', [
            ['description' => 'Item Valid', 'amount' => '100000'],
            ['description' => '', 'amount' => '50000'],
        ])
        ->set('bank_id', (string) $bank->id)
        ->call('save')
        ->assertHasErrors(['items.1.description' => 'required']);

    expect(DaycarePayment::query()->count())->toBe(0)
        ->and(DaycarePaymentDetail::query()->count())->toBe(0);
});

it('menerima bank aktif dan menolak bank invalid atau nonaktif', function (string $selection, bool $valid) {
    $child = DaycareChild::factory()->create();
    $activeBank = Bank::factory()->create(['is_active' => true]);
    $inactiveBank = Bank::factory()->create(['is_active' => false]);
    $bankId = match ($selection) {
        'active' => $activeBank->id,
        'inactive' => $inactiveBank->id,
        default => 999999,
    };

    $component = Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan')
        ->set('items.0.amount', '100000')
        ->set('bank_id', (string) $bankId)
        ->call('save');

    if ($valid) {
        $component->assertHasNoErrors();
        expect(DaycarePayment::query()->count())->toBe(1);
    } else {
        $component->assertHasErrors(['bank_id' => 'exists']);
        expect(DaycarePayment::query()->count())->toBe(0);
    }
})->with([
    ['active', true],
    ['inactive', false],
    ['missing', false],
]);

it('hanya menampilkan bank aktif sebagai pilihan', function () {
    $child = DaycareChild::factory()->create();
    $activeBank = Bank::factory()->create(['name' => 'Bank Aktif', 'account_number' => '1234567890', 'is_active' => true]);
    Bank::factory()->create(['name' => 'Bank Nonaktif', 'is_active' => false]);

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->assertSee($activeBank->displayLabel())
        ->assertDontSee('Bank Nonaktif');
});

it('memvalidasi tanggal pembayaran dan keberadaan anak', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('daycare_child_id', 999999)
        ->set('items.0.description', 'Penitipan')
        ->set('items.0.amount', '100000')
        ->set('bank_id', (string) $bank->id)
        ->set('payment_date', '')
        ->call('save')
        ->assertHasErrors([
            'daycare_child_id' => 'exists',
            'payment_date' => 'required',
        ]);
});

it('menyimpan bukti transfer valid di direktori khusus Daycare', function () {
    Storage::fake('public');
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();
    $proof = UploadedFile::fake()->image('bukti.jpg');

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan')
        ->set('items.0.amount', '100000')
        ->set('bank_id', (string) $bank->id)
        ->set('proof', $proof)
        ->call('save')
        ->assertHasNoErrors();

    $path = DaycarePayment::query()->sole()->proof_path;

    expect($path)->toStartWith('daycare-payment-proofs/');
    Storage::disk('public')->assertExists($path);
});

it('menolak format bukti transfer yang tidak diizinkan', function () {
    Storage::fake('public');
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items.0.description', 'Penitipan')
        ->set('items.0.amount', '100000')
        ->set('bank_id', (string) $bank->id)
        ->set('proof', UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'))
        ->call('save')
        ->assertHasErrors(['proof' => 'mimes']);

    expect(DaycarePayment::query()->count())->toBe(0);
});

it('child detail menampilkan profile card aktif dan data orang tua kosong dengan aman', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Muhammad Vigo',
        'nama_panggilan' => 'Vigo',
        'tempat_lahir' => 'Bekasi',
        'tanggal_lahir' => '2022-08-01',
        'jenis_kelamin' => 'L',
        'kelas' => 'A',
        'alamat' => 'JL kelinci',
        'nama_ayah' => null,
        'no_telp_ayah' => null,
        'nama_ibu' => null,
        'no_telp_ibu' => null,
    ]);

    $component = Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSee('Aktif')
        ->assertSee('Vigo')
        ->assertSee('Kelas A')
        ->assertSee('Kelas')
        ->assertSee('Tempat, Tanggal Lahir')
        ->assertSee('Jenis Kelamin')
        ->assertSee('Alamat')
        ->assertSee('Ayah')
        ->assertSee('Ibu')
        ->assertSee('Muhammad Vigo')
        ->assertSee('Bekasi, 01 Agustus 2022')
        ->assertSee('Laki-laki')
        ->assertSee('JL kelinci')
        ->assertDontSee('Nama Lengkap')
        ->assertDontSee('Nama Panggilan');

    expect($component->html())->toContain('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')
        ->toContain('data-profile-field="father"')
        ->toContain('data-profile-field="mother"')
        ->and(substr_count($component->html(), '>-</p>'))->toBeGreaterThanOrEqual(2);
});

it('child detail menampilkan badge nonaktif parent phone dan alamat panjang dengan aman', function () {
    $longAddress = str_repeat('Jalan Kelinci Blok Panjang ', 8);
    $child = DaycareChild::factory()->inactive()->create([
        'nama_lengkap' => 'Anak Nonaktif',
        'nama_ayah' => 'Herman',
        'no_telp_ayah' => '081317041050',
        'nama_ibu' => 'Yeni',
        'no_telp_ibu' => '085591811723',
        'alamat' => $longAddress,
    ]);

    $component = Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSee('Nonaktif')
        ->assertSee('Herman')
        ->assertSee('081317041050')
        ->assertSee('Yeni')
        ->assertSee('085591811723')
        ->assertSee($longAddress);

    expect($component->html())->toContain('whitespace-pre-line break-words')
        ->toContain('data-parent-value="father"')
        ->toContain('data-parent-value="mother"')
        ->toMatch('/data-parent-value="father".*?Herman.*?&bull;.*?081317041050.*?<\/p>/s')
        ->toMatch('/data-parent-value="mother".*?Yeni.*?&bull;.*?085591811723.*?<\/p>/s');
});

it('profile card tidak menampilkan bullet ketika telepon orang tua kosong', function () {
    $child = DaycareChild::factory()->create([
        'nama_ayah' => 'Herman',
        'no_telp_ayah' => null,
        'nama_ibu' => null,
        'no_telp_ibu' => null,
    ]);

    $html = Livewire::test(DaycareDetail::class, ['child' => $child])->html();
    $fatherStart = strpos($html, 'data-profile-field="father"');
    $motherStart = strpos($html, 'data-profile-field="mother"');
    $fatherHtml = substr($html, $fatherStart, $motherStart - $fatherStart);

    expect($fatherHtml)->toContain('Herman')
        ->not->toContain('&bull;')
        ->not->toContain('data-parent-value="father"');
});

it('profile card menampilkan fallback untuk data lahir yang tidak tersedia', function () {
    $child = DaycareChild::factory()->make([
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
    ]);
    $child->forceFill(['id' => 999]);

    $html = view('livewire.daycare-detail', [
        'child' => $child,
        'payments' => new LengthAwarePaginator([], 0, 10),
        'totalTransactions' => 0,
        'totalPayments' => 0,
        'lastPayment' => null,
        'isEditModalOpen' => false,
        'isDeleteModalOpen' => false,
    ])->render();

    expect($html)->toMatch('/data-profile-field="birth".*?>-<\/p>/s');
});

it('history child menampilkan receipt summary bank total dan seluruh aksi', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Daycare']);
    $bank = Bank::factory()->create(['name' => 'BRI']);
    $payment = createDaycareTransaction($child, [
        'receipt_number' => 'KWT-DC-2026-000001',
        'bank_id' => $bank->id,
        'total_amount' => 875000,
        'payment_date' => '2026-08-25',
    ], [
        ['description' => 'Penitipan Agustus', 'amount' => 750000],
        ['description' => 'Kegiatan Daycare', 'amount' => 125000],
    ]);

    $component = Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSee('Tanggal TF')
        ->assertSee('KWT-DC-2026-000001')
        ->assertSee('Penitipan Agustus + Kegiatan Daycare')
        ->assertSee('BRI')
        ->assertSee('Rp 875.000')
        ->assertSee('Total Transaksi')
        ->assertSee('Total Pembayaran')
        ->assertSee('Riwayat pembayaran Ahmad Daycare.')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $payment).'"')
        ->assertSeeHtml('href="'.route('daycare.payment.edit', $payment).'"')
        ->assertSeeHtml('wire:click="confirmDelete('.$payment->id.')"')
        ->assertSeeHtml('href="'.route('daycare.payment.create', $child).'"');

    expect(substr_count($component->html(), 'wire:key="daycare-payment-'.$payment->id.'"'))->toBe(1);
});

it('detail menampilkan setiap item total catatan dan bukti', function () {
    $user = User::factory()->create(['name' => 'Admin Daycare']);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Fauzan', 'kelas' => 'C']);
    $payment = createDaycareTransaction($child, [
        'total_amount' => 875000,
        'notes' => 'Pembayaran tunai',
        'proof_path' => 'daycare-payment-proofs/bukti.pdf',
        'created_by' => $user->id,
    ], [
        ['description' => 'Penitipan Agustus', 'amount' => 750000],
        ['description' => 'Kegiatan Daycare', 'amount' => 125000],
    ]);

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment])
        ->assertSeeInOrder(['Penitipan Agustus', 'Rp 750.000', 'Kegiatan Daycare', 'Rp 125.000'])
        ->assertSee('Rp 875.000')
        ->assertSee('Pembayaran tunai')
        ->assertSee('Lihat / Unduh Bukti');
});

it('history per anak menggunakan created_at newest first dan summary menjumlahkan total header', function () {
    $child = DaycareChild::factory()->create();
    $old = createDaycareTransaction($child, [
        'total_amount' => 100000,
        'payment_date' => '2026-08-25',
        'created_at' => '2026-09-07 09:00:00',
        'updated_at' => '2026-09-07 09:00:00',
    ]);
    $new = createDaycareTransaction($child, [
        'total_amount' => 250000,
        'payment_date' => '2026-08-01',
        'created_at' => '2026-09-07 10:00:00',
        'updated_at' => '2026-09-07 10:00:00',
    ]);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSeeInOrder(['01 Agt 2026', '25 Agt 2026'])
        ->assertSee('Rp 350.000');

    expect($new->id)->toBeGreaterThan($old->id);
});

it('history menautkan transaksi ke detail pembayaran yang tepat', function () {
    $child = DaycareChild::factory()->create();
    $payment = createDaycareTransaction($child);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSeeHtml('href="'.route('daycare.payment.show', $payment).'"');
});

it('hapus transaksi dari child detail menggunakan konfirmasi dan service yang ada', function () {
    $child = DaycareChild::factory()->create();
    $payment = createDaycareTransaction($child, ['receipt_number' => 'KWT-DC-CHILD-DELETE']);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSee('Hapus transaksi KWT-DC-CHILD-DELETE?')
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('Belum ada transaksi biaya.');

    expect(DaycarePayment::query()->find($payment->id))->toBeNull()
        ->and(DaycarePaymentDetail::query()->where('daycare_payment_id', $payment->id)->exists())->toBeFalse();
});

it('child tanpa transaksi menampilkan empty state dan input pembayaran', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Tanpa Transaksi']);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSee('Riwayat pembayaran Anak Tanpa Transaksi.')
        ->assertSee('Belum ada transaksi biaya.')
        ->assertSee('Input Pembayaran')
        ->assertSeeHtml('href="'.route('daycare.payment.create', $child).'"');
});

it('migration membackfill pembayaran legacy menjadi satu detail tanpa kehilangan data', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_08_25_074626_create_daycare_payment_details_table.php');

    $migration->down();

    $paymentId = DB::table('daycare_payments')->insertGetId([
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'description' => 'Penitipan Agustus',
        'amount' => 750000,
        'notes' => null,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(Schema::hasColumn('daycare_payments', 'description'))->toBeFalse()
        ->and(Schema::hasColumn('daycare_payments', 'amount'))->toBeFalse()
        ->and(DB::table('daycare_payments')->where('id', $paymentId)->value('total_amount'))->toEqual(750000)
        ->and(DB::table('daycare_payment_details')->where('daycare_payment_id', $paymentId)->count())->toBe(1)
        ->and(DB::table('daycare_payment_details')->where('daycare_payment_id', $paymentId)->value('description'))->toBe('Penitipan Agustus')
        ->and(DB::table('daycare_payment_details')->where('daycare_payment_id', $paymentId)->value('amount'))->toEqual(750000);
});
