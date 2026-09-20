<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\SchoolClass;
use App\Services\ReportYearOptionsService;
use App\Services\SchoolBankRecapService;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Services\SchoolMonthlyReportService;
use App\Services\StudentClassPaymentRecapService;
use App\Services\StudentTargetArrearsReportService;
use App\Support\SchoolReportLevel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Laporan - Annur Management')]
class SchoolDailyReport extends Component
{
    public const MONTHLY_MODE_BY_DATE = 'by_date';

    public const MONTHLY_MODE_BY_LEVEL = 'by_level';

    public const MONTHLY_MODE_ALL_UNITS = 'all_units';

    #[Url(as: 'tab')]
    public string $activeTab = 'daily';

    #[Url(as: 'start_date')]
    public string $reportStartDate = '';

    #[Url(as: 'end_date')]
    public string $reportEndDate = '';

    public string $reportDate = '';

    public string $appliedStartDate = '';

    public string $appliedEndDate = '';

    #[Url(as: 'bank_start_date')]
    public string $bankStartDate = '';

    #[Url(as: 'bank_end_date')]
    public string $bankEndDate = '';

    #[Url(as: 'bank')]
    public string $bankFilter = 'all';

    public string $appliedBankStartDate = '';

    public string $appliedBankEndDate = '';

    #[Url(as: 'bulan')]
    public int $reportMonth = 0;

    #[Url(as: 'tahun')]
    public int $reportYear = 0;

    #[Url(as: 'monthly_mode')]
    public string $monthlyMode = self::MONTHLY_MODE_BY_DATE;

    #[Url(as: 'jenjang')]
    public string $schoolLevel = SchoolReportLevel::OPTION_ALL;

    #[Url(as: 'target_mode')]
    public string $targetMode = StudentTargetArrearsReportService::MODE_MONTHLY;

    #[Url(as: 'target_month')]
    public int $targetMonth = 0;

    #[Url(as: 'target_year')]
    public int $targetYear = 0;

    #[Url(as: 'target_academic_year')]
    public string $targetAcademicYear = '';

    #[Url(as: 'rekap_tahun_ajaran')]
    public string $classRecapAcademicYearId = '';

    #[Url(as: 'rekap_jenjang')]
    public string $classRecapSchoolLevel = '';

    #[Url(as: 'rekap_kelas')]
    public string $classRecapSchoolClassId = '';

    public function mount(): void
    {
        if ($this->activeTab === 'level') {
            $this->activeTab = 'monthly';
            $this->monthlyMode = self::MONTHLY_MODE_BY_LEVEL;
        }

        if (! in_array($this->activeTab, ['daily', 'monthly', 'bank', 'target', 'class'], true)) {
            $this->activeTab = 'daily';
        }

        if (! in_array($this->monthlyMode, self::monthlyModes(), true)) {
            $this->monthlyMode = self::MONTHLY_MODE_BY_DATE;
        }

        if ($this->reportStartDate === '') {
            $this->reportStartDate = $this->reportDate !== ''
                ? $this->reportDate
                : now()->toDateString();
        }

        if ($this->reportEndDate === '') {
            $this->reportEndDate = $this->reportDate !== ''
                ? $this->reportDate
                : $this->reportStartDate;
        }

        $this->bankStartDate = $this->bankStartDate !== '' ? $this->bankStartDate : now()->toDateString();
        $this->bankEndDate = $this->bankEndDate !== '' ? $this->bankEndDate : $this->bankStartDate;

        if ($this->reportMonth < 1 || $this->reportMonth > 12) {
            $this->reportMonth = (int) now()->format('n');
        }

        if ($this->reportYear < 2000 || $this->reportYear > 2100) {
            $this->reportYear = (int) now()->format('Y');
        }

        if ($this->targetMonth < 1 || $this->targetMonth > 12) {
            $this->targetMonth = (int) now()->format('n');
        }

        if ($this->targetYear < 2000 || $this->targetYear > 2100) {
            $this->targetYear = (int) now()->format('Y');
        }

        if (! in_array($this->targetMode, StudentTargetArrearsReportService::modes(), true)) {
            $this->targetMode = StudentTargetArrearsReportService::MODE_MONTHLY;
        }

        if ($this->targetAcademicYear === '') {
            $activeAcademicYear = AcademicYear::active();
            $this->targetAcademicYear = $activeAcademicYear !== null
                ? $activeAcademicYear->year
                : (string) (AcademicYear::query()->orderByDesc('start_date')->value('year') ?? '');
        }

        if ($this->classRecapAcademicYearId === '') {
            $this->classRecapAcademicYearId = (string) (AcademicYear::active()?->id
                ?? AcademicYear::query()->orderByDesc('start_date')->value('id')
                ?? '');
        }

        $this->normalizeClassRecapSelection();

        if (! array_key_exists($this->schoolLevel, SchoolReportLevel::options())) {
            $this->schoolLevel = SchoolReportLevel::OPTION_ALL;
        }

        if (! $this->bankFilterIsValid()) {
            $this->bankFilter = 'all';
        }

        $this->validate();
        $this->appliedStartDate = $this->reportStartDate;
        $this->appliedEndDate = $this->reportEndDate;
        $this->appliedBankStartDate = $this->bankStartDate;
        $this->appliedBankEndDate = $this->bankEndDate;
    }

