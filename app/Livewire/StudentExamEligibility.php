<?php

namespace App\Livewire;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use App\Services\StudentExamEligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class StudentExamEligibility extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'jenjang')]
    public string $filterLevel = '';

    #[Url(as: 'kelas')]
    public string $filterClassId = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'tahun')]
    public string $yearlyAcademicYearId = '';

    public bool $isRequirementModalOpen = false;

    #[Locked]
    public ?int $requirementConfigId = null;

    #[Locked]
    public string $requirementLevel = 'TK';

    public bool $isResetConfirmOpen = false;

    #[Locked]
    public string $resetLevel = 'TK';

    public string $monthlyStart = '';

    public string $monthlyEnd = '';

    /** @var array<string, array<int|string, bool>> */
    public array $selectedRequirements = [];

    /** @var array<string, array<int|string, string>> */
    public array $requirementPercentages = [];

    /** @var array<string, array{requirement_id: int, enabled: bool, percentage: string}> */
    public array $pooledRequirements = [];

    public bool $isDetailModalOpen = false;

    /** @var array<string, mixed> */
    public array $detail = [];

    /** @var array<int, string> Query-string param names explicitly present in the page URL. */
    #[Locked]
    public array $explicitFilterKeys = [];

    public function mount(): void
    {
        $this->explicitFilterKeys = array_keys(request()->query());

        $activeAcademicYear = AcademicYear::active();

        if ($this->yearlyAcademicYearId === '') {
            $this->yearlyAcademicYearId = (string) $activeAcademicYear?->id;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->persistExamFilters();
    }

    public function updatedFilterLevel(): void
    {
        if ($this->filterClassId !== '' && ! $this->currentClassMatchesLevel()) {
            $this->filterClassId = '';
        }

        $this->resetPage();
        $this->persistExamFilters();
    }

    public function updatedFilterClassId(): void
    {
        $this->resetPage();
        $this->persistExamFilters();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->persistExamFilters();
    }

    public function updatedYearlyAcademicYearId(): void
    {
        $this->resetPage();
        $this->persistExamFilters();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterLevel = '';
        $this->filterClassId = '';
        $this->filterStatus = '';
        $this->resetPage();
        $this->persistExamFilters();
    }

    public function restoreExamFilters(array $filters): void
    {
        $saved = $this->sanitizeSavedExamFilters($filters);

        if (! in_array('search', $this->explicitFilterKeys, true) && array_key_exists('search', $saved)) {
            $this->search = $saved['search'];
        }

        if (! in_array('jenjang', $this->explicitFilterKeys, true) && array_key_exists('jenjang', $saved)) {
            $this->filterLevel = $saved['jenjang'];
        }

        if (! in_array('kelas', $this->explicitFilterKeys, true) && array_key_exists('kelas', $saved)) {
            $this->filterClassId = $saved['kelas'];
        }

        if (! in_array('status', $this->explicitFilterKeys, true) && array_key_exists('status', $saved)) {
            $this->filterStatus = $saved['status'];
        }

        if (! in_array('tahun', $this->explicitFilterKeys, true) && array_key_exists('tahun', $saved)) {
            $this->yearlyAcademicYearId = $saved['tahun'];
        }

        $this->resetPage();
    }

    public function openRequirements(string $level = 'TK'): void
    {
        $academicYear = AcademicYear::active();
        $schoolLevel = SchoolLevel::tryFrom($level);

        if (! $academicYear || ! $schoolLevel) {
            return;
        }

        $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => $schoolLevel]);

        $this->requirementLevel = $schoolLevel->value;
        $this->requirementConfigId = (int) $config->id;
        $this->selectedRequirements = [];
        $this->requirementPercentages = [];
        $this->pooledRequirements = [];
        $this->resetValidation();

        $requirements = $config->requirements()
            ->with('pooledPaymentTypes')
            ->get();
        $availablePaymentTypes = $this->availablePaymentTypes($academicYear, $schoolLevel, $requirements);

        foreach ($availablePaymentTypes as $frequency => $paymentTypes) {
            foreach ($paymentTypes as $paymentType) {
                $this->selectedRequirements[$frequency][$paymentType->id] = false;
                $this->requirementPercentages[$frequency][$paymentType->id] = '100';
            }

        }

        foreach ($requirements as $requirement) {
            if ($requirement->pooledPaymentTypes->count() > 1) {
                $frequency = $requirement->billing_frequency->value;
                $this->pooledRequirements[$frequency] = [
                    'requirement_id' => (int) $requirement->id,
                    'enabled' => (bool) $requirement->is_active,
                    'percentage' => (string) (float) $requirement->required_percentage,
                ];

                continue;
            }

            $frequency = $requirement->billing_frequency->value;
            $this->selectedRequirements[$frequency][$requirement->payment_type_id] = (bool) $requirement->is_active;
            $this->requirementPercentages[$frequency][$requirement->payment_type_id] = (string) (float) $requirement->required_percentage;
        }

        $monthlyRequirement = $requirements->firstWhere('billing_frequency', BillFrequency::Monthly);
        $this->monthlyStart = $monthlyRequirement?->start_month?->format('Y-m')
            ?? $academicYear->start_date->format('Y-m');
        $this->monthlyEnd = $monthlyRequirement?->end_month?->format('Y-m')
            ?? $academicYear->start_date->format('Y-m');
        $this->isRequirementModalOpen = true;
    }

    public function closeRequirementModal(): void
    {
        $this->isRequirementModalOpen = false;
        $this->requirementConfigId = null;
        $this->resetValidation();
    }

    public function saveRequirements(): void
    {
        $academicYear = AcademicYear::active();
        $config = $this->requirementConfigId === null
            ? null
            : StudentEligibilityConfig::query()->with('requirements.pooledPaymentTypes')->find($this->requirementConfigId);
        $schoolLevel = SchoolLevel::tryFrom($this->requirementLevel);

        if (! $academicYear || ! $config || ! $schoolLevel || $config->school_level !== $schoolLevel) {
            return;
        }

        $hasMonthlyRequirement = collect($this->selectedRequirements[BillFrequency::Monthly->value] ?? [])
            ->contains(fn (bool $isSelected): bool => $isSelected)
            || (bool) ($this->pooledRequirements[BillFrequency::Monthly->value]['enabled'] ?? false);
        $rules = [
            'requirementLevel' => ['required', Rule::enum(SchoolLevel::class)],
            'monthlyStart' => $hasMonthlyRequirement ? ['required', 'date_format:Y-m'] : ['nullable', 'date_format:Y-m'],
            'monthlyEnd' => $hasMonthlyRequirement ? ['required', 'date_format:Y-m', 'after_or_equal:monthlyStart'] : ['nullable', 'date_format:Y-m', 'after_or_equal:monthlyStart'],
            'selectedRequirements' => ['array'],
            'selectedRequirements.*' => ['array'],
            'selectedRequirements.*.*' => ['boolean'],
            'requirementPercentages' => ['array'],
            'requirementPercentages.*' => ['array'],
            'pooledRequirements' => ['array'],
            'pooledRequirements.*.requirement_id' => ['required', 'integer'],
            'pooledRequirements.*.enabled' => ['boolean'],
        ];

        foreach ($this->selectedRequirements as $frequency => $selections) {
            foreach (array_keys($selections) as $paymentTypeId) {
                $rules["requirementPercentages.{$frequency}.{$paymentTypeId}"] = ($selections[$paymentTypeId] ?? false)
                    ? ['required', 'numeric', 'gt:0', 'lte:100']
                    : ['nullable'];
            }
        }

        foreach ($this->pooledRequirements as $frequency => $pool) {
            $rules["pooledRequirements.{$frequency}.percentage"] = ['required', 'numeric', 'gt:0', 'lte:100'];
        }

        $validated = $this->validate($rules);

        $availableTypes = $this->availablePaymentTypes($academicYear, $schoolLevel, $config->requirements);
        $existingPooledRequirements = $config->requirements
            ->filter(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() > 1)
            ->keyBy('id');
        $pooledMemberIdsByFrequency = $existingPooledRequirements
            ->groupBy(fn (StudentExamRequirement $requirement): string => $requirement->billing_frequency->value)
            ->map(fn (Collection $requirements): Collection => $requirements
                ->flatMap(fn (StudentExamRequirement $requirement): Collection => $requirement->pooledPaymentTypes->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values());
        $selectedRequirements = collect($validated['selectedRequirements'])
            ->flatMap(function (array $selections, string $frequency) use ($availableTypes, $pooledMemberIdsByFrequency): Collection {
                $billFrequency = BillFrequency::tryFrom($frequency);

                if (! $billFrequency) {
                    return collect();
                }

                $availableIds = $availableTypes->get($frequency, collect())->pluck('id')->map(fn ($id): int => (int) $id);
                $pooledMemberIds = $pooledMemberIdsByFrequency->get($frequency, collect());

                return collect($selections)
                    ->filter()
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $availableIds->containsStrict($id))
                    ->reject(fn (int $id): bool => $pooledMemberIds->containsStrict($id))
                    ->map(fn (int $id): array => ['payment_type_id' => $id, 'frequency' => $billFrequency]);
            })
            ->values();

        DB::transaction(function () use ($config, $schoolLevel, $selectedRequirements, $existingPooledRequirements, $validated): void {
            $config->requirements()
                ->whereNotIn('id', $existingPooledRequirements->keys())
                ->delete();

            foreach ($selectedRequirements as $selectedRequirement) {
                $paymentTypeId = $selectedRequirement['payment_type_id'];
                $frequency = $selectedRequirement['frequency'];

                StudentExamRequirement::query()->create([
                    'student_exam_id' => null,
                    'student_eligibility_config_id' => $config->id,
                    'school_level' => $schoolLevel,
                    'payment_type_id' => $paymentTypeId,
                    'billing_frequency' => $frequency,
                    'start_month' => $frequency === BillFrequency::Monthly ? $validated['monthlyStart'].'-01' : null,
                    'end_month' => $frequency === BillFrequency::Monthly ? $validated['monthlyEnd'].'-01' : null,
                    'required_percentage' => $validated['requirementPercentages'][$frequency->value][$paymentTypeId],
                ]);
            }

            foreach ($validated['pooledRequirements'] as $frequency => $pool) {
                $requirement = $existingPooledRequirements->get((int) $pool['requirement_id']);

                if (! $requirement instanceof StudentExamRequirement || $requirement->billing_frequency->value !== $frequency) {
                    continue;
                }

                $values = [
                    'is_active' => (bool) $pool['enabled'],
                    'required_percentage' => $pool['percentage'],
                ];

                if ($requirement->billing_frequency === BillFrequency::Monthly && $pool['enabled']) {
                    $values['start_month'] = $validated['monthlyStart'].'-01';
                    $values['end_month'] = $validated['monthlyEnd'].'-01';
                }

                $requirement->update($values);
            }
        });

        $this->closeRequirementModal();
        session()->flash('success', "Kriteria jenjang {$schoolLevel->value} berhasil disimpan dan diterapkan.");
    }

    public function openResetConfirmation(string $level = 'TK'): void
    {
        $schoolLevel = SchoolLevel::tryFrom($level);

        if (! $schoolLevel) {
            return;
        }

        $this->resetLevel = $schoolLevel->value;
        $this->isResetConfirmOpen = true;
    }

    public function cancelReset(): void
    {
        $this->isResetConfirmOpen = false;
    }

    public function confirmReset(): void
    {
        $academicYear = AcademicYear::active();
        $schoolLevel = SchoolLevel::tryFrom($this->resetLevel);

        if (! $academicYear || ! $schoolLevel) {
            $this->isResetConfirmOpen = false;

            return;
        }

        $config = StudentEligibilityConfig::query()
            ->where('school_level', $schoolLevel)
            ->first();

        if (! $config) {
            $this->isResetConfirmOpen = false;

            return;
        }

        $config->resetToCanonicalDefaults();
        $this->isResetConfirmOpen = false;
        $this->openRequirements($schoolLevel->value);
        session()->flash('success', "Kriteria jenjang {$schoolLevel->value} berhasil dikembalikan ke pengaturan default.");
    }

    public function showDetail(int $studentId): void
    {
        $academicYear = $this->evaluationAcademicYear();
        $student = Student::query()->find($studentId);
        $requirements = $this->criteriaRequirements();

        if (! $academicYear || ! $student || $requirements->where('is_active', true)->isEmpty()) {
            return;
        }

        $this->detail = app(StudentExamEligibilityService::class)->evaluateCriteria($academicYear, $requirements, $student);
        $this->isDetailModalOpen = true;
    }

    public function closeDetailModal(): void
    {
        $this->isDetailModalOpen = false;
        $this->detail = [];
    }

    public function render(): View
    {
        $activeAcademicYear = AcademicYear::active();
        $academicYear = $this->evaluationAcademicYear();
        $configurations = StudentEligibilityConfig::query()
            ->with(['requirements.paymentType', 'requirements.pooledPaymentTypes'])
            ->get()
            ->keyBy(fn (StudentEligibilityConfig $config): string => $config->school_level->value);
        $criteriaRequirements = $configurations->flatMap(
            fn (StudentEligibilityConfig $config): Collection => $config->requirements
        )->values();
        $hasActiveCriteria = $criteriaRequirements->where('is_active', true)->isNotEmpty();
        $students = collect();
        $summaries = collect();
        $paginatedStudents = new LengthAwarePaginator([], 0, 15);
        $classes = collect();

        if ($academicYear) {
            $query = Student::query()
                ->whereHas('enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('academic_year_id', $academicYear->id)
                    ->where('status', 'active'))
                ->with(['enrollments' => fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('academic_year_id', $academicYear->id)
                    ->with('schoolClass')]);

            if ($this->search !== '') {
                $search = '%'.$this->search.'%';
                $query->where(function ($studentQuery) use ($search, $academicYear): void {
                    $studentQuery
                        ->where('nama_lengkap', 'like', $search)
                        ->orWhere('nis', 'like', $search)
                        ->orWhereHas('enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                            ->where('academic_year_id', $academicYear->id)
                            ->whereHas('schoolClass', fn ($classQuery) => $classQuery->where('name', 'like', $search)));
                });
            }

            if ($schoolLevel = SchoolLevel::tryFrom($this->filterLevel)) {
                $query->whereHas('enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('academic_year_id', $academicYear->id)
                    ->whereHas('schoolClass', fn ($classQuery) => $classQuery->whereIn('level', $schoolLevel->classLevels())));
            }

            if ($this->filterClassId !== '') {
                $query->whereHas('enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('academic_year_id', $academicYear->id)
                    ->where('school_class_id', $this->filterClassId));
            }

            $students = $query->orderBy('nama_lengkap')->get();

            if ($hasActiveCriteria) {
                $summaries = collect(app(StudentExamEligibilityService::class)->criteriaSummaries(
                    $academicYear,
                    $criteriaRequirements,
                    $students,
                ))->keyBy('student_id');
            }

            if ($hasActiveCriteria && $this->filterStatus !== '') {
                $students = $students->filter(function (Student $student) use ($summaries): bool {
                    $summary = $summaries->get($student->id);

                    return match ($this->filterStatus) {
                        'eligible' => (bool) ($summary['is_eligible'] ?? false),
                        'not_eligible' => ! ($summary['is_eligible'] ?? false),
                        default => true,
                    };
                })->values();
            }

            $paginatedStudents = new LengthAwarePaginator(
                $students->forPage($this->getPage(), 15)->values(),
                $students->count(),
                15,
                $this->getPage(),
                ['path' => request()->url(), 'query' => request()->query()],
            );
            $classes = SchoolClass::query()
                ->whereIn('id', StudentAcademicEnrollment::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('status', 'active')
                    ->select('school_class_id'))
                ->when($schoolLevel ?? null, fn ($classQuery, SchoolLevel $level) => $classQuery->whereIn('level', $level->classLevels()))
                ->orderBy('level')
                ->orderBy('name')
                ->get()
                ->toBase();
        }

        $summaryCounts = [
            'total' => $students->count(),
            'eligible' => $students->filter(fn (Student $student): bool => (bool) ($summaries->get($student->id)['is_eligible'] ?? false))->count(),
            'not_eligible' => $hasActiveCriteria
                ? $students->filter(fn (Student $student): bool => ! ($summaries->get($student->id)['is_eligible'] ?? false))->count()
                : 0,
        ];

        $requirementConfig = $this->isRequirementModalOpen
            ? $configurations->get($this->requirementLevel)
            : null;
        $requirementOptions = $activeAcademicYear && $requirementConfig instanceof StudentEligibilityConfig
            ? $this->availablePaymentTypes($activeAcademicYear, SchoolLevel::from($this->requirementLevel), $requirementConfig->requirements)
            : collect();
        $pooledRequirementGroups = $requirementConfig instanceof StudentEligibilityConfig
            ? $requirementConfig->requirements
                ->filter(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() > 1)
                ->keyBy(fn (StudentExamRequirement $requirement): string => $requirement->billing_frequency->value)
            : collect();
        $configuredCounts = collect(SchoolLevel::cases())->mapWithKeys(fn (SchoolLevel $level): array => [
            $level->value => $configurations->get($level->value)?->activeRequirementCount() ?? 0,
        ]);

        return view('livewire.student-exam-eligibility', [
            'academicYear' => $academicYear,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'students' => $paginatedStudents,
            'summaries' => $summaries,
            'summaryCounts' => $summaryCounts,
            'classes' => $classes,
            'levels' => SchoolLevel::cases(),
            'requirementOptions' => $requirementOptions,
            'pooledRequirementGroups' => $pooledRequirementGroups,
            'configuredCounts' => $configuredCounts,
            'hasActiveCriteria' => $hasActiveCriteria,
        ]);
    }

    /** @return Collection<int, StudentExamRequirement> */
    private function criteriaRequirements(): Collection
    {
        return StudentExamRequirement::query()
            ->whereNotNull('student_eligibility_config_id')
            ->with(['paymentType', 'pooledPaymentTypes'])
            ->get();
    }

    private function evaluationAcademicYear(): ?AcademicYear
    {
        $activeAcademicYear = AcademicYear::active();

        if (! $activeAcademicYear) {
            return null;
        }

        return AcademicYear::query()->find((int) $this->yearlyAcademicYearId) ?? $activeAcademicYear;
    }

    /** @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, PaymentType>> */
    private function availablePaymentTypes(AcademicYear $academicYear, SchoolLevel $schoolLevel, Collection $configuredRequirements): Collection
    {
        return collect(BillFrequency::cases())->mapWithKeys(function (BillFrequency $frequency) use ($academicYear, $configuredRequirements, $schoolLevel): array {
            $configuredTypeIds = $configuredRequirements
                ->filter(fn (StudentExamRequirement $requirement): bool => $requirement->billing_frequency === $frequency)
                ->flatMap(function (StudentExamRequirement $requirement): Collection|array {
                    return $requirement->pooledPaymentTypes->isNotEmpty()
                        ? $requirement->pooledPaymentTypes->pluck('id')
                        : [(int) $requirement->payment_type_id];
                })
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $types = PaymentType::query()
                ->where(function ($query) use ($academicYear, $configuredTypeIds, $schoolLevel, $frequency): void {
                    $query->whereIn('id', $configuredTypeIds)
                        ->orWhere(function ($availableQuery) use ($academicYear, $schoolLevel, $frequency): void {
                            $availableQuery->where('is_active', true)
                                ->whereHas('paymentTypeSchoolLevels', fn ($mappingQuery) => $mappingQuery
                                    ->where('school_level', $schoolLevel)
                                    ->where('is_active', true))
                                ->whereHas('rates', fn ($rateQuery) => $rateQuery
                                    ->whereIn('class_level', $schoolLevel->classLevels())
                                    ->where('billing_frequency', $frequency)
                                    ->whereDate('effective_from', '<=', $academicYear->end_date)
                                    ->where(fn ($dateQuery) => $dateQuery
                                        ->whereNull('effective_until')
                                        ->orWhereDate('effective_until', '>=', $academicYear->start_date)));
                        });
                })
                ->orderBy('name')
                ->get();

            return [$frequency->value => $types];
        });
    }

    private function persistExamFilters(): void
    {
        $this->dispatch('examFiltersPersist',
            search: $this->search,
            jenjang: $this->filterLevel,
            kelas: $this->filterClassId,
            status: $this->filterStatus,
            tahun: $this->yearlyAcademicYearId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeSavedExamFilters(array $filters): array
    {
        $saved = [];

        if (array_key_exists('search', $filters)) {
            $search = (string) $filters['search'];
            $saved['search'] = mb_substr(trim($search), 0, 255);
        }

        if (array_key_exists('jenjang', $filters)) {
            $levelValue = (string) $filters['jenjang'];

            if ($levelValue === '' || SchoolLevel::tryFrom($levelValue) !== null) {
                $saved['jenjang'] = $levelValue;
            }
        }

        if (array_key_exists('kelas', $filters)) {
            $classId = (string) $filters['kelas'];

            if ($classId === '') {
                $saved['kelas'] = '';
            } elseif ($this->classIdMatchesLevel($classId, $saved['jenjang'] ?? '')) {
                $saved['kelas'] = $classId;
            }
        }

        if (array_key_exists('status', $filters)) {
            $status = (string) $filters['status'];

            if (in_array($status, ['', 'eligible', 'not_eligible'], true)) {
                $saved['status'] = $status;
            }
        }

        if (array_key_exists('tahun', $filters)) {
            $yearId = (string) $filters['tahun'];

            if ($yearId === '' || AcademicYear::query()->find((int) $yearId) !== null) {
                $saved['tahun'] = $yearId;
            }
        }

        return $saved;
    }

    private function currentClassMatchesLevel(): bool
    {
        return $this->classIdMatchesLevel($this->filterClassId, $this->filterLevel);
    }

    private function classIdMatchesLevel(string $classId, string $levelValue): bool
    {
        $class = SchoolClass::query()->find((int) $classId);

        if ($class === null) {
            return false;
        }

        if ($levelValue === '') {
            return true;
        }

        $level = SchoolLevel::tryFrom($levelValue);

        return $level !== null && in_array($class->level, $level->classLevels(), true);
    }
}
