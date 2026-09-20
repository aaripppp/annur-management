<?php

use App\Livewire\SchoolClassManagement;
use App\Models\SchoolClass;
use App\Models\Student;
use Database\Seeders\SchoolDataSeeder;
use Livewire\Livewire;

it('seeder memetakan tingkat kelas dengan benar', function () {
    $this->seed(SchoolDataSeeder::class);

    $expected = [
        'KB' => 'KB',
        'TK A' => 'TKA',
        'TK B' => 'TKB',
        'I A' => '1',
        'II A' => '2',
        'III A' => '3',
        'IV A' => '4',
        'V A' => '5',
        'VI A' => '6',
        'VII A' => '7',
        'VIII B' => '8',
        'IX A' => '9',
        'X A' => '10',
        'XI A' => '11',
        'XII A' => '12',
    ];

    foreach ($expected as $name => $levelName) {
        $class = SchoolClass::where('name', $name)->first();

        expect($class)->not->toBeNull()
            ->and($class->level_name)->toBe($levelName);
    }
});

it('semua kelas bisa diedit dengan tingkat yang benar', function () {
    $this->seed(SchoolDataSeeder::class);

    $expected = [
        'KB' => '-3',
        'TK A' => '-2',
        'TK B' => '-1',
        'I A' => '1',
        'VIII B' => '8',
        'XII A' => '12',
    ];

    foreach ($expected as $name => $levelValue) {
        $class = SchoolClass::where('name', $name)->firstOrFail();

        $component = Livewire::test(SchoolClassManagement::class)
            ->call('edit', $class->id);

        $component->assertHasNoErrors()
            ->assertSet('isEditing', true)
            ->assertSet('classId', $class->id)
            ->assertSet('name', $name)
            ->assertSet('level', $levelValue);
    }
});

it('menyimpan edit kelas tanpa mengubah level ke nilai yang salah', function () {
    $this->seed(SchoolDataSeeder::class);

    $tkb = SchoolClass::where('name', 'TK B')->firstOrFail();

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $tkb->id)
        ->set('name', 'TK B')
        ->set('level', '-1')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($tkb->id)->level)->toBe(-1);

    $viii = SchoolClass::where('name', 'VIII B')->firstOrFail();

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $viii->id)
        ->set('name', 'VIII B')
        ->set('level', '8')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($viii->id)->level)->toBe(8);
});

it('membuat kelas baru lalu kelas tersebut bisa diedit', function () {
    $this->seed(SchoolDataSeeder::class);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'II B')
        ->set('level', '2')
        ->call('save')
        ->assertHasNoErrors();

    $newClass = SchoolClass::where('name', 'II B')->first();

    expect($newClass)->not->toBeNull();

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $newClass->id)
        ->assertHasNoErrors()
        ->assertSet('isEditing', true)
        ->assertSet('classId', $newClass->id)
        ->assertSet('name', 'II B')
        ->assertSet('level', '2');
});

it('bisa membuat kelas baru TK dan non-TK', function () {
    $this->seed(SchoolDataSeeder::class);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'TK C')
        ->set('level', '-2')
        ->call('save')
        ->assertHasNoErrors();

    $tk = SchoolClass::where('name', 'TK C')->first();

    expect($tk)->not->toBeNull()
        ->and($tk->level)->toBe(-2)
        ->and($tk->level_name)->toBe('TKA');

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'X MIPA 1')
        ->set('level', '10')
        ->call('save')
        ->assertHasNoErrors();

    $sma = SchoolClass::where('name', 'X MIPA 1')->first();

    expect($sma)->not->toBeNull()
        ->and($sma->level)->toBe(10)
        ->and($sma->level_name)->toBe('10');
});

it('menampilkan dan mengedit KB sebagai tingkat pertama TK', function () {
    $this->seed(SchoolDataSeeder::class);
    $kb = SchoolClass::query()->where('name', 'KB')->sole();

    Livewire::test(SchoolClassManagement::class)
        ->assertSee('KB')
        ->call('edit', $kb->id)
        ->assertSet('level', '-3')
        ->set('level', '-3')
        ->call('save')
        ->assertHasNoErrors();

    expect($kb->fresh()->level)->toBe(-3);
});

