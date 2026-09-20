<?php

use App\Livewire\Dashboard;
use App\Livewire\DaycareDetail;
use App\Livewire\DaycareManagement;
use App\Livewire\DaycarePaymentEntry;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeWorkspaceDaycarePayment(
    DaycareChild $child,
    Bank $bank,
    string $receipt,
    string $date,
    array $details = [['description' => 'Penitipan Agustus', 'amount' => 750000]],
    array $attributes = [],
): DaycarePayment {
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'receipt_number' => $receipt,
        'payment_date' => $date,
        'total_amount' => array_sum(array_column($details, 'amount')),
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

it('Data Daycare hanya menampilkan pengelolaan data anak tanpa tab Riwayat Transaksi', function () {
    Livewire::test(DaycareManagement::class)
        ->assertSee('Data Anak Daycare')
        ->assertSee('Cari Anak')
        ->assertSee('Tambah Anak')
        ->assertDontSee('Riwayat Transaksi')
        ->assertDontSee('Cari Transaksi')
        ->assertDontSeeHtml('setActiveTab(\'history\')');
});

it('mengabaikan query string tab riwayat yang tidak dikenal pada Data Daycare', function () {
    Livewire::withQueryParams(['tab' => 'history'])
        ->test(DaycareManagement::class)
        ->assertSee('Cari Anak')
        ->assertDontSee('Cari Transaksi')
        ->assertDontSee('Riwayat Transaksi');
});

it('mencari anak tanpa selection atau aksi pembayaran pada halaman utama', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Mamad',
        'kelas' => 'B',
        'is_active' => true,
    ]);
    DaycareChild::factory()->create(['nama_lengkap' => 'Siti Aisyah']);

    Livewire::test(DaycareManagement::class)
        ->set('search', 'Mamad')
        ->assertSee('Ahmad Fauzan')
        ->assertDontSee('Siti Aisyah')
        ->assertDontSee('Input Pembayaran')
        ->assertDontSee('Anak Dipilih')
        ->assertDontSeeHtml('wire:click="selectChild('.$child->id.')"')
        ->assertDontSeeHtml('>payments</span>')
        ->assertSeeHtml('href="'.route('daycare.show', $child).'"')
        ->assertSeeHtml('href="'.route('daycare.show', ['child' => $child, 'edit' => 1]).'"');
});

it('memulai pembayaran hanya dari detail anak menggunakan flow create yang ada', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Fauzan']);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->assertSee('Riwayat Transaksi Biaya')
        ->assertSee('Input Pembayaran')
        ->assertSeeHtml('href="'.route('daycare.payment.create', $child).'"');

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.payment.create', $child))
        ->assertOk()
        ->assertSee('Input Pembayaran Daycare');
});

it('menggunakan layout filter responsif tanpa overlap', function () {
    $component = Livewire::test(DaycareManagement::class)
        ->assertSee('Cari nama lengkap atau nama panggilan...')
        ->assertSee('Semua Kelas')
        ->assertSee('Semua Status');

    expect($component->html())
        ->toContain('lg:grid-cols-[minmax(20rem,1fr)_10rem_10rem]')
        ->toContain('sm:col-span-2 lg:col-span-1')
        ->and(substr_count($component->html(), 'h-11'))->toBeGreaterThanOrEqual(3);
});

it('Daycare > Pembayaran menyajikan dua tab dengan default Pembayaran Daycare', function () {
    Livewire::test(DaycarePaymentEntry::class)
        ->assertSet('activeTab', 'pembayaran')
        ->assertSee('Pembayaran Daycare')
        ->assertSee('Riwayat Transaksi')
        ->assertSee('Cari Anak');
});

it('Riwayat Transaksi Daycare menampilkan seluruh transaksi semua anak terbaru dahulu', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Daycare', 'kelas' => 'A']);
    $otherChild = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Lain']);
    $bank = Bank::factory()->create(['name' => 'BCA']);
    $payment = makeWorkspaceDaycarePayment($child, $bank, 'KWT-DC-2026-000001', '2026-08-25', [
        ['description' => 'Penitipan Agustus', 'amount' => 1000000],
        ['description' => 'Kegiatan', 'amount' => 500000],
    ]);
    makeWorkspaceDaycarePayment($otherChild, $bank, 'KWT-DC-OTHER-001', '2026-08-25');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-DC-2026-000001')
        ->assertSee('25 Aug 2026')
        ->assertSee('Penitipan Agustus + Kegiatan')
        ->assertSee('BCA')
        ->assertSee('Rp 1.500.000')
        ->assertSee('Ahmad Daycare')
        ->assertSee('Anak Lain')
        ->assertSee('KWT-DC-OTHER-001')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $payment).'"')
        ->assertSeeHtml('href="'.route('daycare.payment.edit', $payment).'"')
        ->assertSeeHtml('wire:click="confirmDelete('.$payment->id.')"');
});

it('menghapus transaksi dari Daycare > Pembayaran tanpa menyentuh transaksi lain atau Student', function () {
    Storage::fake('public');
    Storage::disk('public')->put('daycare-payment-proofs/delete-me.pdf', 'proof');
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();
    $target = makeWorkspaceDaycarePayment($child, $bank, 'KWT-DELETE', '2026-08-25', attributes: ['proof_path' => 'daycare-payment-proofs/delete-me.pdf']);
    $other = makeWorkspaceDaycarePayment($child, $bank, 'KWT-KEEP', '2026-08-24');
    $student = Student::factory()->create();
    $user = User::factory()->create();
    $studentPayment = Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-KEEP',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $target->id)
        ->assertSee('Hapus transaksi KWT-DELETE?')
        ->assertSee('Dashboard maupun rekap Bank')
        ->call('delete')
        ->assertDontSee('KWT-DELETE')
        ->assertSee('KWT-KEEP');

    expect(DaycarePayment::query()->find($target->id))->toBeNull()
        ->and(DaycarePaymentDetail::query()->where('daycare_payment_id', $target->id)->exists())->toBeFalse()
        ->and(DaycarePayment::query()->find($other->id))->not->toBeNull()
        ->and(Payment::query()->find($studentPayment->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('daycare-payment-proofs/delete-me.pdf');
});

it('delete memperbarui Dashboard dan menegaskan riwayat Daycare tidak pernah masuk riwayat Student', function () {
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $payment = makeWorkspaceDaycarePayment($child, $bank, 'KWT-FINANCIAL-DELETE', '2026-08-25');

    Livewire::withQueryParams([
        'periode' => Dashboard::PERIOD_CUSTOM,
        'dari' => '2026-08-25',
        'sampai' => '2026-08-25',
    ])->test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 750000.0)
        ->assertViewHas('totalTransaksi', 1);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertDontSee('KWT-FINANCIAL-DELETE');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete');

    Livewire::withQueryParams([
        'periode' => Dashboard::PERIOD_CUSTOM,
        'dari' => '2026-08-25',
        'sampai' => '2026-08-25',
    ])->test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 0.0)
        ->assertViewHas('totalTransaksi', 0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertDontSee('KWT-FINANCIAL-DELETE');

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertDontSee('KWT-FINANCIAL-DELETE');
});
