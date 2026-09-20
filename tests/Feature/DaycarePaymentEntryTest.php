<?php

use App\Livewire\DaycareDailyReport;
use App\Livewire\DaycarePaymentEdit;
use App\Livewire\DaycarePaymentEntry;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Daycare payment entry flow
 |--------------------------------------------------------------------------
 |
 | Daycare > Pembayaran takes the operator straight to a search page where
 | they pick the child and are linked into the existing daycare payment
 | creation form. These tests guard the new entry surface, the search
 | behaviour, the child-selection state, and that the daycare domain stays
 | fully independent from the student payment tables.
 |
 */

function daycareEntryChild(string $fullName, string $nickname, string $kelas = 'A', bool $active = true): DaycareChild
{
    return DaycareChild::factory()->create([
        'nama_lengkap' => $fullName,
        'nama_panggilan' => $nickname,
        'kelas' => $kelas,
        'is_active' => $active,
    ]);
}

function daycareEntryPayment(DaycareChild $child, array $attributes = [], array $details = []): DaycarePayment
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

it('renders the daycare payment workspace with both tabs', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.payment.entry'))
        ->assertOk()
        ->assertSee('Pembayaran Daycare')
        ->assertSee('Riwayat Transaksi')
        ->assertSee('Cari Anak')
        ->assertSee('Cari anak Daycare untuk mencatat pembayaran.');
});

it('finds a child when searching by full name', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis', 'B');

    Livewire::test(DaycarePaymentEntry::class)
        ->set('search', 'Bilqis')
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Kelas B');
});

it('finds a child when searching by nickname', function () {
    daycareEntryChild('Bilqis Ramadhani', 'Qis');

    Livewire::test(DaycarePaymentEntry::class)
        ->set('search', 'Qis')
        ->assertSee('Bilqis Ramadhani');
});

it('does not return unrelated children in the search results', function () {
    daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryChild('Muhammad Rafa', 'Rafa');

    Livewire::test(DaycarePaymentEntry::class)
        ->set('search', 'Bilqis')
        ->assertSee('Bilqis Ramadhani')
        ->assertDontSee('Muhammad Rafa');
});

it('requires at least two characters before searching', function () {
    daycareEntryChild('Bilqis Ramadhani', 'Qis');

    Livewire::test(DaycarePaymentEntry::class)
        ->set('search', 'B')
        ->assertDontSee('Bilqis Ramadhani');
});

it('shows the correct information once a child is selected', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis', 'B');

    Livewire::test(DaycarePaymentEntry::class)
        ->set('search', 'Bilqis')
        ->call('selectChild', $child->id)
        ->assertSet('selectedChildId', $child->id)
        ->assertSet('search', '')
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Qis')
        ->assertSee('Kelas B')
        ->assertSee('Aktif');
});

it('ignores selecting a child that does not exist', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->call('selectChild', 99999)
        ->assertSet('selectedChildId', null);
});

it('changeChild returns to the search state', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('selectChild', $child->id)
        ->call('changeChild')
        ->assertSet('selectedChildId', null)
        ->assertSet('search', '')
        ->assertSee('Cari anak Daycare untuk mencatat pembayaran.');
});

it('links the selected child to the existing daycare payment creation form', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('selectChild', $child->id)
        ->assertSee(route('daycare.payment.create', $child), false);
});

it('keeps the selected child when the entry page is reloaded', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');

    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.payment.entry', ['child' => $child->id]))
        ->assertOk()
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Input Pembayaran');
});

it('does not reference the student payment pages from the entry flow', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $student = Student::factory()->create();

    Livewire::test(DaycarePaymentEntry::class)
        ->call('selectChild', $child->id)
        ->assertDontSee(route('pembayaran.create'))
        ->assertDontSee('/pembayaran/siswa/'.$student->id.'/manual');
});

it('still serves the existing daycare payment creation form for the selected child', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');

    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.payment.create', $child))
        ->assertOk()
        ->assertSee('Bilqis Ramadhani');
});

it('keeps the daycare data page independent', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.index'))
        ->assertOk()
        ->assertSee('Data Anak Daycare')
        ->assertSee('Tambah Anak');
});

it('keeps the daycare report page independent from student reports', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    DaycarePayment::factory()->create([
        'daycare_child_id' => daycareEntryChild('Bilqis Ramadhani', 'Qis')->id,
        'bank_id' => $cash->id,
        'payment_date' => '2026-08-27',
        'total_amount' => 100000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test(DaycareDailyReport::class, ['reportDate' => '2026-08-27'])
        ->assertSee('Laporan Penerimaan')
        ->assertSee('SPP Daycare')
        ->assertDontSee(route('laporan.index'))
        ->assertDontSee(route('laporan.harian.export'));
});

it('keeps the transaction history hidden on the pembayaran tab', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('selectChild', $child->id)
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Input Pembayaran')
        ->assertDontSee($payment->receipt_number)
        ->assertDontSee('Nama Anak');
});

