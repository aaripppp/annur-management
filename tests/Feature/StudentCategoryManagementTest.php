<?php

use App\Livewire\StudentCategoryManagement;
use App\Models\Student;
use App\Models\StudentCategory;
use App\Models\User;
use Livewire\Livewire;

it('creates the initial student categories through the migration', function () {
    expect(StudentCategory::query()->orderBy('code')->pluck('name', 'code')->all())->toBe([
        'anak_guru' => 'Anak Guru',
        'anak_yatim' => 'Anak Yatim',
        'beasiswa' => 'Beasiswa',
    ]);
});

it('serves category management through the authenticated master data route', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('kategori-siswa.index'))
        ->assertOk()
        ->assertSee('Kategori Siswa')
        ->assertSee('Anak Guru');
});

it('creates a category and normalizes its code', function () {
    Livewire::test(StudentCategoryManagement::class)
        ->call('openModal')
        ->set('name', 'Anak Pegawai')
        ->set('code', 'Anak Pegawai')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Kategori siswa berhasil ditambahkan.');

    expect(StudentCategory::query()->where('code', 'anak_pegawai')->sole()->name)->toBe('Anak Pegawai');
});

it('requires a unique category code', function () {
    Livewire::test(StudentCategoryManagement::class)
        ->call('openModal')
        ->set('name', 'Duplikat Anak Guru')
        ->set('code', 'Anak Guru')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

it('allows editing the category code field', function () {
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();

    $html = Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->html();

    expect($html)->toContain('id="category-code" type="text" wire:model="code"')
        ->and($html)->not->toContain('wire:model="code" disabled');
});

it('changes a normalized category code without changing its ID or assignments', function () {
    $category = StudentCategory::query()->where('code', 'anak_yatim')->sole();
    $categoryId = $category->id;
    $student = Student::factory()->create();
    $student->categories()->attach($category);

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->set('name', 'Anak Asuh')
        ->set('code', 'Anak Asuh Prioritas')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Kategori siswa berhasil diperbarui.');

    expect($category->fresh()->id)->toBe($categoryId)
        ->and($category->fresh()->name)->toBe('Anak Asuh')
        ->and($category->fresh()->code)->toBe('anak_asuh_prioritas')
        ->and($student->categories()->whereKey($categoryId)->exists())->toBeTrue();
});

it('does not change code when only the category name is edited', function () {
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->set('name', 'Anak Tenaga Pendidik')
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Anak Tenaga Pendidik')
        ->and($category->fresh()->code)->toBe('anak_guru');
});

it('rejects a duplicate code while editing except for the current category', function () {
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->set('code', 'beasiswa')
        ->call('save')
        ->assertHasErrors(['code' => 'unique'])
        ->assertSee('Kode kategori sudah digunakan.');
});

it('can deactivate a category without removing assignments', function () {
    $category = StudentCategory::query()->where('code', 'beasiswa')->sole();
    $student = Student::factory()->create();
    $student->categories()->attach($category);

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Status kategori berhasil diperbarui.');

    expect($category->fresh()->is_active)->toBeFalse()
        ->and($student->categories()->whereKey($category->id)->exists())->toBeTrue();

    Livewire::test(StudentCategoryManagement::class)
        ->call('edit', $category->id)
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Status kategori berhasil diperbarui.');

    expect($category->fresh()->is_active)->toBeTrue();
});

it('blocks deleting a category assigned to students', function () {
    $category = StudentCategory::factory()->create();
    $student = Student::factory()->create();
    $student->categories()->attach($category);

    Livewire::test(StudentCategoryManagement::class)
        ->call('confirmDelete', $category->id)
        ->call('delete')
        ->assertSee('Kategori masih digunakan oleh 1 siswa dan tidak dapat dihapus.');

    expect($category->fresh())->not->toBeNull();
});

it('deletes an unused category', function () {
    $category = StudentCategory::factory()->create();

    Livewire::test(StudentCategoryManagement::class)
        ->call('confirmDelete', $category->id)
        ->call('delete')
        ->assertSee('Kategori siswa berhasil dihapus.');

    expect($category->fresh())->toBeNull();
});

it('renders CRUD feedback as auto-dismiss fixed toasts instead of persistent banners', function () {
    $component = Livewire::test(StudentCategoryManagement::class)
        ->call('openModal')
        ->set('name', 'Kategori Toast')
        ->set('code', 'kategori_toast')
        ->call('save')
        ->assertSee('Kategori siswa berhasil ditambahkan.');

    expect($component->html())->toContain('x-init="setTimeout(() => show = false, 3000)"')
        ->and($component->html())->toContain('fixed top-24 right-8 z-50')
        ->and($component->html())->not->toContain('class="mb-4 bg-secondary-container');
});
