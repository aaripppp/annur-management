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
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function validDaycareChildData(array $overrides = []): array
{
    return array_merge([
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Ahmad',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2022-05-10',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Melati No. 10',
        'kelas' => 'C',
        'nama_ayah' => '',
        'no_telp_ayah' => '',
        'nama_ibu' => '',
        'no_telp_ibu' => '',
        'is_active' => true,
    ], $overrides);
}

it('dapat membuat anak Daycare dan kembali ke daftar', function () {
    $component = Livewire::test(DaycareManagement::class)->call('openModal');

    foreach (validDaycareChildData() as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasNoErrors();

    $child = DaycareChild::query()->sole();

    expect($child->nama_lengkap)->toBe('Ahmad Fauzan')
        ->and($child->nama_panggilan)->toBe('Ahmad')
        ->and($child->tanggal_lahir->format('Y-m-d'))->toBe('2022-05-10')
        ->and($child->jenis_kelamin)->toBe('L')
        ->and($child->kelas)->toBe('C')
        ->and($child->is_active)->toBeTrue();

    $component->assertRedirect(route('daycare.index'));
});

it('memvalidasi hanya nama lengkap dan kelas yang wajib', function () {
    Livewire::test(DaycareManagement::class)
        ->call('save')
        ->assertHasErrors([
            'nama_lengkap' => 'required',
            'kelas' => 'required',
        ]);
});

it('menerima kelas Daycare A sampai E', function (string $kelas) {
    $component = Livewire::test(DaycareManagement::class);

    foreach (validDaycareChildData(['nama_lengkap' => 'Anak '.$kelas, 'kelas' => $kelas]) as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasNoErrors();

    expect(DaycareChild::query()->where('kelas', $kelas)->exists())->toBeTrue();
})->with(['A', 'B', 'C', 'D', 'E']);

it('menolak kelas di luar A sampai E', function () {
    $component = Livewire::test(DaycareManagement::class);

    foreach (validDaycareChildData(['kelas' => 'F']) as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasErrors(['kelas' => 'in']);
});

it('menyimpan nama dan telepon orang tua sebagai nullable', function () {
    $component = Livewire::test(DaycareManagement::class);

    foreach (validDaycareChildData() as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasNoErrors();

    $child = DaycareChild::query()->sole();

    expect($child->nama_ayah)->toBeNull()
        ->and($child->no_telp_ayah)->toBeNull()
        ->and($child->nama_ibu)->toBeNull()
        ->and($child->no_telp_ibu)->toBeNull();
});

it('dapat mengedit biodata tanpa mengubah riwayat pembayaran', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Nama Lama', 'kelas' => 'A']);
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'total_amount' => 750000,
    ]);
    DaycarePaymentDetail::factory()->create(['daycare_payment_id' => $payment->id, 'description' => 'Penitipan Agustus', 'amount' => 750000]);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->set('nama_lengkap', 'Nama Baru')
        ->set('kelas', 'E')
        ->call('updateBiodata')
        ->assertHasNoErrors()
        ->assertSee('Nama Baru');

    expect($child->refresh()->nama_lengkap)->toBe('Nama Baru')
        ->and($child->kelas)->toBe('E')
        ->and(DaycarePayment::query()->find($payment->id)?->total_amount)->toBe('750000.00')
        ->and($payment->details()->sole()->description)->toBe('Penitipan Agustus');
});

it('mencari berdasarkan nama lengkap nama panggilan dan telepon orang tua', function (string $search) {
    DaycareChild::factory()->create([
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Ujang',
        'no_telp_ibu' => '081234567890',
    ]);
    DaycareChild::factory()->create(['nama_lengkap' => 'Siti Aminah', 'nama_panggilan' => 'Siti']);

    Livewire::test(DaycareManagement::class)
        ->set('search', $search)
        ->assertSee('Ahmad Fauzan')
        ->assertDontSee('Siti Aminah');
})->with(['Ahmad', 'Ujang', '081234567890']);

it('memfilter berdasarkan kelas', function () {
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas A', 'kelas' => 'A']);
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas B', 'kelas' => 'B']);

    Livewire::test(DaycareManagement::class)
        ->set('filterClass', 'A')
        ->assertSee('Anak Kelas A')
        ->assertDontSee('Anak Kelas B');
});

it('memfilter anak aktif dan nonaktif', function (string $status, string $visible, string $hidden) {
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Aktif', 'is_active' => true]);
    DaycareChild::factory()->inactive()->create(['nama_lengkap' => 'Anak Nonaktif']);

    Livewire::test(DaycareManagement::class)
        ->set('filterStatus', $status)
        ->assertSee($visible)
        ->assertDontSee($hidden);
})->with([
    ['aktif', 'Anak Aktif', 'Anak Nonaktif'],
    ['nonaktif', 'Anak Nonaktif', 'Anak Aktif'],
]);

it('menampilkan aksi Hapus tepat setelah Edit', function () {
    $child = DaycareChild::factory()->create();
    $html = Livewire::test(DaycareManagement::class)->html();
    $editPosition = strpos($html, 'title="Edit Biodata"');
    $deletePosition = strpos($html, 'wire:click="confirmChildDelete('.$child->id.')"');

    expect($editPosition)->not->toBeFalse()
        ->and($deletePosition)->not->toBeFalse()
        ->and($deletePosition)->toBeGreaterThan($editPosition);
});

it('memerlukan konfirmasi sebelum menghapus anak', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Konfirmasi']);

    Livewire::test(DaycareManagement::class)
        ->call('deleteChild')
        ->assertSet('isChildDeleteModalOpen', false);

    expect(DaycareChild::query()->find($child->id))->not->toBeNull();

    Livewire::test(DaycareManagement::class)
        ->call('confirmChildDelete', $child->id)
        ->assertSet('isChildDeleteModalOpen', true)
        ->assertSet('deletingChildId', $child->id)
        ->assertSee('Hapus Data Anak?')
        ->assertSee('akan dihapus secara permanen');
});

