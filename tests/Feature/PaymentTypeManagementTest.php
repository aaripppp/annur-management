<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentTypeManagement;
use App\Livewire\StudentManagement;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use App\Models\User;
use Livewire\Livewire;

it('membuat jenis pembayaran baru', function () {
    Livewire::test(PaymentTypeManagement::class)
        ->call('openModal')
        ->set('name', 'Seragam')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $type = PaymentType::where('name', 'Seragam')->first();

    expect($type)->not->toBeNull()
        ->and($type->is_active)->toBeTrue();
});

it('edit memuat jenis pembayaran yang benar berdasarkan ID', function () {
    PaymentType::factory()->create(['name' => 'SPP']);
    $second = PaymentType::factory()->create(['name' => 'Uang Buku']);

    Livewire::test(PaymentTypeManagement::class)
        ->call('edit', $second->id)
        ->assertHasNoErrors()
        ->assertSet('isEditing', true)
        ->assertSet('paymentTypeId', $second->id)
        ->assertSet('name', 'Uang Buku');
});

it('status jenis pembayaran memakai satu radio group dan reset ke default aktif', function () {
    $inactiveType = PaymentType::factory()->create(['is_active' => false]);
    $component = Livewire::test(PaymentTypeManagement::class)
        ->call('edit', $inactiveType->id)
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

it('mengupdate jenis pembayaran dan list refresh tanpa reload', function () {
    $type = PaymentType::factory()->create(['name' => 'OSIS']);

    Livewire::test(PaymentTypeManagement::class)
        ->call('edit', $type->id)
        ->set('name', 'Dana Kegiatan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee('OSIS')
        ->assertSee('Dana Kegiatan');

    expect(PaymentType::find($type->id)->name)->toBe('Dana Kegiatan');
});

it('menghapus jenis pembayaran tanpa referensi berhasil', function () {
    $type = PaymentType::factory()->create(['name' => 'Lain-lain']);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete');

    expect(PaymentType::find($type->id))->toBeNull();
});

it('menghapus jenis pembayaran yang masih punya tarif ditolak', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    PaymentRate::factory()->create(['payment_type_id' => $type->id, 'class_level' => 8]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('1 tarif pembayaran');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus jenis pembayaran yang masih punya tagihan ditolak', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    makeMonthlyBill(makeBillStudent(), $type, 970000);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('1 tagihan siswa');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus jenis pembayaran yang masih punya pengaturan siswa ditolak', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    makeActiveSetting(makeBillStudent(), $type);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('1 pengaturan pembayaran siswa');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus jenis pembayaran yang masih jadi default jenjang ditolak', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    makeLevelDefault($type, SchoolLevel::SMP);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('Konfigurasi jenjang SMP');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus jenis pembayaran yang masih dipakai detail pembayaran ditolak', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    $bill = makeMonthlyBill($student, $type, 970000);

    $payment = Payment::create([
        'receipt_number' => 'REC-'.random_int(1000, 9999),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 970000,
    ]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSee('1 rincian transaksi pembayaran')
        ->assertSee('1 tagihan siswa');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus mapping jenjang nonaktif saat jenis pembayaran dikonfirmasi untuk dihapus', function () {
    $type = PaymentType::factory()->create(['name' => 'Penunjang Belajar']);
    $mapping = PaymentTypeSchoolLevel::factory()->create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::TK,
        'is_active' => false,
    ]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete');

    expect(PaymentType::find($type->id))->toBeNull()
        ->and(PaymentTypeSchoolLevel::find($mapping->id))->toBeNull();
});

it('menolak hapus saat jenis pembayaran menjadi kriteria kelayakan langsung', function () {
    $type = PaymentType::factory()->create(['name' => 'Kriteria Bulanan']);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::TK]);
    StudentExamRequirement::factory()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::TK,
        'payment_type_id' => $type->id,
        'billing_frequency' => BillFrequency::Monthly,
    ]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSee('Kriteria Kelayakan TK');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menolak hapus saat jenis pembayaran menjadi anggota kriteria gabungan', function () {
    $type = PaymentType::factory()->create(['name' => 'Anggota Gabungan']);
    $anchor = PaymentType::factory()->create(['name' => 'Anchor Gabungan']);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $requirement = StudentExamRequirement::factory()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SD,
        'payment_type_id' => $anchor->id,
        'billing_frequency' => BillFrequency::Yearly,
    ]);
    $requirement->pooledPaymentTypes()->attach([$anchor->id, $type->id]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSee('Kriteria Kelayakan SD');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menolak hapus saat jenis pembayaran tersimpan dalam default kriteria kelayakan', function () {
    $type = PaymentType::factory()->create(['name' => 'Default Gabungan']);
    StudentEligibilityConfig::query()
        ->firstOrCreate(['school_level' => SchoolLevel::SMA])
        ->update(['default_pooled_payment_type_ids' => [$type->id]]);

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSee('Kriteria Kelayakan SMA');

    expect(PaymentType::find($type->id))->not->toBeNull();
});

