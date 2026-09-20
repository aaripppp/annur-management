<?php

use App\Livewire\SchoolClassManagement;
use App\Models\SchoolClass;
use Database\Seeders\SchoolDataSeeder;
use Livewire\Livewire;

it('repro level 3: create III B -> edit ke III C -> edit lagi -> delete', function () {
    $this->seed(SchoolDataSeeder::class);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'III B')
        ->set('level', '3')
        ->call('save')
        ->assertHasNoErrors();

    $class = SchoolClass::where('name', 'III B')->firstOrFail();
    $originalId = $class->id;

    $component = Livewire::test(SchoolClassManagement::class)
        ->call('edit', $originalId)
        ->assertSet('classId', $originalId)
        ->assertSet('name', 'III B')
        ->assertSet('level', '3')
        ->set('name', 'III C')
        ->call('save')
        ->assertHasNoErrors();

    $afterFirstEdit = SchoolClass::find($originalId);

    $component = Livewire::test(SchoolClassManagement::class)
        ->call('edit', $originalId)
        ->assertSet('classId', $originalId)
        ->assertSet('name', 'III C')
        ->assertSet('level', '3')
        ->call('save')
        ->assertHasNoErrors();

    $afterSecondEdit = SchoolClass::find($originalId);

    $component = Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $originalId)
        ->assertSet('deletingId', $originalId)
        ->assertSet('deleteClassName', 'III C')
        ->assertSet('deleteStudentCount', 0);

    $component->call('delete');

    expect(SchoolClass::find($originalId))->toBeNull();
});

it('repro level 2: create II B -> edit -> edit lagi -> delete (pembanding)', function () {
    $this->seed(SchoolDataSeeder::class);

    Livewire::test(SchoolClassManagement::class)
        ->call('openModal')
        ->set('name', 'II B')
        ->set('level', '2')
        ->call('save')
        ->assertHasNoErrors();

    $class = SchoolClass::where('name', 'II B')->firstOrFail();
    $originalId = $class->id;

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $originalId)
        ->set('name', 'II C')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(SchoolClassManagement::class)
        ->call('edit', $originalId)
        ->assertSet('name', 'II C')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(SchoolClassManagement::class)
        ->call('confirmDelete', $originalId)
        ->call('delete');

    expect(SchoolClass::find($originalId))->toBeNull();
});
