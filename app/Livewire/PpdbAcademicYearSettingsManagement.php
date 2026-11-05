<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\PpdbAcademicYearSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PpdbAcademicYearSettingsManagement extends Component
{
    /**
     * @var array<int, bool>
     */
    public array $openYears = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->loadSettings();
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $validated = validator(
            ['openYears' => $this->openYears],
            [
                'openYears' => ['array'],
                'openYears.*' => ['boolean'],
            ],
            [
                'openYears.array' => 'Pengaturan tahun ajaran tidak valid.',
                'openYears.*.boolean' => 'Status PPDB harus berupa pilihan dibuka atau ditutup.',
            ]
        )->validate();

        $submittedYearIds = collect(array_keys($validated['openYears']))
            ->map(function (int|string $academicYearId): int {
                if (! is_int($academicYearId) && ! ctype_digit($academicYearId)) {
                    throw ValidationException::withMessages([
                        'openYears' => 'Tahun ajaran yang dipilih tidak valid.',
                    ]);
                }

                return (int) $academicYearId;
            })
            ->filter(fn (int $academicYearId): bool => $academicYearId > 0)
            ->unique()
            ->values();

        if ($submittedYearIds->count() !== count($validated['openYears'])) {
            throw ValidationException::withMessages([
                'openYears' => 'Tahun ajaran yang dipilih tidak valid.',
            ]);
        }

        DB::transaction(function () use ($submittedYearIds, $validated): void {
            $currentYearIds = AcademicYear::query()
                ->whereKey($submittedYearIds)
                ->lockForUpdate()
                ->pluck('id');

            if ($currentYearIds->count() !== $submittedYearIds->count()) {
                throw ValidationException::withMessages([
                    'openYears' => 'Data tahun ajaran berubah. Muat ulang halaman lalu coba kembali.',
                ]);
            }

            if ($submittedYearIds->isEmpty()) {
                return;
            }

            $now = now();
            $rows = $submittedYearIds->map(fn (int $academicYearId): array => [
                'academic_year_id' => $academicYearId,
                'is_open' => (bool) $validated['openYears'][$academicYearId],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            PpdbAcademicYearSetting::query()->upsert(
                $rows,
                ['academic_year_id'],
                ['is_open', 'updated_at']
            );
        });

        $this->loadSettings();
        session()->flash('success', 'Pengaturan tahun ajaran PPDB berhasil disimpan.');
    }

    public function render(): View
    {
        $this->authorizeAccess();

        $academicYears = AcademicYear::query()
            ->select(['id', 'year', 'is_active', 'start_date', 'end_date'])
            ->orderByDesc('start_date')
            ->get();

        return view('livewire.ppdb.academic-year-settings.index', [
            'academicYears' => $academicYears,
        ]);
    }

    protected function loadSettings(): void
    {
        $this->openYears = PpdbAcademicYearSetting::query()
            ->pluck('is_open', 'academic_year_id')
            ->map(fn (mixed $isOpen): bool => (bool) $isOpen)
            ->all();
    }

    protected function authorizeAccess(): void
    {
        if (! auth()->check() || ! (auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())) {
            abort(403);
        }
    }
}
