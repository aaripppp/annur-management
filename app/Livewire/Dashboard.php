<?php

namespace App\Livewire;

use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use App\Services\DashboardOperationalMetricsService;
use App\Services\ReportYearOptionsService;
use App\Services\StudentTargetArrearsReportService;
use App\Support\RecentTransaction;
use App\Support\SchoolReportCategory;
use App\Support\SchoolReportLevel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public const PERIOD_TODAY = 'today';

    public const PERIOD_ALL = 'all';

    public const PERIOD_YESTERDAY = 'yesterday';

    public const PERIOD_CURRENT_MONTH = 'current_month';

    public const PERIOD_CUSTOM = 'custom';

    #[Url(as: 'unit')]
    public string $operationalUnit = DashboardOperationalMetricsService::UNIT_ALL;

    #[Url(as: 'periode')]
    public string $operationalPeriod = self::PERIOD_ALL;

    #[Url(as: 'dari')]
    public string $operationalStartDate = '';

    #[Url(as: 'sampai')]
    public string $operationalEndDate = '';

    public string $appliedOperationalStartDate = '';

    public string $appliedOperationalEndDate = '';

    #[Url(as: 'target_bulan')]
    public int $targetMonth = 0;

    #[Url(as: 'target_tahun')]
    public int $targetYear = 0;

    #[Url(as: 'target_jenjang')]
    public string $targetJenjang = SchoolReportLevel::OPTION_ALL;

    public function mount(): void
    {
        $today = $this->now()->toDateString();

        if (! array_key_exists($this->operationalUnit, DashboardOperationalMetricsService::unitOptions())) {
            $this->operationalUnit = DashboardOperationalMetricsService::UNIT_ALL;
        }

        if (! array_key_exists($this->operationalPeriod, self::periodOptions())) {
            $this->operationalPeriod = self::PERIOD_ALL;
        }

        if (! $this->isDate($this->operationalStartDate)) {
            $this->operationalStartDate = $today;
        }

        if (! $this->isDate($this->operationalEndDate)) {
            $this->operationalEndDate = $this->operationalStartDate;
        }

        if ($this->operationalEndDate < $this->operationalStartDate) {
            $this->operationalEndDate = $this->operationalStartDate;
        }

        $this->appliedOperationalStartDate = $this->operationalStartDate;
        $this->appliedOperationalEndDate = $this->operationalEndDate;

        if ($this->targetMonth < 1 || $this->targetMonth > 12) {
            $this->targetMonth = (int) $this->now()->format('n');
        }

        if ($this->targetYear < 2000 || $this->targetYear > 2100) {
            $this->targetYear = (int) $this->now()->format('Y');
        }

        if (! array_key_exists($this->targetJenjang, SchoolReportLevel::options())) {
            $this->targetJenjang = SchoolReportLevel::OPTION_ALL;
        }
    }

    public function updatedOperationalUnit(): void
    {
        if (! array_key_exists($this->operationalUnit, DashboardOperationalMetricsService::unitOptions())) {
            $this->operationalUnit = DashboardOperationalMetricsService::UNIT_ALL;
        }
    }

    public function updatedOperationalPeriod(): void
    {
        if (! array_key_exists($this->operationalPeriod, self::periodOptions())) {
            $this->operationalPeriod = self::PERIOD_ALL;
        }

        if ($this->operationalPeriod === self::PERIOD_CUSTOM) {
            $this->applyCustomDateRange();
        }
    }

    public function updatedOperationalStartDate(): void
    {
        $this->applyCustomDateRange();
    }

    public function updatedOperationalEndDate(): void
    {
        $this->applyCustomDateRange();
    }

    public function render(
        DashboardOperationalMetricsService $operationalMetricsService,
        StudentTargetArrearsReportService $targetArrearsService,
        ReportYearOptionsService $reportYearService,
    ): View {
        [$operationalStart, $operationalEnd] = $this->operationalDateRange();
        $operationalMetrics = $operationalMetricsService->generate(
            $this->operationalUnit,
            $operationalStart,
            $operationalEnd,
        );
        $currentMonth = $this->now();
        $targetArrearsSummary = $targetArrearsService->generateMonthlySummary(
            $this->targetMonth,
            $this->targetYear,
            SchoolReportLevel::fromValue($this->targetJenjang),
        );

        $recentStudentPayments = Payment::with([
            'student' => fn ($query) => $query->with('schoolClass'),
            'bank',
            'user',
            'details.bill',
        ])
            ->latest('created_at')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (Payment $payment): RecentTransaction => new RecentTransaction(
                type: 'student',
                sourceId: $payment->id,
                receiptNumber: $payment->receipt_number,
                name: $payment->student->nama_lengkap,
                secondaryInfo: 'Kelas '.$payment->student->schoolClass->name,
                description: $payment->detail_display,
                bank: $payment->bank->paymentLabel(),
                bankAccountNumber: $payment->bank->displayAccountNumber(),
                amount: (float) $payment->total_amount,
                paymentDate: $payment->payment_date,
                createdAt: $payment->created_at ?? $payment->payment_date->copy()->startOfDay(),
                detailUrl: route('pembayaran.show', $payment->id),
                status: $payment->status_label,
                creator: $payment->user->name,
                isManual: $payment->isManualPayment(),
            ));

        $recentDaycarePayments = DaycarePayment::query()
            ->with(['child', 'bank', 'creator', 'details'])
            ->latest('created_at')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (DaycarePayment $payment): RecentTransaction => new RecentTransaction(
                type: 'daycare',
                sourceId: $payment->id,
                receiptNumber: $payment->receipt_number,
                name: $payment->child->nama_lengkap,
                secondaryInfo: 'Kelas '.$payment->child->kelas,
                description: $this->daycareDescription($payment),
                bank: $payment->bank->paymentLabel(),
                bankAccountNumber: $payment->bank->displayAccountNumber(),
                amount: (float) $payment->total_amount,
                paymentDate: $payment->payment_date,
                createdAt: $payment->created_at ?? $payment->payment_date->copy()->startOfDay(),
                detailUrl: route('daycare.payment.show', $payment),
                status: 'Tercatat',
                creator: $payment->created_by !== null ? $payment->creator->name : 'Administrator',
            ));

        $recentProspectivePayments = ProspectiveStudentPayment::query()
            ->with([
                'prospectiveStudent.schoolClass',
                'bank',
                'creator',
                'details' => fn ($query) => $query->with(['paymentType', 'bill.paymentType']),
            ])
            ->latest('created_at')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (ProspectiveStudentPayment $payment): RecentTransaction => new RecentTransaction(
                type: 'prospective',
                sourceId: $payment->id,
                receiptNumber: $payment->receipt_number,
                name: $payment->prospectiveStudent->nama_lengkap,
                secondaryInfo: 'Kelas '.$payment->prospectiveStudent->schoolClass->name,
                description: $this->prospectiveDescription($payment),
                bank: $payment->bank->paymentLabel(),
                bankAccountNumber: $payment->bank->displayAccountNumber(),
                amount: (float) $payment->total_amount,
                paymentDate: $payment->payment_date,
                createdAt: $payment->created_at ?? $payment->payment_date->copy()->startOfDay(),
                detailUrl: route('pembayaran.prospective.show', $payment),
                status: $payment->status_label,
                creator: $payment->created_by !== null ? $payment->creator->name : 'Administrator',
            ));

        $recentTransactions = $recentStudentPayments
            ->concat($recentDaycarePayments)
            ->concat($recentProspectivePayments)
            ->sort(function (RecentTransaction $first, RecentTransaction $second): int {
                $createdComparison = strcmp(
                    $second->createdAt->format('Y-m-d H:i:s.u'),
                    $first->createdAt->format('Y-m-d H:i:s.u')
                );

                if ($createdComparison !== 0) {
                    return $createdComparison;
                }

                $typeComparison = strcmp($first->type, $second->type);

                return $typeComparison !== 0
                    ? $typeComparison
                    : $second->sourceId <=> $first->sourceId;
            })
            ->take(10)
            ->values();

        return view('livewire.dashboard.index', [
            'totalPemasukan' => $operationalMetrics['total_income'],
            'totalTransaksi' => $operationalMetrics['transaction_count'],
            'totalSiswaAktif' => $operationalMetrics['active_students'],
            'activeDaycareChildren' => $operationalMetrics['active_daycare_children'],
            'banks' => $operationalMetrics['banks'],
            'bankTotals' => $operationalMetrics['bank_totals'],
            'recentTransactions' => $recentTransactions,
            'targetArrearsSummary' => $targetArrearsSummary,
            'targetCardTitle' => $this->targetCardTitle($targetArrearsSummary['period_label']),
            'targetArrearsUrl' => route('laporan.index', [
                'tab' => 'target',
                'target_mode' => StudentTargetArrearsReportService::MODE_MONTHLY,
                'target_month' => $this->targetMonth,
                'target_year' => $this->targetYear,
                'jenjang' => $this->targetJenjang,
            ]),
            'operationalUnitOptions' => DashboardOperationalMetricsService::unitOptions(),
            'operationalPeriodOptions' => self::periodOptions(),
            'targetMonthOptions' => $this->monthOptions(),
            'targetYearOptions' => $reportYearService->options(),
            'targetLevelOptions' => SchoolReportLevel::options(),
        ]);
    }

    /** @return array<string, string> */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_ALL => 'Semua Waktu',
            self::PERIOD_TODAY => 'Hari Ini',
            self::PERIOD_YESTERDAY => 'Kemarin',
            self::PERIOD_CURRENT_MONTH => 'Bulan Ini',
            self::PERIOD_CUSTOM => 'Custom',
        ];
    }

    private function applyCustomDateRange(): void
    {
        $validated = $this->validate([
            'operationalStartDate' => ['required', 'date_format:Y-m-d'],
            'operationalEndDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:operationalStartDate'],
        ], [
            'operationalEndDate.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.',
        ], [
            'operationalStartDate' => 'Dari Tanggal',
            'operationalEndDate' => 'Sampai Tanggal',
        ]);

        $this->appliedOperationalStartDate = $validated['operationalStartDate'];
        $this->appliedOperationalEndDate = $validated['operationalEndDate'];
    }

    /** @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null} */
    private function operationalDateRange(): array
    {
        $today = $this->now();

        return match ($this->operationalPeriod) {
            self::PERIOD_ALL => [null, null],
            self::PERIOD_YESTERDAY => [$today->subDay()->startOfDay(), $today->subDay()->endOfDay()],
            self::PERIOD_CURRENT_MONTH => [$today->startOfMonth(), $today->endOfMonth()],
            self::PERIOD_CUSTOM => [
                CarbonImmutable::parse($this->appliedOperationalStartDate, $this->timezone())->startOfDay(),
                CarbonImmutable::parse($this->appliedOperationalEndDate, $this->timezone())->endOfDay(),
            ],
            default => [$today->startOfDay(), $today->endOfDay()],
        };
    }

    private function isDate(string $value): bool
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, $this->timezone())->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function targetCardTitle(string $periodLabel): string
    {
        $current = $this->now();

        $isCurrentPeriod = $this->targetMonth === (int) $current->format('n')
            && $this->targetYear === (int) $current->format('Y');

        return $isCurrentPeriod
            ? 'Capaian Tagihan Bulan Ini'
            : 'Capaian Tagihan '.$periodLabel;
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

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    private function daycareDescription(DaycarePayment $payment): string
    {
        /** @var Collection<int, string> $descriptions */
        $descriptions = $payment->details
            ->pluck('description')
            ->map(fn (mixed $description): string => trim((string) $description))
            ->filter()
            ->values();

        if ($descriptions->isEmpty()) {
            return '-';
        }

        if ($descriptions->count() <= 2) {
            return $descriptions->implode(' + ');
        }

        return $descriptions->first().' + '.($descriptions->count() - 1).' lainnya';
    }

    private function prospectiveDescription(ProspectiveStudentPayment $payment): string
    {
        /** @var Collection<int, string> $labels */
        $labels = $payment->details
            ->map(fn ($detail): string => SchoolReportCategory::categoryForProspectiveDetail($detail))
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return '-';
        }

        if ($labels->count() <= 2) {
            return $labels->implode(' + ');
        }

        return $labels->first().' + '.($labels->count() - 1).' lainnya';
    }
}