it('menghapus anak yang tidak memiliki transaksi tanpa memengaruhi data lain', function () {
    $target = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Dihapus']);
    $other = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Dipertahankan']);
    $student = Student::factory()->create();

    Livewire::test(DaycareManagement::class)
        ->call('confirmChildDelete', $target->id)
        ->call('deleteChild')
        ->assertSet('isChildDeleteModalOpen', false)
        ->assertDontSeeHtml('wire:key="daycare-child-'.$target->id.'"')
        ->assertSee('Anak Dipertahankan');

    expect(DaycareChild::query()->find($target->id))->toBeNull()
        ->and(DaycareChild::query()->find($other->id))->not->toBeNull()
        ->and(Student::query()->find($student->id))->not->toBeNull();
});

it('menghitung dampak transaksi sebelum menampilkan peringatan kuat', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad']);
    DaycarePayment::factory()->create(['daycare_child_id' => $child->id, 'total_amount' => 500000]);
    DaycarePayment::factory()->create(['daycare_child_id' => $child->id, 'total_amount' => 750000]);

    Livewire::test(DaycareManagement::class)
        ->call('confirmChildDelete', $child->id)
        ->assertSet('deletingChildPaymentCount', 2)
        ->assertSet('deletingChildPaymentTotal', 1250000.0)
        ->assertSee('Ahmad memiliki 2 riwayat transaksi dengan total Rp 1.250.000')
        ->assertSee('seluruh riwayat transaksi, rincian pembayaran, bukti transfer')
        ->assertSee('Data yang sudah dihapus tidak dapat dikembalikan.')
        ->assertSee('Hapus Semua Data');

    expect(DaycareChild::query()->find($child->id))->not->toBeNull()
        ->and($child->payments()->count())->toBe(2);
});

it('membatalkan penghapusan tanpa mengubah child transaksi detail atau bukti', function () {
    Storage::fake('public');
    Storage::disk('public')->put('daycare-payment-proofs/keep.pdf', 'proof');
    $child = DaycareChild::factory()->create();
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'proof_path' => 'daycare-payment-proofs/keep.pdf',
    ]);
    $detail = DaycarePaymentDetail::factory()->create(['daycare_payment_id' => $payment->id]);

    Livewire::test(DaycareManagement::class)
        ->call('confirmChildDelete', $child->id)
        ->call('cancelChildDelete')
        ->assertSet('isChildDeleteModalOpen', false)
        ->assertSet('deletingChildId', null);

    expect(DaycareChild::query()->find($child->id))->not->toBeNull()
        ->and(DaycarePayment::query()->find($payment->id))->not->toBeNull()
        ->and(DaycarePaymentDetail::query()->find($detail->id))->not->toBeNull();
    Storage::disk('public')->assertExists('daycare-payment-proofs/keep.pdf');
});