it('defaults to the pembayaran tab with the search prompt', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->assertSet('activeTab', 'pembayaran')
        ->assertSee('Cari Anak')
        ->assertDontSee('Cari Transaksi');
});

it('rejects an unknown tab and falls back to the pembayaran tab', function () {
    Livewire::withQueryParams(['tab' => 'bogus'])
        ->test(DaycarePaymentEntry::class)
        ->assertSet('activeTab', 'pembayaran');
});

it('opens the riwayat tab from the query string like the student page', function () {
    Livewire::withQueryParams(['tab' => 'history'])
        ->test(DaycarePaymentEntry::class)
        ->assertSet('activeTab', 'history')
        ->assertSee('Cari Transaksi');
});

it('can switch between the two tabs', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSet('activeTab', 'history')
        ->assertSee('Cari Transaksi')
        ->assertSee('Belum ada transaksi pembayaran.')
        ->call('setActiveTab', 'pembayaran')
        ->assertSet('activeTab', 'pembayaran')
        ->assertSee('Cari Anak')
        ->assertDontSee('Cari Transaksi');
});

it('shows the transaction history once the riwayat tab is active', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Riwayat Transaksi')
        ->assertSee('Daftar seluruh transaksi pembayaran anak Daycare.');
});

it('lists the payment records of all daycare children in the history tab', function () {
    $firstChild = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $secondChild = daycareEntryChild('Muhammad Rafa', 'Rafa');
    $firstPayment = daycareEntryPayment($firstChild, ['receipt_number' => 'KWT-DC-2026-000001']);
    $secondPayment = daycareEntryPayment($secondChild, ['receipt_number' => 'KWT-DC-2026-000002']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee($firstPayment->receipt_number)
        ->assertSee($secondPayment->receipt_number)
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Muhammad Rafa');
});

it('shows the child name and class inside each history row', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis', 'B');
    daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Daycare • Kelas B');
});

it('sorts the history by created_at newest first instead of payment_date', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [
        'payment_date' => '2026-09-07',
        'receipt_number' => 'KWT-DC-RECORDED-EARLIER',
        'created_at' => '2026-09-07 09:00:00',
        'updated_at' => '2026-09-07 09:00:00',
    ]);
    daycareEntryPayment($child, [
        'payment_date' => '2026-09-05',
        'receipt_number' => 'KWT-DC-RECORDED-LATER',
        'created_at' => '2026-09-07 10:00:00',
        'updated_at' => '2026-09-07 10:00:00',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-DC-RECORDED-LATER', 'KWT-DC-RECORDED-EARLIER']);
});

it('uses id descending when daycare transactions share created_at', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $createdAt = '2026-09-07 10:00:00';
    $first = daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-SAME-TIME-FIRST',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $second = daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-SAME-TIME-SECOND',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    expect($second->id)->toBeGreaterThan($first->id);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-DC-SAME-TIME-SECOND', 'KWT-DC-SAME-TIME-FIRST']);
});

it('rendering and filtering daycare history does not mutate transaction dates', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-DATE-IMMUTABLE',
        'payment_date' => '2026-09-05',
        'created_at' => '2026-09-07 10:00:00',
        'updated_at' => '2026-09-07 10:00:00',
    ]);
    $dateColumns = ['payment_date', 'created_at', 'updated_at'];
    $dates = array_intersect_key($payment->fresh()->getRawOriginal(), array_flip($dateColumns));

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('historySearch', 'DATE-IMMUTABLE')
        ->assertSee('KWT-DC-DATE-IMMUTABLE');

    $freshDates = array_intersect_key($payment->fresh()->getRawOriginal(), array_flip($dateColumns));

    expect($freshDates)->toBe($dates);
});

it('joins many details into a compact summary', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [], [
        ['description' => 'Kegiatan Pagi', 'amount' => 50000],
        ['description' => 'Kegiatan Siang', 'amount' => 50000],
        ['description' => 'Kegiatan Sore', 'amount' => 50000],
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Kegiatan Pagi + 2 lainnya')
        ->assertSee('Rp 150.000');
});

it('labels a cash receipt as Tunai using the bank paymentLabel', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $cash = Bank::factory()->cash()->create();
    daycareEntryPayment($child, ['bank_id' => $cash->id]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Tunai')
        ->assertDontSee('Tunai —');
});

