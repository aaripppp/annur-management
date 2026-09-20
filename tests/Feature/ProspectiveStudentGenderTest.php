<?php

use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use Livewire\Livewire;

function makeProspGenderStudent(string $gender = 'L'): ProspectiveStudent
{
    $class = SchoolClass::factory()->create(['level' => 7]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    return ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Calon Gender',
        'jenis_kelamin' => $gender,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
    ]);
}

it('form calon siswa memakai radio Laki-laki (L) dan Perempuan (P) seperti form siswa', function () {
    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->assertSee('Jenis Kelamin')
        ->assertSee('Laki-laki (L)')
        ->assertSee('Perempuan (P)')
        ->assertSeeHtml('type="radio"')
        ->assertSeeHtml('value="L"')
        ->assertSeeHtml('value="P"')
        ->assertDontSee('-- Pilih Jenis Kelamin --');
});

it('membuat calon siswa dengan jenis kelamin Laki-laki (L)', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Calon Laki-laki')
        ->set('jenis_kelamin', 'L')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->sole()->jenis_kelamin)->toBe('L');
});

it('membuat calon siswa dengan jenis kelamin Perempuan (P)', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Calon Perempuan')
        ->set('jenis_kelamin', 'P')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->sole()->jenis_kelamin)->toBe('P');
});

it('jenis kelamin boleh dikosongkan saat membuat calon siswa', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Tanpa Gender')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->sole()->jenis_kelamin)->toBeNull();
});

it('mengedit jenis kelamin dari Laki-laki menjadi Perempuan', function () {
    $prospectiveStudent = makeProspGenderStudent('L');

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('jenis_kelamin', 'L')
        ->set('jenis_kelamin', 'P')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->jenis_kelamin)->toBe('P');
});

it('mengedit jenis kelamin dari Perempuan menjadi Laki-laki', function () {
    $prospectiveStudent = makeProspGenderStudent('P');

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('jenis_kelamin', 'P')
        ->set('jenis_kelamin', 'L')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->jenis_kelamin)->toBe('L');
});

it('menghapus nilai jenis kelamin saat edit kembali menjadi kosong', function () {
    $prospectiveStudent = makeProspGenderStudent('L');

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('jenis_kelamin', 'L')
        ->set('jenis_kelamin', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->jenis_kelamin)->toBeNull();
});

it('nilai jenis kelamin yang tidak sah ditolak', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Gender Salah')
        ->set('jenis_kelamin', 'X')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save')
        ->assertHasErrors(['jenis_kelamin']);

    expect(ProspectiveStudent::query()->count())->toBe(0);
});
