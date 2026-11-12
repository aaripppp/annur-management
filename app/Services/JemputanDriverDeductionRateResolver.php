<?php

namespace App\Services;

use App\Models\JemputanDriver;
use App\Models\JemputanDriverDeductionRate;
use Illuminate\Support\Collection;

class JemputanDriverDeductionRateResolver
{
    public const SOURCE_HISTORY = 'history';

    public const SOURCE_LEGACY = 'legacy';

    public const SOURCE_NONE = 'none';

    /**
     * Resolve the foundation deduction rate that applies to one driver period.
     *
     * The newest rate whose effective period is on or before the report period
     * wins. Future rates are ignored. When no history exists, the legacy column
     * can be used as a provisional fallback unless explicitly disabled.
     *
     * @return array{
     *     percentage: float|null,
     *     configured: bool,
     *     source: string,
     *     effective_year: int|null,
     *     effective_month: int|null
     * }
     */
    public function resolve(
        JemputanDriver|int $driver,
        int $year,
        int $month,
        bool $allowLegacyFallback = true,
    ): array {
        $driverId = $driver instanceof JemputanDriver ? (int) $driver->id : $driver;
        $rate = JemputanDriverDeductionRate::query()
            ->where('jemputan_driver_id', $driverId)
            ->where(function ($query) use ($year, $month): void {
                $query
                    ->where('effective_year', '<', $year)
                    ->orWhere(function ($query) use ($year, $month): void {
                        $query->where('effective_year', $year)
                            ->where('effective_month', '<=', $month);
                    });
            })
            ->orderByDesc('effective_year')
            ->orderByDesc('effective_month')
            ->first(['effective_year', 'effective_month', 'percentage']);

        if ($rate instanceof JemputanDriverDeductionRate) {
            return [
                'percentage' => (float) $rate->percentage,
                'configured' => true,
                'source' => self::SOURCE_HISTORY,
                'effective_year' => (int) $rate->effective_year,
                'effective_month' => (int) $rate->effective_month,
            ];
        }

        if (! $allowLegacyFallback) {
            return $this->notConfigured();
        }

        $legacyPercentage = $driver instanceof JemputanDriver
            ? $driver->foundation_deduction_percentage
            : JemputanDriver::query()->whereKey($driverId)->value('foundation_deduction_percentage');

        if ($legacyPercentage === null) {
            return $this->notConfigured();
        }

        return [
            'percentage' => (float) $legacyPercentage,
            'configured' => true,
            'source' => self::SOURCE_LEGACY,
            'effective_year' => null,
            'effective_month' => null,
        ];
    }

    /**
     * Resolve many drivers and periods while loading rate history in one query.
     *
     * This keeps a semester recap from running one deduction-rate query per driver
     * per month. Each period still uses the newest rate effective in that period.
     *
     * @param  iterable<int, JemputanDriver>  $drivers
     * @param  list<array{key: string, year: int, month: int}>  $periods
     * @return array<int, array<string, array{
     *     percentage: float|null,
     *     configured: bool,
     *     source: string,
     *     effective_year: int|null,
     *     effective_month: int|null
     * }>>
     */
    public function resolveMany(
        iterable $drivers,
        array $periods,
        bool $allowLegacyFallback = true,
    ): array {
        $driverCollection = collect($drivers)
            ->filter(fn ($driver): bool => $driver instanceof JemputanDriver)
            ->keyBy(fn (JemputanDriver $driver): int => (int) $driver->id);

        if ($driverCollection->isEmpty() || $periods === []) {
            return [];
        }

        $ratesByDriver = JemputanDriverDeductionRate::query()
            ->whereIn('jemputan_driver_id', $driverCollection->keys()->all())
            ->orderByDesc('effective_year')
            ->orderByDesc('effective_month')
            ->get(['jemputan_driver_id', 'effective_year', 'effective_month', 'percentage'])
            ->groupBy('jemputan_driver_id');
        $resolved = [];

        foreach ($driverCollection as $driver) {
            $rates = $ratesByDriver->get($driver->id, collect());
            $legacyPercentage = $driver->foundation_deduction_percentage;

            foreach ($periods as $period) {
                $resolved[(int) $driver->id][(string) $period['key']] = $this->resolveFromRates(
                    $rates,
                    (int) $period['year'],
                    (int) $period['month'],
                    $legacyPercentage === null ? null : (float) $legacyPercentage,
                    $allowLegacyFallback,
                );
            }
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, JemputanDriverDeductionRate>  $rates
     * @return array{
     *     percentage: float|null,
     *     configured: bool,
     *     source: string,
     *     effective_year: int|null,
     *     effective_month: int|null
     * }
     */
    private function resolveFromRates(
        Collection $rates,
        int $year,
        int $month,
        ?float $legacyPercentage,
        bool $allowLegacyFallback,
    ): array {
        $rate = $rates->first(fn (JemputanDriverDeductionRate $rate): bool => (int) $rate->effective_year < $year
            || ((int) $rate->effective_year === $year && (int) $rate->effective_month <= $month));

        if ($rate instanceof JemputanDriverDeductionRate) {
            return [
                'percentage' => (float) $rate->percentage,
                'configured' => true,
                'source' => self::SOURCE_HISTORY,
                'effective_year' => (int) $rate->effective_year,
                'effective_month' => (int) $rate->effective_month,
            ];
        }

        if (! $allowLegacyFallback || $legacyPercentage === null) {
            return $this->notConfigured();
        }

        return [
            'percentage' => $legacyPercentage,
            'configured' => true,
            'source' => self::SOURCE_LEGACY,
            'effective_year' => null,
            'effective_month' => null,
        ];
    }

    /** @return array{percentage: null, configured: false, source: string, effective_year: null, effective_month: null} */
    private function notConfigured(): array
    {
        return [
            'percentage' => null,
            'configured' => false,
            'source' => self::SOURCE_NONE,
            'effective_year' => null,
            'effective_month' => null,
        ];
    }
}