it('shows the bank account number in daycare transaction history', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create(['name' => 'BSI Daycare', 'account_number' => '9988776655']);
    daycareEntryPayment($child, ['bank_id' => $bank->id]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('BSI Daycare')
        ->assertSee('9988776655');
});

it('shows the payment records once a transaction exists', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create(['name' => 'BSI']);
    daycareEntryPayment($child, [
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-28',
        'receipt_number' => 'KWT-DC-2026-000001',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('28 Aug 2026')
        ->assertSee('KWT-DC-2026-000001')
        ->assertSee('BSI')
        ->assertSee('Rp 100.000')
        ->assertSee('Lunas');
});

it('keeps daycare history strictly separate from student payments', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, ['receipt_number' => 'KWT-DC-SEPARATE']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Sekolah Reguler']);
    Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-NOT-DC',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-28',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-DC-SEPARATE')
        ->assertDontSee('KWT-STUDENT-NOT-DC')
        ->assertDontSee('Siswa Sekolah Reguler');
});

it('renders the student-aligned history column headers', function () {
    daycareEntryPayment(daycareEntryChild('Bilqis Ramadhani', 'Qis'));

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('No.')
        ->assertSee('No. Kwitansi')
        ->assertSee('Tanggal TF')
        ->assertSee('Nama & Kelas')
        ->assertSee('Detail Pembayaran')
        ->assertSee('Total Pembayaran')
        ->assertSee('Diinput Oleh')
        ->assertSee('Aksi');
});

it('shows the Daycare badge beside the receipt number like student badges', function () {
    daycareEntryPayment(daycareEntryChild('Bilqis Ramadhani', 'Qis'));

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('bg-purple-100 text-purple-800">Daycare</span>')
        ->assertSeeHtml('class="inline-flex items-center gap-1.5 whitespace-nowrap"')
        ->assertDontSeeHtml('class="flex flex-wrap items-center gap-1.5"');
});

it('shows the operator who recorded the transaction', function () {
    $user = User::factory()->create(['name' => 'Bendahara Daycare']);
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, ['created_by' => $user->id]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Bendahara Daycare');
});

it('filters the history by receipt number', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, ['receipt_number' => 'KWT-DC-SEARCH-ABC']);
    daycareEntryPayment($child, ['receipt_number' => 'KWT-DC-SEARCH-XYZ']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('historySearch', 'SEARCH-ABC')
        ->assertSee('KWT-DC-SEARCH-ABC')
        ->assertDontSee('KWT-DC-SEARCH-XYZ');
});

it('numbers rows with pagination-aware No. across pages', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    for ($i = 1; $i <= 15; $i++) {
        daycareEntryPayment($child, [
            'bank_id' => $bank->id,
            'created_by' => $user->id,
            'payment_date' => '2026-08-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'receipt_number' => 'KWT-NO-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-NO-15', 'KWT-NO-14', 'KWT-NO-06'])
        ->assertDontSeeHtml('>11</td>');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('gotoPage', 2, 'historyPage')
        ->assertSeeHtml('>11</td>')
        ->assertSeeInOrder(['KWT-NO-05', 'KWT-NO-04', 'KWT-NO-01']);
});

it('exposes exactly the Detail Edit and Delete actions per row', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    $component = Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $payment).'"')
        ->assertSeeHtml('href="'.route('daycare.payment.edit', $payment).'"')
        ->assertSeeHtml('wire:click="confirmDelete('.$payment->id.')"');

    expect(substr_count($component->html(), '>visibility</span>'))->toBe(1)
        ->and(substr_count($component->html(), '>edit</span>'))->toBe(1)
        ->and(substr_count($component->html(), '>delete</span>'))->toBe(1);
});

it('shows an empty state when there are no daycare transactions', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Belum ada transaksi pembayaran.');
});

it('links each transaction to its existing detail page', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $payment).'"');
});

it('links each transaction to its existing edit page', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('href="'.route('daycare.payment.edit', $payment).'"');
});

it('does not expose student payment pages from the history', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertDontSee(route('pembayaran.index'))
        ->assertDontSee(route('pembayaran.create'));
});