it('menghapus seluruh data finansial child dan memperbarui laporan tanpa menyentuh domain lain', function () {
    Storage::fake('public');
    Storage::disk('public')->put('daycare-payment-proofs/ahmad-1.pdf', 'proof-1');
    Storage::disk('public')->put('daycare-payment-proofs/ahmad-2.pdf', 'proof-2');
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad Cascade']);
    $otherChild = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Lain']);
    $firstPayment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-CASCADE-001',
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 500000,
        'proof_path' => 'daycare-payment-proofs/ahmad-1.pdf',
        'created_by' => $user->id,
    ]);
    $secondPayment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-CASCADE-002',
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 750000,
        'proof_path' => 'daycare-payment-proofs/ahmad-2.pdf',
        'created_by' => $user->id,
    ]);
    $otherPayment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-OTHER-KEEP',
        'daycare_child_id' => $otherChild->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 1000000,
        'created_by' => $user->id,
    ]);
    $firstDetail = DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $firstPayment->id,
        'description' => 'Penitipan',
        'amount' => 500000,
    ]);
    $secondDetail = DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $secondPayment->id,
        'description' => 'Kegiatan',
        'amount' => 750000,
    ]);
    DaycarePaymentDetail::factory()->create(['daycare_payment_id' => $otherPayment->id, 'amount' => 1000000]);
    $student = Student::factory()->create();
    $studentBill = StudentBill::factory()->create(['student_id' => $student->id]);
    $studentPayment = Payment::query()->create([
        'receipt_number' => 'KWT-STUDENT-KEEP-CASCADE',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $dashboardBefore = Livewire::withQueryParams([
        'periode' => Dashboard::PERIOD_CUSTOM,
        'dari' => '2000-01-01',
        'sampai' => '2100-12-31',
    ])->test(Dashboard::class);
    $totalsBefore = $dashboardBefore->viewData('bankTotals');

    $component = Livewire::test(DaycareManagement::class)
        ->call('confirmChildDelete', $child->id)
        ->call('deleteChild')
        ->assertSet('isChildDeleteModalOpen', false)
        ->assertSee('Data Ahmad Cascade beserta 2 riwayat transaksinya berhasil dihapus.');

    expect(DaycareChild::query()->find($child->id))->toBeNull()
        ->and(DaycarePayment::query()->find($firstPayment->id))->toBeNull()
        ->and(DaycarePayment::query()->find($secondPayment->id))->toBeNull()
        ->and(DaycarePaymentDetail::query()->find($firstDetail->id))->toBeNull()
        ->and(DaycarePaymentDetail::query()->find($secondDetail->id))->toBeNull()
        ->and(DaycareChild::query()->find($otherChild->id))->not->toBeNull()
        ->and(DaycarePayment::query()->find($otherPayment->id))->not->toBeNull()
        ->and(Payment::query()->find($studentPayment->id))->not->toBeNull()
        ->and(StudentBill::query()->find($studentBill->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('daycare-payment-proofs/ahmad-1.pdf');
    Storage::disk('public')->assertMissing('daycare-payment-proofs/ahmad-2.pdf');

    $dashboardAfter = Livewire::withQueryParams([
        'periode' => Dashboard::PERIOD_CUSTOM,
        'dari' => '2000-01-01',
        'sampai' => '2100-12-31',
    ])->test(Dashboard::class);
    $totalsAfter = $dashboardAfter->viewData('bankTotals');

    expect($dashboardAfter->viewData('totalPemasukan'))->toBe($dashboardBefore->viewData('totalPemasukan') - 1250000.0)
        ->and($dashboardAfter->viewData('totalTransaksi'))->toBe($dashboardBefore->viewData('totalTransaksi') - 2)
        ->and($totalsAfter->get($bank->id)['daycare_total'])->toBe($totalsBefore->get($bank->id)['daycare_total'] - 1250000.0);

    Livewire::test(DaycarePaymentEntry::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-DC-OTHER-KEEP')
        ->assertDontSee('KWT-DC-CASCADE-001')
        ->assertDontSee('KWT-DC-CASCADE-002');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertDontSee('KWT-DC-CASCADE-001')
        ->assertDontSee('KWT-DC-CASCADE-002')
        ->assertDontSee('KWT-DC-OTHER-KEEP')
        ->assertSee('KWT-STUDENT-KEEP-CASCADE');

    $this->actingAs($user)
        ->get(route('daycare.payment.show', ['payment' => $firstPayment->id]))
        ->assertNotFound();
    $this->get(route('daycare.payment.print', ['payment' => $firstPayment->id]))->assertNotFound();
    $this->get(route('daycare.payment.pdf', ['payment' => $secondPayment->id]))->assertNotFound();
});

it('melindungi seluruh route Daycare dengan autentikasi', function (string $routeName, array $parameters = []) {
    $this->get(route($routeName, $parameters))->assertRedirect(route('login'));
})->with([
    ['daycare.index', []],
    ['daycare.show', fn () => ['child' => DaycareChild::factory()->create()]],
    ['daycare.payment.create', fn () => ['child' => DaycareChild::factory()->create()]],
]);