    public function setActiveTab(string $tab): void
    {
        if ($tab === 'level') {
            $this->activeTab = 'monthly';
            $this->monthlyMode = self::MONTHLY_MODE_BY_LEVEL;

            return;
        }

        if (in_array($tab, ['daily', 'monthly', 'bank', 'target', 'class'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function setMonthlyMode(string $mode): void
    {
        if (in_array($mode, self::monthlyModes(), true)) {
            $this->monthlyMode = $mode;
        }
    }

    public function setTargetMode(string $mode): void
    {
        if (in_array($mode, StudentTargetArrearsReportService::modes(), true)) {
            $this->targetMode = $mode;
        }
    }

    public function updatedBankStartDate(): void
    {
        $this->validateOnly('bankStartDate');
        $this->validateOnly('bankEndDate');

        $this->appliedBankStartDate = $this->bankStartDate;
        $this->appliedBankEndDate = $this->bankEndDate;
    }

    public function updatedBankEndDate(): void
    {
        $this->validateOnly('bankStartDate');
        $this->validateOnly('bankEndDate');

        $this->appliedBankStartDate = $this->bankStartDate;
        $this->appliedBankEndDate = $this->bankEndDate;
    }

    public function updatedBankFilter(string $value): void
    {
        if (! $this->bankFilterIsValid()) {
            $this->bankFilter = 'all';
        }
    }

    public function updatedReportStartDate(): void
    {
        $this->validateOnly('reportStartDate');
        $this->validateOnly('reportEndDate');

        $this->appliedStartDate = $this->reportStartDate;
        $this->appliedEndDate = $this->reportEndDate;
    }

    public function updatedReportEndDate(): void
    {
        $this->validateOnly('reportStartDate');
        $this->validateOnly('reportEndDate');

        $this->appliedStartDate = $this->reportStartDate;
        $this->appliedEndDate = $this->reportEndDate;
    }

    public function updatedReportMonth(): void
    {
        $this->validateOnly('reportMonth');
    }

    public function updatedReportYear(): void
    {
        $this->validateOnly('reportYear');
    }

    public function updatedSchoolLevel(): void
    {
        $this->validateOnly('schoolLevel');
    }

    public function updatedClassRecapSchoolLevel(): void
    {
        $this->normalizeClassRecapSelection();
    }

    public function updatedClassRecapSchoolClassId(): void
    {
        $this->normalizeClassRecapSelection();
    }

    public function render(
        ReportYearOptionsService $reportYearService,
        SchoolDailyReportService $dailyService,
        SchoolMonthlyReportService $monthlyService,
        SchoolMonthlyByLevelReportService $monthlyByLevelService,
        SchoolMonthlyAllUnitsReportService $monthlyAllUnitsService,
        SchoolBankRecapService $bankRecapService,
        StudentTargetArrearsReportService $targetArrearsService,
        StudentClassPaymentRecapService $classRecapService,
    ): View {
        $schoolLevel = SchoolReportLevel::fromValue($this->schoolLevel);

        if ($this->activeTab === 'class') {
            $classRecapReport = null;
            $classRecapLevel = SchoolLevel::tryFrom($this->classRecapSchoolLevel);

            if ($classRecapLevel !== null && $this->classRecapContextIsValid($classRecapLevel)) {
                $classRecapReport = $classRecapService->generate(
                    (int) $this->classRecapAcademicYearId,
                    $classRecapLevel,
                    (int) $this->classRecapSchoolClassId,
                );
            }

            return view('livewire.school-daily-report', [
                'report' => null,
                'monthlyReport' => null,
                'allUnitsReport' => null,
                'bankReport' => null,
                'targetReport' => null,
                'levelReport' => null,
                'classRecapReport' => $classRecapReport,
                'classRecapAcademicYearOptions' => $this->classRecapAcademicYearOptions(),
                'classRecapLevelOptions' => $this->classRecapLevelOptions(),
                'classRecapClassOptions' => $this->classRecapClassOptions(),
            ]);
        }

        if ($this->activeTab === 'target') {
            return view('livewire.school-daily-report', [
                'report' => null,
                'monthlyReport' => null,
                'allUnitsReport' => null,
                'bankReport' => null,
                'targetReport' => $targetArrearsService->generate(
                    $this->targetMode,
                    $this->targetMonth,
                    $this->targetYear,
                    $this->targetAcademicYear,
                    $schoolLevel,
                ),
                'levelReport' => null,
                'monthOptions' => $this->monthOptions(),
                'yearOptions' => $reportYearService->options(),
                'academicYearOptions' => $this->academicYearOptions(),
                'levelOptions' => $this->levelOptions(),
            ]);
        }

        if ($this->activeTab === 'monthly') {
            $monthlyReport = null;
            $levelReport = null;
            $allUnitsReport = null;

            if ($this->monthlyMode === self::MONTHLY_MODE_BY_LEVEL) {
                $levelReport = $monthlyByLevelService->generate($this->reportYear, $this->reportMonth);
            } elseif ($this->monthlyMode === self::MONTHLY_MODE_ALL_UNITS) {
                $allUnitsReport = $monthlyAllUnitsService->generate($this->reportYear, $this->reportMonth);
            } else {
                $monthlyReport = $monthlyService->generate($this->reportYear, $this->reportMonth, $schoolLevel);
            }

            return view('livewire.school-daily-report', [
                'report' => null,
                'monthlyReport' => $monthlyReport,
                'levelReport' => $levelReport,
                'allUnitsReport' => $allUnitsReport,
                'bankReport' => null,
                'targetReport' => null,
                'monthOptions' => $this->monthOptions(),
                'yearOptions' => $reportYearService->options(),
                'academicYearOptions' => $this->academicYearOptions(),
                'levelOptions' => $this->levelOptions(),
            ]);
        }

        if ($this->activeTab === 'bank') {
            return view('livewire.school-daily-report', [
                'report' => null,
                'monthlyReport' => null,
                'allUnitsReport' => null,
                'levelReport' => null,
                'bankReport' => $bankRecapService->generate(
                    $this->appliedBankStartDate,
                    $this->appliedBankEndDate,
                    $this->bankFilter,
                ),
                'targetReport' => null,
                'bankFilterOptions' => Bank::query()
                    ->where('is_active', true)
                    ->where('type', Bank::TYPE_BANK)
                    ->orderBy('name')
                    ->get(),
                'monthOptions' => $this->monthOptions(),
                'yearOptions' => $reportYearService->options(),
                'academicYearOptions' => $this->academicYearOptions(),
                'levelOptions' => $this->levelOptions(),
            ]);
        }

        return view('livewire.school-daily-report', [
            'report' => $dailyService->generate($this->appliedStartDate, $this->appliedEndDate, $schoolLevel),
            'monthlyReport' => null,
            'allUnitsReport' => null,
            'levelReport' => null,
            'bankReport' => null,
            'targetReport' => null,
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $reportYearService->options(),
            'academicYearOptions' => $this->academicYearOptions(),
            'levelOptions' => $this->levelOptions(),
        ]);
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'reportStartDate' => ['required', 'date_format:Y-m-d'],
            'reportEndDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:reportStartDate'],
            'bankStartDate' => ['required', 'date_format:Y-m-d'],
            'bankEndDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:bankStartDate'],
            'bankFilter' => ['required', 'string', 'max:20'],
            'reportMonth' => ['required', 'integer', 'between:1,12'],
            'reportYear' => ['required', 'integer', 'between:2000,2100'],
            'monthlyMode' => ['required', 'string', Rule::in(self::monthlyModes())],
            'schoolLevel' => ['required', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
            'targetMode' => ['required', 'string', Rule::in(StudentTargetArrearsReportService::modes())],
            'targetMonth' => ['required', 'integer', 'between:1,12'],
            'targetYear' => ['required', 'integer', 'between:2000,2100'],
            'targetAcademicYear' => ['nullable', 'string', 'max:9'],
            'classRecapAcademicYearId' => ['nullable', 'integer', 'exists:academic_years,id'],
            'classRecapSchoolLevel' => ['nullable', 'string', Rule::in(array_column($this->classRecapLevelOptions(), 'value'))],
            'classRecapSchoolClassId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'reportEndDate.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'bankEndDate.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'reportStartDate' => 'Tanggal Mulai',
            'reportEndDate' => 'Tanggal Selesai',
            'bankStartDate' => 'Tanggal Mulai',
            'bankEndDate' => 'Tanggal Selesai',
            'bankFilter' => 'Bank / Channel',
        ];
    }

    private function bankFilterIsValid(): bool
    {
        if (in_array($this->bankFilter, ['all', 'cash'], true)) {
            return true;
        }

        return is_numeric($this->bankFilter)
            && Bank::query()->whereKey((int) $this->bankFilter)->exists();
    }

    /** @return list<array{value: int, label: string}> */
    private function monthOptions(): array
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[] = [
                'value' => $month,
                'label' => CarbonImmutable::create(2000, $month, 1)
                    ->settings(['locale' => 'id'])
                    ->translatedFormat('F'),
            ];
        }

        return $months;
    }