it('filters the history by child full name', function () {
    $firstChild = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $secondChild = daycareEntryChild('Muhammad Rafa', 'Rafa');
    $firstPayment = daycareEntryPayment($firstChild, ['receipt_number' => 'KWT-DC-2026-000001']);
    $secondPayment = daycareEntryPayment($secondChild, ['receipt_number' => 'KWT-DC-2026-000002']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('historySearch', 'Bilqis')
        ->assertSee($firstPayment->receipt_number)
        ->assertDontSee($secondPayment->receipt_number);
});

it('filters the history by child nickname', function () {
    $firstChild = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $secondChild = daycareEntryChild('Muhammad Rafa', 'Rafa');
    $firstPayment = daycareEntryPayment($firstChild, ['receipt_number' => 'KWT-DC-2026-000001']);
    $secondPayment = daycareEntryPayment($secondChild, ['receipt_number' => 'KWT-DC-2026-000002']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('historySearch', 'Rafa')
        ->assertSee($secondPayment->receipt_number)
        ->assertDontSee($firstPayment->receipt_number);
});

it('keeps daycare payment editing scoped to its own child', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);
    $other = daycareEntryChild('Muhammad Rafa', 'Rafa');
    $bank = Bank::factory()->create();

    Livewire::test(DaycarePaymentEdit::class, ['payment' => $payment->id])
        ->set('daycare_child_id', $other->id)
        ->set('items', [['description' => 'Daycare Edit', 'amount' => 150000]])
        ->set('bank_id', (string) $bank->id)
        ->set('payment_date', '2026-08-28')
        ->call('save')
        ->assertHasErrors(['payment_id']);

    expect(DaycarePayment::query()->find($payment->id)->total_amount)->toBe('100000.00');
});

it('waits for confirmation before deleting a transaction', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child, ['receipt_number' => 'KWT-DC-2026-DELETE-01']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSee('Hapus transaksi KWT-DC-2026-DELETE-01?')
        ->assertSee('Transaksi akan dihapus permanen');

    expect(DaycarePayment::query()->find($payment->id))->not->toBeNull();
});

it('confirmDelete requires the active history tab', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingPaymentId', null);

    expect(DaycarePayment::query()->find($payment->id))->not->toBeNull();
});

it('ignores confirmDelete for a transaction id that does not exist', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 99999)
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingPaymentId', null);
});

it('closes the delete confirmation modal without deleting', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('cancelDelete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingPaymentId', null)
        ->assertSet('deletingReceiptNumber', '');

    expect(DaycarePayment::query()->find($payment->id))->not->toBeNull();
});

it('deletes the transaction permanently through the existing service', function () {
    Storage::fake('public');

    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $proofPath = UploadedFile::fake()->image('bukti.png')->store('daycare-payment-proofs', 'public');
    $payment = daycareEntryPayment($child, ['proof_path' => $proofPath]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('Transaksi Daycare berhasil dihapus.')
        ->assertDontSee($payment->receipt_number);

    expect(DaycarePayment::query()->find($payment->id))->toBeNull()
        ->and(DaycarePaymentDetail::query()->where('daycare_payment_id', $payment->id)->exists())->toBeFalse();

    Storage::disk('public')->assertMissing($proofPath);
});

it('leaving the history tab cancels the delete confirmation', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('setActiveTab', 'pembayaran')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingPaymentId', null);

    expect(DaycarePayment::query()->find($payment->id))->not->toBeNull();
});

it('delete cancels silently when the transaction no longer exists', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('isDeleteModalOpen', true)
        ->set('deletingPaymentId', 99999)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingPaymentId', null);
});

it('never touches a student Payment when deleting a daycare transaction', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $payment = daycareEntryPayment($child);

    $student = Student::factory()->create();
    $user = User::factory()->create();
    $studentPayment = Payment::create([
        'receipt_number' => 'KWT-2026-999001',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $payment->bank_id,
        'payment_date' => '2026-08-10',
        'total_amount' => 200000,
        'payment_method' => 'manual',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete');

    expect(Payment::query()->find($studentPayment->id))->not->toBeNull()
        ->and(DaycarePayment::query()->find($payment->id))->toBeNull();
});

it('paginates the history across all daycare transactions', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create();
    $receipts = [];

    foreach (range(1, 11) as $i) {
        $receipts[] = daycareEntryPayment($child, [
            'bank_id' => $bank->id,
            'receipt_number' => 'KWT-DC-2026-PG-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'payment_date' => Carbon::parse('2026-08-01')->addDays($i),
        ])->receipt_number;
    }

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee($receipts[10])
        ->assertDontSee($receipts[0])
        ->call('gotoPage', 2, 'historyPage')
        ->assertSee($receipts[0])
        ->assertDontSee($receipts[10]);
});

