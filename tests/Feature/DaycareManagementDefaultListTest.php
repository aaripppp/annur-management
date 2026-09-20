<?php

use App\Livewire\DaycareManagement;
use App\Models\DaycareChild;
use Livewire\Livewire;

it('menampilkan 10 anak terbaru secara default dengan urutan terbaru lebih dulu', function () {
    $ids = [];

    for ($i = 1; $i <= 11; $i++) {
        $child = DaycareChild::factory()->create([
            'nama_lengkap' => 'Anak Terbaru '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
        $child->forceFill(['created_at' => now()->addSeconds($i)])->save();
        $ids[$i] = $child->id;
    }

    $component = Livewire::test(DaycareManagement::class)
        ->assertSee('Anak Terbaru 11')
        ->assertSee('Anak Terbaru 02')
        ->assertDontSee('Anak Terbaru 01');

    $pageIds = collect($component->viewData('children')->items())->pluck('id')->all();

    expect($pageIds)->toBe(array_slice(array_values(array_reverse($ids)), 0, 10))->toHaveCount(10);
    expect($component->viewData('children')->total())->toBe(11);
});

it('menunjukkan anak lebih lama di halaman anak berikutnya', function () {
    for ($i = 1; $i <= 11; $i++) {
        $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        $child->forceFill(['created_at' => now()->addSeconds($i)])->save();
    }

    Livewire::test(DaycareManagement::class)
        ->assertSee('Anak 11')
        ->assertDontSee('Anak 01')
        ->call('setPage', 2, 'childrenPage')
        ->assertSee('Anak 01')
        ->assertDontSee('Anak 11');
});

it('pencarian tetap berfungsi lalu kembali ke daftar terbaru saat dibersihkan', function () {
    foreach (['Ahmad', 'Budi', 'Citra'] as $nama) {
        DaycareChild::factory()->create(['nama_lengkap' => $nama]);
    }

    Livewire::test(DaycareManagement::class)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad')
        ->assertDontSee('Budi')
        ->set('search', '')
        ->assertSee('Ahmad')
        ->assertSee('Budi')
        ->assertSee('Citra');
});

it('filter kelas tetap bekerja pada daftar terbaru', function () {
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas A', 'kelas' => 'A']);
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas B', 'kelas' => 'B']);

    Livewire::test(DaycareManagement::class)
        ->set('filterClass', 'A')
        ->assertSee('Anak Kelas A')
        ->assertDontSee('Anak Kelas B');
});

it('filter status aktif tetap bekerja pada daftar terbaru', function () {
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Aktif']);
    DaycareChild::factory()->inactive()->create(['nama_lengkap' => 'Anak Nonaktif']);

    Livewire::test(DaycareManagement::class)
        ->set('filterStatus', 'aktif')
        ->assertSee('Anak Aktif')
        ->assertDontSee('Anak Nonaktif')
        ->set('filterStatus', 'nonaktif')
        ->assertSee('Anak Nonaktif')
        ->assertDontSee('Anak Aktif')
        ->set('filterStatus', '')
        ->assertSee('Anak Aktif')
        ->assertSee('Anak Nonaktif');
});
