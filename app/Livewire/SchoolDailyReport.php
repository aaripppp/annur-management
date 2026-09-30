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

    #[Url(as: 'tahun_ajaran')]
    public string $monthlyAcademicYear = '';

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

    #[Url(as: 'target_kelas')]
    public ?string $targetSchoolClassId = null;

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

        $this->normalizeMonthlyPeriod();

        if (! in_array($this->targetMode, StudentTargetArrearsReportService::modes(), true)) {
            $this->targetMode = StudentTargetArrearsReportService::MODE_MONTHLY;
        }

        $this->normalizeTargetPeriod();

        if ($this->classRecapAcademicYearId === '') {
            $this->classRecapAcademicYearId = (string) (AcademicYear::active()?->id
                ?? AcademicYear::query()->orderByDesc('start_date')->value('id')
                ?? '');
        }

        $this->normalizeClassRecapSelection();

        if (! array_key_exists($this->schoolLevel, SchoolReportLevel::options())) {
            $this->schoolLevel = SchoolReportLevel::OPTION_ALL;
        }

        $this->normalizeTargetClassSelection();

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
        $this->normalizeMonthlyPeriod();
        $this->validateOnly('reportYear');
    }

    public function updatedMonthlyAcademicYear(): void
    {
        $this->normalizeMonthlyPeriod();
        $this->validateOnly('monthlyAcademicYear');
        $this->validateOnly('reportYear');
    }

    public function updatedReportYear(): void
    {
        $this->validateOnly('reportYear');
    }

    public function updatedTargetMonth(): void
    {
        $this->normalizeTargetPeriod();
        $this->validateOnly('targetMonth');
        $this->validateOnly('targetYear');
    }

    public function updatedTargetAcademicYear(): void
    {
        $this->normalizeTargetPeriod();
        $this->validateOnly('targetAcademicYear');
        $this->validateOnly('targetYear');
    }

    public function updatedSchoolLevel(): void
    {
        $this->validateOnly('schoolLevel');
        $this->normalizeTargetClassSelection();
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
                    $this->targetSchoolClassId === null ? null : (int) $this->targetSchoolClassId,
                ),
                'levelReport' => null,
                'monthOptions' => $this->monthOptions(),
                'targetMonthOptions' => $this->targetMonthOptions(),
                'targetClassOptions' => $this->targetClassOptions(),
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
                'monthlyMonthOptions' => $this->academicYearMonthOptions($this->monthlyAcademicYear),
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
            'monthlyAcademicYear' => ['nullable', 'string', 'max:9'],
            'monthlyMode' => ['required', 'string', Rule::in(self::monthlyModes())],
            'schoolLevel' => ['required', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
            'targetMode' => ['required', 'string', Rule::in(StudentTargetArrearsReportService::modes())],
            'targetMonth' => ['required', 'integer', 'between:1,12'],
            'targetYear' => ['required', 'integer', 'between:2000,2100'],
            'targetAcademicYear' => ['nullable', 'string', 'max:9'],
            'targetSchoolClassId' => ['nullable', 'integer', 'exists:school_classes,id'],
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

    private function normalizeMonthlyPeriod(): void
    {
        if ($this->reportMonth < 1 || $this->reportMonth > 12) {
            $this->reportMonth = (int) now()->format('n');
        }

        $this->monthlyAcademicYear = $this->resolveAcademicYear($this->monthlyAcademicYear);
        $this->reportYear = $this->yearForAcademicMonth($this->monthlyAcademicYear, $this->reportMonth);
    }

    private function normalizeTargetPeriod(): void
    {
        if ($this->targetMonth < 1 || $this->targetMonth > 12) {
            $this->targetMonth = (int) now()->format('n');
        }

        if ($this->targetAcademicYear === '') {
            $this->targetAcademicYear = $this->resolveAcademicYear('');
        }

        $this->targetYear = $this->yearForAcademicMonth($this->targetAcademicYear, $this->targetMonth);
    }

    private function resolveAcademicYear(string $candidate): string
    {
        if ($candidate !== '' && AcademicYear::query()->where('year', $candidate)->exists()) {
            return $candidate;
        }

        $activeAcademicYear = AcademicYear::active();

        return $activeAcademicYear !== null
            ? $activeAcademicYear->year
            : (string) (AcademicYear::query()->orderByDesc('start_date')->value('year') ?? '');
    }

    private function yearForAcademicMonth(string $academicYear, int $month): int
    {
        if ($academicYear === '') {
            return (int) now()->format('Y');
        }

        $startYear = (int) explode('/', $academicYear, 2)[0];

        return $month >= 7 ? $startYear : $startYear + 1;
    }

    /** @return list<array{value: int, label: string}> */
    private function academicYearMonthOptions(string $academicYear): array
    {
        if ($academicYear === '') {
            return [];
        }

        $months = array_merge(range(7, 12), range(1, 6));

        return array_map(fn (int $month): array => [
            'value' => $month,
            'label' => CarbonImmutable::create($this->yearForAcademicMonth($academicYear, $month), $month, 1)
                ->settings(['locale' => 'id'])
                ->translatedFormat('F Y'),
        ], $months);
    }

    /** @return list<array{value: int, label: string}> */
    private function targetMonthOptions(): array
    {
        return $this->academicYearMonthOptions($this->targetAcademicYear);
    }

    /**
     * Opsi kelas untuk tab Target & Tunggakan, terbatas pada jenjang terpilih.
     *
     * Satu query terkunci di SQL lewat level kelas, bukan memuat semua kelas
     * lalu menyaringnya di PHP. Jenjang "Semua Jenjang" tidak punya daftar kelas
     * yang masuk akal karena kelas selalu berjenjang, jadi opsi dikosongkan dan
     * state kelas dinormalisasi menjadi null.
     *
     * @return list<array{value: int, label: string}>
     */
    private function targetClassOptions(): array
    {
        $schoolLevel = SchoolReportLevel::fromValue($this->schoolLevel);

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

    /**
     * Jaga konsistensi filter kelas dengan jenjang terpilih.
     *
     * Rule exists hanya memastikan kelas ada, bukan bahwa kelas itu milik
     * jenjang yang sedang dipilih, sehingga kelas dari jenjang lain (mis. dari
     * URL lama) harus dinormalisasi. Mengikuti normalizeClassRecapSelection()
     * untuk tab Rekap Per Kelas. Changing bulan atau tahun ajaran tidak
     * menyentuh state kelas karena daftar kelas hanya bergantung pada jenjang.
     */
    private function normalizeTargetClassSelection(): void
    {
        $schoolLevel = SchoolReportLevel::fromValue($this->schoolLevel);

        if ($schoolLevel === null || $this->targetSchoolClassId === null || $this->targetSchoolClassId === '') {
            $this->targetSchoolClassId = null;

            return;
        }

        $classIsValid = SchoolClass::query()
            ->whereKey((int) $this->targetSchoolClassId)
            ->whereIn('level', $schoolLevel->classLevels())
            ->exists();

        if (! $classIsValid) {
            $this->targetSchoolClassId = null;
        }
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