it('resets to the first page when the history search changes', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create();
    $receipts = [];

    foreach (range(1, 11) as $i) {
        $receipts[] = daycareEntryPayment($child, [
            'bank_id' => $bank->id,
            'receipt_number' => 'KWT-DC-2026-PG-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'payment_date' => Carbon::parse('2026-08-01')->addDays($i),
        ])->receipt_number;
    }

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('gotoPage', 2, 'historyPage')
        ->assertSee($receipts[0])
        ->set('historySearch', 'Qis')
        ->assertSee($receipts[10])
        ->assertDontSee($receipts[0]);
});

it('initializes daycare history filters empty and lists all transactions', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-ALL-NEW',
        'created_at' => '2026-09-07 09:00:00',
        'updated_at' => '2026-09-07 09:00:00',
    ]);
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-ALL-OLD',
        'created_at' => '2026-08-01 09:00:00',
        'updated_at' => '2026-08-01 09:00:00',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSet('bankId', '')
        ->assertSet('status', '')
        ->assertSet('startDate', '')
        ->assertSet('endDate', '')
        ->assertSee('KWT-DC-ALL-NEW')
        ->assertSee('KWT-DC-ALL-OLD');
});

it('daycare bank filter narrows the history before pagination', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bankA = Bank::factory()->create();
    $bankB = Bank::factory()->create();

    foreach (range(1, 11) as $i) {
        daycareEntryPayment($child, [
            'bank_id' => $bankA->id,
            'receipt_number' => 'KWT-DC-BANKA-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }

    daycareEntryPayment($child, ['bank_id' => $bankB->id, 'receipt_number' => 'KWT-DC-BANKB-01']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('bankId', $bankB->id)
        ->assertSee('KWT-DC-BANKB-01')
        ->assertDontSee('KWT-DC-BANKA-01')
        ->assertDontSee('KWT-DC-BANKA-11');
});

it('daycare status active lists all rows and cancelled hides every row', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, ['receipt_number' => 'KWT-DC-STATUS-01']);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'cancelled')
        ->assertDontSee('KWT-DC-STATUS-01')
        ->assertSee('Tidak ada transaksi yang sesuai filter.')
        ->set('status', 'active')
        ->assertSee('KWT-DC-STATUS-01');
});

it('daycare start date only filters created_at from that day forward', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-START-BEFORE',
        'created_at' => '2026-08-01 09:00:00',
        'updated_at' => '2026-08-01 09:00:00',
    ]);
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-START-AFTER',
        'created_at' => '2026-08-20 09:00:00',
        'updated_at' => '2026-08-20 09:00:00',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-15')
        ->assertSet('endDate', '')
        ->assertSee('KWT-DC-START-AFTER')
        ->assertDontSee('KWT-DC-START-BEFORE');
});

it('daycare end date only filters created_at up to that day', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-END-BEFORE',
        'created_at' => '2026-08-01 09:00:00',
        'updated_at' => '2026-08-01 09:00:00',
    ]);
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-END-AFTER',
        'created_at' => '2026-08-20 09:00:00',
        'updated_at' => '2026-08-20 09:00:00',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('endDate', '2026-08-15')
        ->assertSet('startDate', '')
        ->assertSee('KWT-DC-END-BEFORE')
        ->assertDontSee('KWT-DC-END-AFTER');
});

it('daycare date range is inclusive on created_at', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-RANGE-IN',
        'created_at' => '2026-08-15 09:00:00',
        'updated_at' => '2026-08-15 09:00:00',
    ]);
    daycareEntryPayment($child, [
        'receipt_number' => 'KWT-DC-RANGE-OUT',
        'created_at' => now()->toDateString().' 09:00:00',
        'updated_at' => now()->toDateString().' 09:00:00',
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-DC-RANGE-IN')
        ->assertDontSee('KWT-DC-RANGE-OUT');
});

it('changing the daycare bank filter resets to the first page', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');
    $bank = Bank::factory()->create();
    $receipts = [];

    foreach (range(1, 11) as $i) {
        $receipts[] = daycareEntryPayment($child, [
            'bank_id' => $bank->id,
            'receipt_number' => 'KWT-DC-2026-BK-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'payment_date' => Carbon::parse('2026-08-01')->addDays($i),
        ])->receipt_number;
    }

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('gotoPage', 2, 'historyPage')
        ->assertSee($receipts[0])
        ->set('bankId', $bank->id)
        ->assertSee($receipts[10])
        ->assertDontSee($receipts[0]);
});

it('restoring a child from the URL lands on the pembayaran tab with the child selected', function () {
    $child = daycareEntryChild('Bilqis Ramadhani', 'Qis');

    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.payment.entry', ['child' => $child->id]))
        ->assertOk()
        ->assertSee('Bilqis Ramadhani')
        ->assertSee('Input Pembayaran');
});
