<?php

namespace App\Livewire;

use App\Models\PpdbDefaultClassMapping;
use App\Models\SchoolClass;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PpdbDefaultClassMappingManagement extends Component
{
    /**
     * @var array<int, string|null>
     */
    public array $selections = [];

    public function mount(): void
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (! (auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())) {
            abort(403);
        }

        $this->loadSelections();
    }

    protected function loadSelections(): void
    {
        $this->selections = [];

        $mappings = PpdbDefaultClassMapping::query()->get();

        foreach ($mappings as $mapping) {
            $this->selections[(int) $mapping->class_level] = (string) $mapping->school_class_id;
        }
    }

    public function save(): void
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (! (auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())) {
            abort(403);
        }

        $validLevels = PpdbDefaultClassMapping::validClassLevels();

        DB::transaction(function () use ($validLevels): void {
            $existing = PpdbDefaultClassMapping::query()
                ->get()
                ->keyBy('class_level');

            $submittedLevels = array_keys($this->selections);

            foreach ($submittedLevels as $level) {
                $levelInt = (int) $level;
                if (! in_array($levelInt, $validLevels, true)) {
                    throw ValidationException::withMessages([
                        'selections.'.$levelInt => 'Tingkat kelas tidak valid.',
                    ]);
                }

                $schoolClassId = $this->selections[$levelInt] ?? $this->selections[$level] ?? null;
                if ($schoolClassId === null || $schoolClassId === '') {
                    continue;
                }

                $schoolClassIdInt = (int) $schoolClassId;

                $schoolClass = SchoolClass::query()->find($schoolClassIdInt);
                if (! $schoolClass) {
                    throw ValidationException::withMessages([
                        'selections.'.$levelInt => 'Kelas tidak valid.',
                    ]);
                }

                if ((int) $schoolClass->level !== $levelInt) {
                    throw ValidationException::withMessages([
                        'selections.'.$levelInt => 'Kelas yang dipilih harus memiliki tingkat yang sama.',
                    ]);
                }

                $mapping = $existing->get($levelInt);
                if (! $mapping) {
                    $mapping = new PpdbDefaultClassMapping;
                    $mapping->class_level = $levelInt;
                }

                $mapping->school_class_id = $schoolClassIdInt;
                $mapping->setRelation('schoolClass', $schoolClass);
                $mapping->save();
            }
        });

        $this->loadSelections();

        session()->flash('success', 'Pengaturan kelas default PPDB berhasil disimpan.');
    }

    public function render(): View
    {
        if (! auth()->check() || ! (auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())) {
            abort(403);
        }

        $classes = SchoolClass::query()
            ->select(['id', 'name', 'level'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $classesByLevel = $classes->groupBy(function (SchoolClass $class) {
            return (int) $class->level;
        });

        $levelsByGroup = [
            'TK' => [-3, -2, -1],
            'SD' => [1, 2, 3, 4, 5, 6],
            'SMP' => [7, 8, 9],
            'SMA' => [10, 11, 12],
        ];

        $levelLabels = [
            -3 => 'KB (-3)',
            -2 => 'TKA (-2)',
            -1 => 'TKB (-1)',
            1 => 'Kelas 1',
            2 => 'Kelas 2',
            3 => 'Kelas 3',
            4 => 'Kelas 4',
            5 => 'Kelas 5',
            6 => 'Kelas 6',
            7 => 'Kelas 7',
            8 => 'Kelas 8',
            9 => 'Kelas 9',
            10 => 'Kelas 10',
            11 => 'Kelas 11',
            12 => 'Kelas 12',
        ];

        return view('livewire.ppdb.default-class-mappings.index', [
            'classesByLevel' => $classesByLevel,
            'levelsByGroup' => $levelsByGroup,
            'levelLabels' => $levelLabels,
        ]);
    }
}
