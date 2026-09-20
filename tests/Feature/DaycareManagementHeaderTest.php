<?php

use App\Livewire\DaycareManagement;
use App\Models\DaycareChild;
use App\Models\User;
use Livewire\Livewire;

it('Data Daycare menampilkan tiga aksi header dalam urutan Tambah lalu Import lalu Update', function () {
    $html = Livewire::test(DaycareManagement::class)->html();

    $tambahPosition = strpos($html, 'wire:click="openModal"');
    $importPosition = strpos($html, route('daycare.import'));
    $updatePosition = strpos($html, route('daycare.update'));

    expect($tambahPosition)->not->toBeFalse()
        ->and($importPosition)->not->toBeFalse()
        ->and($updatePosition)->not->toBeFalse()
        ->and($tambahPosition)->toBeLessThan($importPosition)
        ->and($importPosition)->toBeLessThan($updatePosition);
});

it('Tambah Anak tetap menjadi aksi primer dan Import serta Update menjadi aksi sekunder', function () {
    $html = Livewire::test(DaycareManagement::class)->html();

    $primaryButton = substr($html, strpos($html, 'wire:click="openModal"') - 200, 700);

    expect($primaryButton)->toContain('bg-primary hover:bg-primary/90 text-on-primary')
        ->and($primaryButton)->toContain('Tambah Anak');

    $importAnchorStart = strpos($html, route('daycare.import')) - 200;
    $importAnchor = substr($html, $importAnchorStart, 700);

    expect($importAnchor)->toContain('border border-primary text-primary hover:bg-primary-fixed')
        ->and($importAnchor)->toContain('Import Daycare')
        ->and($importAnchor)->not->toContain('bg-primary hover:bg-primary/90');

    $updateAnchorStart = strpos($html, route('daycare.update')) - 200;
    $updateAnchor = substr($html, $updateAnchorStart, 700);

    expect($updateAnchor)->toContain('border border-primary text-primary hover:bg-primary-fixed')
        ->and($updateAnchor)->toContain('Update Data Daycare')
        ->and($updateAnchor)->not->toContain('bg-primary hover:bg-primary/90');
});

it('Import Daycare dan Update Data Daycare tidak muncul sebagai menu sidebar', function () {
    $this->actingAs(User::factory()->create());

    $content = $this->get(route('daycare.index'))->getContent();

    $sidebarChildPattern = 'rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="';

    expect(substr_count($content, $sidebarChildPattern.route('daycare.import').'"'))->toBe(0)
        ->and(substr_count($content, $sidebarChildPattern.route('daycare.update').'"'))->toBe(0)
        ->and(substr_count($content, $sidebarChildPattern.route('daycare.index').'"'))->toBe(1)
        ->and(substr_count($content, $sidebarChildPattern.route('daycare.payment.entry').'"'))->toBe(1)
        ->and(substr_count($content, $sidebarChildPattern.route('daycare.report.daily').'"'))->toBe(1);
});

it('halaman import dan update daycare hanya untuk pengguna terautentikasi', function () {
    $this->get(route('daycare.import'))->assertRedirect(route('login'));
    $this->get(route('daycare.update'))->assertRedirect(route('login'));
});

it('halaman import dan update daycare dapat diakses pengguna terautentikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('daycare.import'))->assertOk();
    $this->get(route('daycare.update'))->assertOk();
});

it('membuat anak dari modal tetap hanya membutuhkan nama lengkap dan kelas', function () {
    $component = Livewire::test(DaycareManagement::class)->call('openModal');

    $component->set('nama_lengkap', 'Anak Header')
        ->set('kelas', 'A')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('daycare.index'));

    expect(DaycareChild::query()->sole()->nama_lengkap)->toBe('Anak Header');
});