    /** @return list<string> */
    private function academicYearOptions(): array
    {
        return array_values(AcademicYear::query()
            ->orderByDesc('start_date')
            ->pluck('year')
            ->map(fn ($year): string => (string) $year)
            ->values()
            ->all());
    }

    /** @return list<array{value: string, label: string}> */
    private function levelOptions(): array
    {
        $options = [];

        foreach (SchoolReportLevel::options() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /** @return list<array{value: int, label: string}> */
    private function classRecapAcademicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'year'])
            ->map(fn (AcademicYear $academicYear): array => [
                'value' => $academicYear->id,
                'label' => $academicYear->year,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function classRecapLevelOptions(): array
    {
        return array_map(
            fn (SchoolLevel $level): array => ['value' => $level->value, 'label' => $level->value],
            SchoolLevel::cases(),
        );
    }

    /** @return list<array{value: int, label: string}> */
    private function classRecapClassOptions(): array
    {
        $schoolLevel = SchoolLevel::tryFrom($this->classRecapSchoolLevel);

        if ($schoolLevel === null) {
            return [];
        }

        return SchoolClass::query()
            ->whereIn('level', $schoolLevel->classLevels())
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $schoolClass): array => [
                'value' => $schoolClass->id,
                'label' => $schoolClass->name,
            ])
            ->values()
            ->all();
    }