it('mengedit nama kelas berhasil', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $class->id)
        ->set('name', 'II C')
        ->set('level', '2')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($class->id)->name)->toBe('II C');
});

it('mengedit tingkat kelas berhasil', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $class->id)
        ->set('name', 'II B')
        ->set('level', '5')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($class->id)->level)->toBe(5);
});

it('update kelas tidak dianggap duplicate terhadap dirinya sendiri', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $class->id)
        ->set('name', 'II B')
        ->set('level', '2')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($class->id)->name)->toBe('II B');
});

it('membuat nama kelas duplikat ditolak', function () {
    SchoolClass::create(['name' => 'II B', 'level' => 2]);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'II B')
        ->set('level', '2')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('validasi menolak tingkat yang tidak dikenal', function () {
    $this->seed(SchoolDataSeeder::class);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'Contoh')
        ->set('level', '99')
        ->call('save')
        ->assertHasErrors(['level']);
});

it('menerima nilai tingkat kanonikal dari dropdown saat membuat kelas', function (string $name, string $level, int $storedLevel) {
    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', $name)
        ->set('level', $level)
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::query()->where('name', $name)->sole()->level)->toBe($storedLevel);
})->with([
    'KB' => ['KB-1', '-3', -3],
    'TK A' => ['TK A-5', '-2', -2],
    'TK B' => ['TK B-5', '-1', -1],
    'positive' => ['II Contoh', '2', 2],
]);

it('mengizinkan beberapa kelas memakai tingkat TK negatif yang sama', function (string $level, int $storedLevel) {
    SchoolClass::query()->create(['name' => "Kelas Awal {$level}", 'level' => $storedLevel]);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', "Kelas Tambahan {$level}")
        ->set('level', $level)
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::query()->where('level', $storedLevel)->count())->toBe(2);
})->with([
    'KB' => ['-3', -3],
    'TK A' => ['-2', -2],
    'TK B' => ['-1', -1],
]);

it('mengedit kelas ke setiap tingkat TK negatif yang valid', function (string $level, int $storedLevel) {
    $class = SchoolClass::query()->create(['name' => "Kelas Edit {$level}", 'level' => 1]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $class->id)
        ->assertSet('level', '1')
        ->set('level', $level)
        ->call('save')
        ->assertHasNoErrors();

    expect($class->fresh()->level)->toBe($storedLevel);
})->with([
    'KB' => ['-3', -3],
    'TK A' => ['-2', -2],
    'TK B' => ['-1', -1],
]);

it('merender semua nilai dropdown dari sumber tingkat kanonikal yang diterima backend', function () {
    $options = SchoolClass::levelOptions();
    $component = Livewire::test(SchoolClassManagement::class)->call('openModal');

    expect($options)->toBe(SchoolClass::levelLabels())
        ->and(array_keys($options))->toBe([-3, -2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);

    foreach ($options as $value => $label) {
        $component->assertSeeHtml('<option value="'.$value.'">');
    }
});

it('menghapus kelas kosong berhasil setelah konfirmasi', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);

    $component = Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $class->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deleteClassName', 'II B')
        ->assertSet('deleteStudentCount', 0);

    $component->call('delete');

    expect(SchoolClass::find($class->id))->toBeNull();
});

it('menghapus kelas yang memiliki siswa ditolak', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);
    Student::factory()->count(5)->create(['class_id' => $class->id]);

    $component = Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $class->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deleteClassName', 'II B')
        ->assertSet('deleteStudentCount', 5)
        ->assertSee('Lihat Siswa');

    $component->call('delete');

    expect(SchoolClass::find($class->id))->not->toBeNull();
});

it('siswa tidak ikut terhapus ketika penghapusan kelas diblokir', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);
    $students = Student::factory()->count(3)->create(['class_id' => $class->id]);

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $class->id)
        ->call('delete');

    expect(Student::find($students->first()->id))->not->toBeNull()
        ->and(Student::count())->toBe(3);
});

