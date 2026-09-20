<?php

use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('adds nullable biodata columns and casts birth date', function () {
    expect(Schema::hasColumns('students', [
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
        'tempat_lahir',
        'tanggal_lahir',
        'foto',
    ]))->toBeTrue();

    $student = Student::factory()->create([
        'tanggal_lahir' => '2014-05-02',
        'no_telp_ayah' => '081234567890',
        'no_telp_ibu' => '+6281234567890',
    ]);

    expect($student->tanggal_lahir->toDateString())->toBe('2014-05-02')
        ->and($student->no_telp_ayah)->toBe('081234567890')
        ->and($student->no_telp_ibu)->toBe('+6281234567890')
        ->and(Student::factory()->create()->nama_ayah)->toBeNull();
});

it('creates a student with optional biodata and a public photo', function () {
    Storage::fake('public');
    $class = SchoolClass::factory()->create();

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'BIODATA-001')
        ->set('nama_lengkap', 'Siswa Biodata')
        ->set('nama_panggilan', 'Biodata')
        ->set('class_id', $class->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Biodata')
        ->set('tempat_lahir', 'Bandung')
        ->set('tanggal_lahir', '2014-05-02')
        ->set('nama_ayah', 'Ayah Siswa')
        ->set('no_telp_ayah', '081234567890')
        ->set('nama_ibu', 'Ibu Siswa')
        ->set('no_telp_ibu', '+6281234567890')
        ->set('foto_upload', UploadedFile::fake()->image('siswa.jpg', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'BIODATA-001')->firstOrFail();

    expect($student->tempat_lahir)->toBe('Bandung')
        ->and($student->tanggal_lahir->toDateString())->toBe('2014-05-02')
        ->and($student->no_telp_ayah)->toBe('081234567890')
        ->and($student->foto)->toStartWith('student-photos/');
    Storage::disk('public')->assertExists($student->foto);
});

it('keeps an existing photo without a new upload and safely replaces it', function () {
    Storage::fake('public');
    Storage::disk('public')->put('student-photos/old.jpg', 'old-photo');
    $student = Student::factory()->create(['foto' => 'student-photos/old.jpg']);

    $component = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_ayah', 'Nama Ayah')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->foto)->toBe('student-photos/old.jpg')
        ->and($student->fresh()->nama_ayah)->toBe('Nama Ayah');
    Storage::disk('public')->assertExists('student-photos/old.jpg');

    $component
        ->call('openEditProfile')
        ->set('foto_upload', UploadedFile::fake()->image('replacement.png', 200, 200))
        ->call('saveProfile')
        ->assertHasNoErrors();

    $newPath = $student->fresh()->foto;
    expect($newPath)->not->toBe('student-photos/old.jpg')->toStartWith('student-photos/');
    Storage::disk('public')->assertMissing('student-photos/old.jpg');
    Storage::disk('public')->assertExists($newPath);

    $component
        ->call('openEditProfile')
        ->set('remove_foto', true)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->foto)->toBeNull();
    Storage::disk('public')->assertMissing($newPath);
});

it('rejects non-image photo uploads and never deletes an unmanaged path', function () {
    Storage::fake('public');
    Storage::disk('public')->put('documents/keep.txt', 'keep');
    $student = Student::factory()->create(['foto' => 'documents/keep.txt']);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('foto_upload', UploadedFile::fake()->create('student.pdf', 100, 'application/pdf'))
        ->call('saveProfile')
        ->assertHasErrors(['foto_upload']);

    app(StudentPhotoService::class)->delete($student->foto);

    Storage::disk('public')->assertExists('documents/keep.txt');
});

it('does not render a standalone biodata card on student detail', function () {
    $student = Student::factory()->create([
        'tempat_lahir' => 'Bekasi',
        'tanggal_lahir' => '2004-03-15',
        'nama_ayah' => 'Ahmad Fauzan',
        'nama_ibu' => 'Siti Aminah',
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSee('Biodata Tambahan');
});