    private function normalizeClassRecapSelection(): void
    {
        $schoolLevel = SchoolLevel::tryFrom($this->classRecapSchoolLevel);

        if ($schoolLevel === null) {
            $this->classRecapSchoolLevel = '';
            $this->classRecapSchoolClassId = '';

            return;
        }

        if ($this->classRecapSchoolClassId === '') {
            return;
        }

        $classIsValid = SchoolClass::query()
            ->whereKey((int) $this->classRecapSchoolClassId)
            ->whereIn('level', $schoolLevel->classLevels())
            ->exists();

        if (! $classIsValid) {
            $this->classRecapSchoolClassId = '';
        }
    }

    private function classRecapContextIsValid(SchoolLevel $schoolLevel): bool
    {
        if ($this->classRecapAcademicYearId === '' || $this->classRecapSchoolClassId === '') {
            return false;
        }

        return AcademicYear::query()->whereKey((int) $this->classRecapAcademicYearId)->exists()
            && SchoolClass::query()
                ->whereKey((int) $this->classRecapSchoolClassId)
                ->whereIn('level', $schoolLevel->classLevels())
                ->exists();
    }

    /** @return list<string> */
    private static function monthlyModes(): array
    {
        return [
            self::MONTHLY_MODE_BY_DATE,
            self::MONTHLY_MODE_BY_LEVEL,
            self::MONTHLY_MODE_ALL_UNITS,
        ];
    }
}
