<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentManagement;
use App\Models\SchoolClass;
use App\Models\Student;
use Livewire\Livewire;

function makeDefaultListClasses(): void
{
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    SchoolClass::factory()->create(['id' => 2, 'name' => 'VIII B', 'level' => 8]);
    SchoolClass::factory()->create(['id' => 3, 'name' => 'I A', 'level' => 1]);
    SchoolClass::factory()->create(['id' => 4, 'name' => 'II B', 'level' => 2]);
}

function makeDefaultListStudents(): void
{
    makeDefaultListClasses();

    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Siswa SMP Tujuh', 'nis' => 'DEFAULT-001']);
    Student::factory()->create(['class_id' => 2, 'nama_lengkap' => 'Siswa SMP Delapan', 'nis' => 'DEFAULT-002']);
    Student::factory()->create(['class_id' => 3, 'nama_lengkap' => 'Siswa SD Satu', 'nis' => 'DEFAULT-003']);
    Student::factory()->create(['class_id' => 4, 'nama_lengkap' => 'Siswa SD Dua', 'nis' => 'DEFAULT-004']);
}

it('shows the 10 most recently added students on first load, newest first', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);

    $ids = [];

    for ($i = 1; $i <= 11; $i++) {
        $student = Student::factory()->create([
            'class_id' => $class->id,
            'nama_lengkap' => 'Siswa SMP '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'nis' => sprintf('DEFAULT-%02d', $i),
        ]);
        $student->forceFill(['created_at' => now()->addSeconds($i)])->save();
        $ids[$i] = $student->id;
    }

    $component = Livewire::test(StudentManagement::class)
        ->assertSee('Siswa SMP 11')
        ->assertSee('Siswa SMP 02')
        ->assertDontSee('Siswa SMP 01')
        ->assertDontSee('Gunakan pencarian atau filter untuk menampilkan data siswa.');

    $pageIds = collect($component->viewData('students')->items())->pluck('id')->all();

    expect($pageIds)->toBe(array_slice(array_values(array_reverse($ids)), 0, 10))->toHaveCount(10);
    expect($component->viewData('students')->total())->toBe(11);
});

it('shows the no-results message only when no students exist at all', function () {
    makeDefaultListClasses();

    Livewire::test(StudentManagement::class)
        ->assertSee('Tidak ada siswa yang ditemukan.')
        ->assertDontSee('Gunakan pencarian atau filter untuk menampilkan data siswa.');
});

it('shows only matching students after a search', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('search', 'SMP Tujuh')
        ->assertSee('Siswa SMP Tujuh')
        ->assertDontSee('Siswa SMP Delapan')
        ->assertDontSee('Siswa SD Satu');
});

it('shows only matching students after searching by NIS', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('search', 'DEFAULT-004')
        ->assertSee('Siswa SD Dua')
        ->assertDontSee('Siswa SMP Tujuh');
});

it('filters by jenjang SMP', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->assertSee('Siswa SMP Tujuh')
        ->assertSee('Siswa SMP Delapan')
        ->assertDontSee('Siswa SD Satu')
        ->assertDontSee('Siswa SD Dua');
});

it('filters by jenjang SD', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'SD')
        ->assertSee('Siswa SD Satu')
        ->assertSee('Siswa SD Dua')
        ->assertDontSee('Siswa SMP Tujuh')
        ->assertDontSee('Siswa SMP Delapan');
});

it('invalid jenjang value yields empty results with no-results message', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'XYZ')
        ->assertDontSee('Siswa SMP Tujuh')
        ->assertDontSee('Siswa SD Satu')
        ->assertSee('Tidak ada siswa yang ditemukan.');
});

it('combines jenjang and kelas filters', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->set('filterClassId', 1)
        ->assertSee('Siswa SMP Tujuh')
        ->assertDontSee('Siswa SMP Delapan')
        ->assertDontSee('Siswa SD Satu');
});

it('shows no-results message when an active filter finds nothing', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('search', 'nama yang tidak ada')
        ->assertSee('Tidak ada siswa yang ditemukan.')
        ->assertDontSee('Gunakan pencarian atau filter untuk menampilkan data siswa.');
});

it('jenjang filter persists via URL query param on first load', function () {
    makeDefaultListStudents();

    Livewire::withQueryParams(['jenjang' => 'SMP'])
        ->test(StudentManagement::class)
        ->assertSet('filterLevel', 'SMP')
        ->assertSee('Siswa SMP Tujuh')
        ->assertSee('Siswa SMP Delapan')
        ->assertDontSee('Siswa SD Satu');
});

it('clearing all filters returns to the newest students', function () {
    makeDefaultListStudents();

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->set('search', 'SMP Tujuh')
        ->assertSee('Siswa SMP Tujuh')
        ->assertDontSee('Siswa SMP Delapan')
        ->set('filterLevel', '')
        ->set('search', '')
        ->assertSee('Siswa SMP Tujuh')
        ->assertSee('Siswa SMP Delapan')
        ->assertSee('Siswa SD Satu')
        ->assertSee('Siswa SD Dua')
        ->assertDontSee('Gunakan pencarian atau filter untuk menampilkan data siswa.');
});

it('pagination resets when jenjang filter changes', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);

    for ($i = 1; $i <= 11; $i++) {
        $student = Student::factory()->create(['class_id' => $class->id, 'nama_lengkap' => 'Siswa SMP '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'nis' => sprintf('PAGE-%02d', $i)]);
        $student->forceFill(['created_at' => now()->addSeconds($i)])->save();
    }

    $sdClass = SchoolClass::factory()->create(['level' => 1]);
    Student::factory()->create(['class_id' => $sdClass->id, 'nama_lengkap' => 'Siswa SD Satu', 'nis' => 'PAGE-SD-01']);

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->assertSee('Siswa SMP 11')
        ->assertDontSee('Siswa SMP 01')
        ->call('setPage', 2)
        ->assertSee('Siswa SMP 01')
        ->assertDontSee('Siswa SMP 11')
        ->set('filterLevel', 'SD')
        ->assertSee('Siswa SD Satu')
        ->assertDontSee('Siswa SMP 11')
        ->assertDontSee('Siswa SMP 01');
});

it('exposes all school levels as jenjang filter options', function () {
    makeDefaultListStudents();

    $component = Livewire::test(StudentManagement::class);

    foreach (SchoolLevel::cases() as $level) {
        $component->assertSeeHtml('<option value="'.$level->value.'">'.$level->value.'</option>');
    }
});
