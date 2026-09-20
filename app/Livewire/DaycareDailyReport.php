<?php

namespace App\Livewire;

use App\Services\DaycareDailyReportService;
use App\Services\DaycareMonthlyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Laporan Daycare - Annur Management')]
class DaycareDailyReport extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'daily';

    #[Url(as: 'start_date')]
    public string $reportStartDate = '';

    #[Url(as: 'end_date')]
    public string $reportEndDate = '';

    public string $reportDate = '';

    public string $appliedStartDate = '';

    public string $appliedEndDate = '';

    #[Url(as: 'bulan')]
    public int $reportMonth = 0;

    #[Url(as: 'tahun')]
    public int $reportYear = 0;

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['daily', 'monthly'], true)) {
            $this->activeTab = 'daily';
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

        if ($this->reportMonth < 1 || $this->reportMonth > 12) {
            $this->reportMonth = (int) now()->format('n');
        }

        if ($this->reportYear < 2000 || $this->reportYear > 2100) {
            $this->reportYear = (int) now()->format('Y');
        }

        $this->validate();
        $this->appliedStartDate = $this->reportStartDate;
        $this->appliedEndDate = $this->reportEndDate;
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['daily', 'monthly'], true)) {
            $this->activeTab = $tab;
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

    public function render(
        DaycareDailyReportService $dailyReportService,
        DaycareMonthlyReportService $monthlyReportService,
    ): View {
        return view('livewire.daycare-daily-report', [
            'report' => $this->activeTab === 'daily'
                ? $dailyReportService->generate($this->appliedStartDate, $this->appliedEndDate)
                : null,
            'monthlyReport' => $this->activeTab === 'monthly'
                ? $monthlyReportService->generate($this->reportYear, $this->reportMonth)
                : null,
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'reportStartDate' => ['required', 'date_format:Y-m-d'],
            'reportEndDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:reportStartDate'],
            'reportMonth' => ['required', 'integer', 'between:1,12'],
            'reportYear' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'reportStartDate' => 'Tanggal Mulai',
            'reportEndDate' => 'Tanggal Selesai',
            'reportMonth' => 'Bulan',
            'reportYear' => 'Tahun',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'reportEndDate.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ];
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

    /** @return list<int> */
    private function yearOptions(): array
    {
        $current = (int) now()->format('Y');

        return [$current - 1, $current, $current + 1];
    }
}
