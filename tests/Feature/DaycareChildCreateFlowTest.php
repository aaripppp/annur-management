<?php

use App\Livewire\DaycareDetail;
use App\Livewire\DaycareManagement;
use App\Models\DaycareChild;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

it('membuat anak Daycare hanya dengan nama lengkap dan kelas', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors();

    expect(DaycareChild::query()->count())->toBe(1);
});

it('menyimpan biodata opsional sebagai null ketika tidak diisi', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors();

    $child = DaycareChild::query()->sole();

    expect($child->nama_panggilan)->toBeNull()
        ->and($child->tempat_lahir)->toBeNull()
        ->and($child->tanggal_lahir)->toBeNull()
        ->and($child->jenis_kelamin)->toBeNull()
        ->and($child->alamat)->toBeNull();
});

it('membuat anak Daycare tanpa nama lengkap gagal', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasErrors(['nama_lengkap' => 'required']);

    expect(DaycareChild::query()->count())->toBe(0);
});

it('membuat anak Daycare tanpa kelas gagal', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->call('save')
        ->assertHasErrors(['kelas' => 'required']);

    expect(DaycareChild::query()->count())->toBe(0);
});

it('mengarahkan ke indeks data Daycare setelah berhasil membuat anak', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('daycare.index'));
});

it('tidak mengarahkan ke profil detail anak setelah berhasil membuat', function () {
    $component = Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors();

    $child = DaycareChild::query()->sole();

    expect($component->effects['redirect'])->not->toBe(route('daycare.show', $child))
        ->and($component->effects['redirect'])->toBe(route('daycare.index'));
});

it('menyimpan anak Daycare yang dibuat secara benar', function () {
    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors();

    $child = DaycareChild::query()->sole();

    expect($child->nama_lengkap)->toBe('Ahmad Fauzan')
        ->and($child->kelas)->toBe('C')
        ->and($child->is_active)->toBeTrue();
});

it('tidak menyentuh domain sekolah ketika membuat anak minimal', function () {
    $before = [
        'students' => Student::query()->count(),
        'enrollments' => StudentAcademicEnrollment::query()->count(),
        'payments' => Payment::query()->count(),
    ];

    Livewire::test(DaycareManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Ahmad Fauzan')
        ->set('kelas', 'C')
        ->call('save')
        ->assertHasNoErrors();

    expect(DaycareChild::query()->count())->toBe(1)
        ->and(Student::query()->count())->toBe($before['students'])
        ->and(StudentAcademicEnrollment::query()->count())->toBe($before['enrollments'])
        ->and(Payment::query()->count())->toBe($before['payments']);
});

it('memuat nilai optional pada modal edit dari anak yang datanya opsional kosong', function () {
    $child = DaycareChild::factory()->create([
        'nama_panggilan' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'jenis_kelamin' => null,
        'alamat' => null,
    ]);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->assertSet('nama_panggilan', '')
        ->assertSet('tempat_lahir', '')
        ->assertSet('tanggal_lahir', '')
        ->assertSet('jenis_kelamin', '')
        ->assertSet('alamat', '');
});

it('menyimpan edit biodata hanya dengan nama lengkap dan kelas saat semua field optional kosong', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Anak Sebelum Edit',
        'nama_panggilan' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'jenis_kelamin' => null,
        'alamat' => null,
        'nama_ayah' => null,
        'no_telp_ayah' => null,
        'nama_ibu' => null,
        'no_telp_ibu' => null,
        'kelas' => 'A',
    ]);

    $child->refresh();

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->set('nama_lengkap', 'Daycare A')
        ->set('kelas', 'C')
        ->call('updateBiodata')
        ->assertHasNoErrors();

    expect($child->refresh())
        ->nama_lengkap->toBe('Daycare A')
        ->kelas->toBe('C')
        ->and($child->nama_panggilan)->toBeNull()
        ->and($child->tempat_lahir)->toBeNull()
        ->and($child->tanggal_lahir)->toBeNull()
        ->and($child->jenis_kelamin)->toBeNull()
        ->and($child->alamat)->toBeNull();
});

it('gagal menyimpan edit biodata tanpa nama lengkap', function () {
    $child = DaycareChild::factory()->create(['kelas' => 'A']);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->set('nama_lengkap', '')
        ->call('updateBiodata')
        ->assertHasErrors(['nama_lengkap' => 'required']);
});

it('gagal menyimpan edit biodata tanpa kelas', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Uji']);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->set('kelas', '')
        ->call('updateBiodata')
        ->assertHasErrors(['kelas' => 'required']);
});

it('menjaga nilai is_active dan data parent yang sudah terisi saat edit', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Anak Uji',
        'is_active' => false,
        'nama_ayah' => 'Ayah Uji',
        'no_telp_ayah' => '081234567890',
    ]);

    Livewire::test(DaycareDetail::class, ['child' => $child])
        ->call('openEditModal')
        ->set('nama_lengkap', 'Anak Uji Baru')
        ->call('updateBiodata')
        ->assertHasNoErrors();

    expect($child->refresh())
        ->is_active->toBeFalse()
        ->nama_ayah->toBe('Ayah Uji')
        ->no_telp_ayah->toBe('081234567890');
});

it('hanya Nama Lengkap dan Kelas yang ditandai wajib pada form edit daycare', function () {
    $html = view('livewire.daycare-child-fields', ['mode' => 'edit', 'errors' => new ViewErrorBag])->render();

    expect(str_contains($html, 'Nama Lengkap <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, 'Kelas <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, '>Nama Panggilan</label>'))->toBeTrue()
        ->and(str_contains($html, '>Tempat Lahir</label>'))->toBeTrue()
        ->and(str_contains($html, '>Tanggal Lahir</label>'))->toBeTrue()
        ->and(str_contains($html, '>Jenis Kelamin</span>'))->toBeTrue()
        ->and(str_contains($html, '>Alamat</label>'))->toBeTrue()
        ->and(str_contains($html, 'id="daycare-nama-panggilan" type="text" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="daycare-tempat-lahir" type="text" wire:model="tempat_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="daycare-tanggal-lahir" type="date" wire:model="tanggal_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="daycare-alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue();
});
