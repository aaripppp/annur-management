<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Services\JemputanDriverSemesterRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class JemputanDriverSemesterRecap extends Component
{
    public string $selectedAcademicYearId = '';

    public string $selectedSchoolLevel = '';

    public int $selectedSemester = 1;

    public function mount(): void
    {
        $this->authorizeRecap();
        $this->selectedAcademicYearId = (string) (AcademicYear::active()?->id
            ?? AcademicYear::query()->latest('start_date')->value('id')
            ?? '');
    }

    public function updatedSelectedAcademicYearId(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedAcademicYearId', $this->rules());
    }

    public function updatedSelectedSchoolLevel(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedSchoolLevel', $this->rules());
    }

    public function updatedSelectedSemester(): void
    {
        $this->authorizeRecap();
        $this->validateOnly('selectedSemester', $this->rules());
    }

    public function exportExcel(): void
    {
        $this->authorizeRecap();
        $this->validate($this->rules());

        $this->redirectRoute('jemputan.semester-recap.export', [
            'academic_year_id' => $this->selectedAcademicYearId,
            'school_level' => $this->selectedSchoolLevel !== '' ? $this->selectedSchoolLevel : null,
            'semester' => $this->selectedSemester,
        ]);
    }

    public function render(JemputanDriverSemesterRecapService $service): View
    {
        $this->authorizeRecap();

        $academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'year', 'start_date', 'end_date']);
        $report = null;

        if ($this->filtersAreValid()) {
            $report = $service->generate(
                (int) $this->selectedAcademicYearId,
                $this->selectedSchoolLevel === '' ? null : SchoolLevel::from($this->selectedSchoolLevel),
                $this->selectedSemester,
            );
        }

        return view('livewire.jemputan-driver-semester-recap', [
            'academicYears' => $academicYears,
            'schoolLevels' => SchoolLevel::cases(),
            'report' => $report,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'selectedSchoolLevel' => ['nullable', Rule::enum(SchoolLevel::class)],
            'selectedSemester' => ['required', 'integer', Rule::in([1, 2])],
        ];
    }

    private function filtersAreValid(): bool
    {
        return ! Validator::make([
            'selectedAcademicYearId' => $this->selectedAcademicYearId,
            'selectedSchoolLevel' => $this->selectedSchoolLevel,
            'selectedSemester' => $this->selectedSemester,
        ], $this->rules())->fails();
    }

    private function authorizeRecap(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
    }
}
