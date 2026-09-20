<?php

use App\Livewire\SchoolClassManagement;
use App\Models\SchoolClass;
use Database\Seeders\SchoolDataSeeder;
use Livewire\Livewire;

it('persistent component: create III B -> edit -> edit -> delete level 3', function () {
    $this->seed(SchoolDataSeeder::class);

    $component = Livewire::test(SchoolClassManagement::class);

    $component->call('openModal')
        ->set('name', 'III B')
        ->set('level', '3')
        ->call('save')
        ->assertHasNoErrors();

    $class = SchoolClass::where('name', 'III B')->firstOrFail();
    $id = $class->id;

    $component->call('edit', $id)
        ->assertSet('classId', $id)
        ->assertSet('name', 'III B')
        ->assertSet('level', '3')
        ->set('name', 'III C')
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolClass::find($id)->name)->toBe('III C');

    $component->call('edit', $id)
        ->assertSet('classId', $id)
        ->assertSet('name', 'III C')
        ->assertSet('level', '3')
        ->call('save')
        ->assertHasNoErrors();

    $component->call('confirmDelete', $id)
        ->assertSet('deletingId', $id)
        ->assertSet('deleteClassName', 'III C')
        ->assertSet('deleteStudentCount', 0)
        ->assertSee('Hapus Kelas?');

    $component->call('delete');

    expect(SchoolClass::find($id))->toBeNull();
});
