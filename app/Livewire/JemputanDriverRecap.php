<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\SchoolClass;
use App\Services\JemputanDriverRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class JemputanDriverRecap extends Component
{
    use WithPagination;

    public string $selectedAcademicYearId = '';

    public string $selectedDriverId = '';

    public string $selectedSchoolLevel = '';

    public string $selectedSchoolClassId = '';

    public string $selectedMobileMonth = '';

    public int $perPage = 25;

    public string $exportMode = 'all';

    public function mount(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->selectedAcademicYearId = (string) (AcademicYear::active()?->id
            ?? AcademicYear::query()->latest('start_date')->value('id')
            ?? '');
    }

    public function updatedSelectedAcademicYearId(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedAcademicYearId', $this->rules());
        $this->reset('selectedDriverId', 'selectedSchoolClassId', 'selectedMobileMonth');
        $this->resetPage('recapPage');
    }

    public function updatedSelectedDriverId(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedDriverId', $this->rules());
        $this->resetPage('recapPage');
    }

    public function updatedSelectedSchoolLevel(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedSchoolLevel', $this->rules());
        $this->reset('selectedSchoolClassId');
        $this->resetPage('recapPage');
    }

    public function updatedSelectedSchoolClassId(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedSchoolClassId', $this->rules());

        if ($this->selectedSchoolClassId !== '' && ! $this->selectedClassIsAllowed()) {
            $this->addError('selectedSchoolClassId', 'Kelas tidak sesuai dengan tahun ajaran dan jenjang yang dipilih.');
        }

        $this->resetPage('recapPage');
    }

    public function updatedPerPage(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('perPage', $this->rules());
        $this->resetPage('recapPage');
    }

    public function updatedSelectedMobileMonth(): void
    {
        $this->authorizeRecap();

        if ($this->selectedMobileMonth !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->selectedMobileMonth) !== 1) {
            $this->addError('selectedMobileMonth', 'Bulan yang dipilih tidak valid.');
        }
    }

    public function updatedExportMode(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('exportMode', $this->exportRules());
        $this->resetErrorBag('selectedDriverId');
    }

    public function exportExcel(): void
    {
        $this->authorizeRecap();
        $this->validate($this->exportRules());

        if (! $this->selectedClassIsAllowed()) {
            throw ValidationException::withMessages([
                'selectedSchoolClassId' => 'Kelas tidak sesuai dengan tahun ajaran dan jenjang yang dipilih.',
            ]);
        }

        $this->redirectRoute('jemputan.recap.export', [
            'mode' => $this->exportMode,
            'academic_year_id' => $this->selectedAcademicYearId,
            'driver_id' => $this->exportMode === 'filtered' ? $this->selectedDriverId : null,
            'school_level' => $this->selectedSchoolLevel !== '' ? $this->selectedSchoolLevel : null,
            'class_id' => $this->selectedSchoolClassId !== '' ? $this->selectedSchoolClassId : null,
        ]);
    }

    public function updatedPaginators(): void
    {
        $this->authorizeRecap();
    }

    public function render(JemputanDriverRecapService $service): View
    {
        $this->authorizeRecap();

        $academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'year', 'start_date', 'end_date']);
        $drivers = JemputanDriver::query()
            ->when(ctype_digit($this->selectedAcademicYearId), fn (Builder $query) => $query
                ->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->currentMembership()))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $classes = $this->availableClasses();
        $report = null;

        if ($this->selectedDriverId !== '' && $this->filtersAreValid()) {
            $report = $service->generate(
                (int) $this->selectedAcademicYearId,
                (int) $this->selectedDriverId,
                $this->selectedSchoolLevel === '' ? null : SchoolLevel::from($this->selectedSchoolLevel),
                $this->selectedSchoolClassId === '' ? null : (int) $this->selectedSchoolClassId,
                $this->perPage,
                $this->getPage('recapPage'),
                false,
            );
            $report['rows'] = $this->withTariffDisplay($report['rows']);

            if ($this->selectedMobileMonth === '' || ! collect($report['months'])->contains('key', $this->selectedMobileMonth)) {
                $this->selectedMobileMonth = $report['months'][0]['key'] ?? '';
            }
        }

        return view('livewire.jemputan-driver-recap', [
            'academicYears' => $academicYears,
            'drivers' => $drivers,
            'schoolLevels' => SchoolLevel::cases(),
            'classes' => $classes,
            'report' => $report,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'selectedDriverId' => ['nullable', 'integer', 'exists:jemputan_drivers,id'],
            'selectedSchoolLevel' => ['nullable', Rule::enum(SchoolLevel::class)],
            'selectedSchoolClassId' => ['nullable', 'integer', 'exists:school_classes,id'],
            'perPage' => ['required', 'integer', Rule::in([10, 25, 50])],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function exportRules(): array
    {
        return [
            'exportMode' => ['required', 'string', Rule::in(['all', 'filtered'])],
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'selectedDriverId' => ['nullable', 'integer', 'exists:jemputan_drivers,id', 'required_if:exportMode,filtered'],
            'selectedSchoolLevel' => ['nullable', Rule::enum(SchoolLevel::class)],
            'selectedSchoolClassId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ];
    }

    private function authorizeRecap(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
    }

    private function filtersAreValid(): bool
    {
        $validator = Validator::make([
            'selectedAcademicYearId' => $this->selectedAcademicYearId,
            'selectedDriverId' => $this->selectedDriverId,
            'selectedSchoolLevel' => $this->selectedSchoolLevel,
            'selectedSchoolClassId' => $this->selectedSchoolClassId,
            'perPage' => $this->perPage,
        ], $this->rules());

        return ! $validator->fails() && $this->selectedClassIsAllowed();
    }

    /** @return Collection<int, SchoolClass> */
    private function availableClasses(): Collection
    {
        if (! ctype_digit($this->selectedAcademicYearId)) {
            return collect();
        }

        return SchoolClass::query()
            ->join('student_academic_enrollments as recap_enrollments', 'recap_enrollments.school_class_id', '=', 'school_classes.id')
            ->where('recap_enrollments.academic_year_id', (int) $this->selectedAcademicYearId)
            ->when($this->selectedSchoolLevel !== '' && SchoolLevel::tryFrom($this->selectedSchoolLevel) !== null, function (Builder $query): void {
                $query->whereIn('school_classes.level', SchoolLevel::from($this->selectedSchoolLevel)->classLevels());
            })
            ->select(['school_classes.id', 'school_classes.name', 'school_classes.level'])
            ->distinct()
            ->orderBy('school_classes.level')
            ->orderBy('school_classes.name')
            ->get();
    }

    private function selectedClassIsAllowed(): bool
    {
        if ($this->selectedSchoolClassId === '') {
            return true;
        }

        if (! ctype_digit($this->selectedAcademicYearId) || ! ctype_digit($this->selectedSchoolClassId)) {
            return false;
        }

        return $this->availableClasses()->contains(
            fn (SchoolClass $schoolClass): bool => $schoolClass->id === (int) $this->selectedSchoolClassId
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withTariffDisplay(array $rows): array
    {
        return array_map(function (array $row): array {
            $effectiveAmounts = collect($row['months'])
                ->filter(fn (array $month): bool => $month['bill_count'] > 0)
                ->pluck('effective_billed_amount')
                ->map(fn ($amount): float => round((float) $amount, 2))
                ->unique()
                ->values();

            $row['tariff_amount'] = $effectiveAmounts->count() === 1 ? $effectiveAmounts->first() : null;
            $row['tariff_varies'] = $effectiveAmounts->count() > 1;

            return $row;
        }, $rows);
    }
}