it('jumlah siswa pada kelas tampil benar', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);
    Student::factory()->count(5)->create(['class_id' => $class->id]);

    Livewire::test(SchoolClassManagement::class)
        ->assertSee('5 Siswa');
});

it('list refresh setelah create dan update tanpa reload', function () {
    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'II B')
        ->set('level', '2')
        ->call('save')
        ->assertSee('II B');

    $class = SchoolClass::where('name', 'II B')->firstOrFail();

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $class->id)
        ->set('name', 'II C')
        ->set('level', '2')
        ->call('save')
        ->assertDontSee('II B')
        ->assertSee('II C');
});

it('level tidak harus unik — beberapa kelas boleh memiliki level yang sama', function () {
    $this->seed(SchoolDataSeeder::class);

    SchoolClass::create(['name' => '1B', 'level' => 1]);
    SchoolClass::create(['name' => 'III B', 'level' => 3]);

    expect(SchoolClass::where('level', 1)->count())->toBe(2)
        ->and(SchoolClass::where('level', 3)->count())->toBe(2);
});

it('dua kelas baru di level yang sama bisa diedit berulang kali dan dihapus jika kosong', function () {
    $oneA = SchoolClass::create(['name' => '1A', 'level' => 1]);
    $oneB = SchoolClass::create(['name' => '1B', 'level' => 1]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $oneA->id)
        ->assertSet('classId', $oneA->id)
        ->assertSet('name', '1A')
        ->set('name', '1C')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($oneA->id)->name)->toBe('1C');

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $oneA->id)
        ->assertSet('classId', $oneA->id)
        ->assertSet('name', '1C')
        ->set('name', '1D')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($oneA->id)->name)->toBe('1D');

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $oneB->id)
        ->assertSet('classId', $oneB->id)
        ->assertSet('name', '1B')
        ->set('name', '1E')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($oneB->id)->name)->toBe('1E');

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $oneA->id)
        ->assertSet('deletingId', $oneA->id)
        ->assertSet('deleteStudentCount', 0)
        ->call('delete');

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $oneB->id)
        ->assertSet('deletingId', $oneB->id)
        ->assertSet('deleteStudentCount', 0)
        ->call('delete');

    expect(SchoolClass::find($oneA->id))->toBeNull()
        ->and(SchoolClass::find($oneB->id))->toBeNull();
});

it('dua kelas baru di level 3 bisa diedit berulang kali dan dihapus jika kosong', function () {
    $threeA = SchoolClass::create(['name' => 'III B', 'level' => 3]);
    $threeB = SchoolClass::create(['name' => 'III C', 'level' => 3]);

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $threeA->id)
        ->assertSet('classId', $threeA->id)
        ->assertSet('name', 'III B')
        ->set('name', 'III D')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($threeA->id)->name)->toBe('III D');

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $threeA->id)
        ->assertSet('classId', $threeA->id)
        ->assertSet('name', 'III D')
        ->set('name', 'III E')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($threeA->id)->name)->toBe('III E');

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $threeB->id)
        ->assertSet('classId', $threeB->id)
        ->set('name', 'III F')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($threeB->id)->name)->toBe('III F');

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $threeA->id)
        ->assertSet('deletingId', $threeA->id)
        ->assertSet('deleteStudentCount', 0)
        ->call('delete');

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $threeB->id)
        ->assertSet('deletingId', $threeB->id)
        ->assertSet('deleteStudentCount', 0)
        ->call('delete');

    expect(SchoolClass::find($threeA->id))->toBeNull()
        ->and(SchoolClass::find($threeB->id))->toBeNull();
});

it('wire:key pada baris list menggunakan ID kelas', function () {
    $class = SchoolClass::create(['name' => 'II B', 'level' => 2]);

    Livewire::test(SchoolClassManagement::class)
        ->assertSee('school-class-'.$class->id, false);
});