it('menghapus siswa terakhir tidak menghapus konfigurasi jenjang jenis pembayaran', function () {
    $type = PaymentType::factory()->create(['name' => 'Konfigurasi Tanpa Siswa']);
    $mapping = makeLevelDefault($type, SchoolLevel::TK);
    $student = makeBillStudent(-3);
    makeActiveSetting($student, $type);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    Livewire::test(PaymentTypeManagement::class)
        ->call('confirmDelete', $type->id)
        ->call('delete')
        ->assertSee('Konfigurasi jenjang TK');

    expect($student->fresh())->toBeNull()
        ->and($mapping->fresh())->not->toBeNull()
        ->and(PaymentType::find($type->id))->not->toBeNull();
});

it('wire:key pada baris list menggunakan ID jenis pembayaran', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    Livewire::test(PaymentTypeManagement::class)
        ->assertSee('payment-type-'.$type->id, false);
});

it('validasi menolak nama jenis pembayaran kosong', function () {
    Livewire::test(PaymentTypeManagement::class)
        ->call('openModal')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('menyimpan dan menampilkan applicability jenjang tanpa membuat tagihan', function () {
    Livewire::test(PaymentTypeManagement::class)
        ->call('openModal')
        ->set('name', 'Iuran Digital')
        ->set('schoolLevels', ['SMP', 'SMA'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Iuran Digital')
        ->assertSee('SMP')
        ->assertSee('SMA');

    $type = PaymentType::where('name', 'Iuran Digital')->sole();

    expect($type->paymentTypeSchoolLevels()->where('is_active', true)->get()->pluck('school_level')->map->value->sort()->values()->all())
        ->toBe(['SMA', 'SMP'])
        ->and($type->bills()->count())->toBe(0);
});

it('edit applicability menonaktifkan jenjang yang dilepas dan mempertahankan is required', function () {
    $type = PaymentType::factory()->create(['name' => 'Program Sekolah']);
    $requiredMapping = makeLevelDefault($type, SchoolLevel::SMP, required: true);
    makeLevelDefault($type, SchoolLevel::SD, required: false);

    Livewire::test(PaymentTypeManagement::class)
        ->call('edit', $type->id)
        ->assertSet('schoolLevels', ['SD', 'SMP'])
        ->set('schoolLevels', ['SMP', 'SMA'])
        ->call('save')
        ->assertHasNoErrors();

    expect($requiredMapping->fresh()->is_required)->toBeTrue()
        ->and($requiredMapping->fresh()->is_active)->toBeTrue()
        ->and(PaymentTypeSchoolLevel::where('payment_type_id', $type->id)->where('school_level', SchoolLevel::SD)->sole()->is_active)->toBeFalse()
        ->and(PaymentTypeSchoolLevel::where('payment_type_id', $type->id)->where('school_level', SchoolLevel::SMA)->sole()->is_required)->toBeFalse();
});
