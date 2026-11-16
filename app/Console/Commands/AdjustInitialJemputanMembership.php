<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Services\JemputanDriverMembershipPeriodValidator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

#[Signature('jemputan:adjust-initial-membership
    {--dry-run : Preview the audited candidates without writing data}
    {--execute : Apply the adjustment after explicit approval}
    {--include-multi-driver : Include audited assignments 23 and 110 as one package (execute only)}')]
#[Description('Safely preview or adjust audited initial Jemputan memberships to 2026-07-01')]
class AdjustInitialJemputanMembership extends Command
{
    private const ACADEMIC_YEAR = '2026/2027';

    private const ACADEMIC_YEAR_START = '2026-07-01';

    private const ACADEMIC_YEAR_END = '2027-06-30';

    private const ORIGINAL_DATE = '2026-11-10';

    private const TARGET_DATE = '2026-07-01';

    /** @var array<int, array{student_id: int, driver_id: int}> */
    private const SCOPE_A = [
        1 => ['student_id' => 1466, 'driver_id' => 1],
        2 => ['student_id' => 1320, 'driver_id' => 1],
        3 => ['student_id' => 1465, 'driver_id' => 1],
        4 => ['student_id' => 1510, 'driver_id' => 1],
        5 => ['student_id' => 1453, 'driver_id' => 1],
        6 => ['student_id' => 1660, 'driver_id' => 1],
        7 => ['student_id' => 1397, 'driver_id' => 1],
        8 => ['student_id' => 1341, 'driver_id' => 1],
        9 => ['student_id' => 1408, 'driver_id' => 1],
        10 => ['student_id' => 1459, 'driver_id' => 1],
        11 => ['student_id' => 1382, 'driver_id' => 1],
        12 => ['student_id' => 1553, 'driver_id' => 1],
        13 => ['student_id' => 1282, 'driver_id' => 1],
        14 => ['student_id' => 80, 'driver_id' => 2],
        15 => ['student_id' => 114, 'driver_id' => 2],
        16 => ['student_id' => 118, 'driver_id' => 2],
        17 => ['student_id' => 121, 'driver_id' => 2],
        18 => ['student_id' => 1615, 'driver_id' => 3],
        19 => ['student_id' => 1319, 'driver_id' => 3],
        20 => ['student_id' => 1696, 'driver_id' => 3],
        21 => ['student_id' => 1511, 'driver_id' => 3],
        22 => ['student_id' => 1528, 'driver_id' => 3],
        24 => ['student_id' => 1262, 'driver_id' => 3],
        25 => ['student_id' => 1679, 'driver_id' => 3],
        26 => ['student_id' => 1504, 'driver_id' => 3],
        27 => ['student_id' => 1526, 'driver_id' => 3],
        28 => ['student_id' => 1360, 'driver_id' => 3],
        29 => ['student_id' => 1583, 'driver_id' => 4],
        30 => ['student_id' => 1724, 'driver_id' => 4],
        31 => ['student_id' => 1761, 'driver_id' => 4],
        32 => ['student_id' => 1532, 'driver_id' => 4],
        33 => ['student_id' => 1518, 'driver_id' => 4],
        34 => ['student_id' => 1648, 'driver_id' => 4],
        35 => ['student_id' => 1393, 'driver_id' => 4],
        36 => ['student_id' => 1650, 'driver_id' => 4],
        37 => ['student_id' => 1594, 'driver_id' => 4],
        38 => ['student_id' => 1508, 'driver_id' => 4],
        39 => ['student_id' => 1381, 'driver_id' => 4],
        40 => ['student_id' => 1410, 'driver_id' => 4],
        41 => ['student_id' => 1585, 'driver_id' => 4],
        42 => ['student_id' => 1330, 'driver_id' => 4],
        43 => ['student_id' => 1584, 'driver_id' => 5],
        44 => ['student_id' => 1698, 'driver_id' => 5],
        45 => ['student_id' => 1474, 'driver_id' => 5],
        46 => ['student_id' => 1513, 'driver_id' => 5],
        47 => ['student_id' => 1353, 'driver_id' => 5],
        48 => ['student_id' => 1736, 'driver_id' => 5],
        49 => ['student_id' => 1807, 'driver_id' => 5],
        50 => ['student_id' => 1525, 'driver_id' => 5],
        51 => ['student_id' => 1549, 'driver_id' => 6],
        52 => ['student_id' => 1791, 'driver_id' => 6],
        53 => ['student_id' => 1276, 'driver_id' => 6],
        54 => ['student_id' => 1803, 'driver_id' => 6],
        55 => ['student_id' => 1738, 'driver_id' => 6],
        56 => ['student_id' => 1744, 'driver_id' => 6],
        57 => ['student_id' => 1701, 'driver_id' => 6],
        58 => ['student_id' => 1322, 'driver_id' => 6],
        59 => ['student_id' => 1735, 'driver_id' => 6],
        60 => ['student_id' => 1755, 'driver_id' => 7],
        61 => ['student_id' => 1665, 'driver_id' => 7],
        62 => ['student_id' => 1668, 'driver_id' => 7],
        63 => ['student_id' => 1690, 'driver_id' => 7],
        64 => ['student_id' => 1680, 'driver_id' => 7],
        65 => ['student_id' => 1439, 'driver_id' => 7],
        66 => ['student_id' => 1654, 'driver_id' => 7],
        68 => ['student_id' => 1537, 'driver_id' => 8],
        69 => ['student_id' => 1477, 'driver_id' => 8],
        70 => ['student_id' => 1425, 'driver_id' => 8],
        71 => ['student_id' => 1388, 'driver_id' => 8],
        72 => ['student_id' => 1269, 'driver_id' => 8],
        73 => ['student_id' => 1563, 'driver_id' => 8],
        74 => ['student_id' => 1545, 'driver_id' => 8],
        75 => ['student_id' => 1651, 'driver_id' => 9],
        76 => ['student_id' => 1712, 'driver_id' => 9],
        77 => ['student_id' => 1664, 'driver_id' => 9],
        78 => ['student_id' => 1633, 'driver_id' => 9],
        79 => ['student_id' => 1523, 'driver_id' => 9],
        80 => ['student_id' => 1695, 'driver_id' => 9],
        81 => ['student_id' => 1507, 'driver_id' => 9],
        82 => ['student_id' => 1942, 'driver_id' => 10],
        83 => ['student_id' => 2001, 'driver_id' => 10],
        84 => ['student_id' => 2052, 'driver_id' => 10],
        85 => ['student_id' => 1946, 'driver_id' => 10],
        86 => ['student_id' => 1816, 'driver_id' => 10],
        87 => ['student_id' => 1927, 'driver_id' => 10],
        88 => ['student_id' => 1846, 'driver_id' => 10],
        89 => ['student_id' => 1844, 'driver_id' => 10],
        90 => ['student_id' => 1827, 'driver_id' => 10],
        91 => ['student_id' => 1917, 'driver_id' => 11],
        92 => ['student_id' => 1907, 'driver_id' => 11],
        93 => ['student_id' => 1971, 'driver_id' => 11],
        94 => ['student_id' => 1941, 'driver_id' => 11],
        95 => ['student_id' => 1948, 'driver_id' => 11],
        96 => ['student_id' => 1904, 'driver_id' => 12],
        97 => ['student_id' => 1953, 'driver_id' => 12],
        98 => ['student_id' => 1861, 'driver_id' => 12],
        99 => ['student_id' => 1841, 'driver_id' => 12],
        100 => ['student_id' => 2077, 'driver_id' => 12],
        101 => ['student_id' => 1813, 'driver_id' => 12],
        102 => ['student_id' => 1990, 'driver_id' => 12],
        103 => ['student_id' => 2032, 'driver_id' => 13],
        104 => ['student_id' => 1997, 'driver_id' => 13],
        105 => ['student_id' => 2049, 'driver_id' => 13],
        106 => ['student_id' => 2030, 'driver_id' => 13],
        107 => ['student_id' => 2026, 'driver_id' => 13],
        108 => ['student_id' => 1837, 'driver_id' => 13],
        109 => ['student_id' => 1883, 'driver_id' => 13],
    ];

    /** @var array<int, array{student_id: int, driver_id: int}> */
    private const SCOPE_B = [
        23 => ['student_id' => 1485, 'driver_id' => 3],
        110 => ['student_id' => 1485, 'driver_id' => 7],
    ];

    /** @var array<int, array{student_id: int, driver_id: int, effective_from: string, effective_until: string|null, match_by_identity: bool, reason: string}> */
    private const PERMANENT_EXCLUSIONS = [
        67 => [
            'student_id' => 1711,
            'driver_id' => 7,
            'effective_from' => '2026-11-10',
            'effective_until' => '2026-11-10',
            'match_by_identity' => false,
            'reason' => 'Periode zero-length/invalid.',
        ],
        111 => [
            'student_id' => 1528,
            'driver_id' => 3,
            'effective_from' => '2026-11-12',
            'effective_until' => null,
            'match_by_identity' => true,
            'reason' => 'Periode lanjutan Raffasya dan bukan pengisian awal November.',
        ],
    ];

    public function handle(JemputanDriverMembershipPeriodValidator $periodValidator): int
    {
        $execute = (bool) $this->option('execute');
        $dryRun = (bool) $this->option('dry-run');
        $includeMultiDriver = (bool) $this->option('include-multi-driver');

        if ($execute && $dryRun) {
            $this->error('Pilih salah satu mode: --dry-run atau --execute.');

            return self::FAILURE;
        }

        if ($includeMultiDriver && ! $execute) {
            $this->error('--include-multi-driver hanya boleh digunakan bersama --execute.');

            return self::FAILURE;
        }

        $databaseName = DB::connection()->getDatabaseName();
        $this->components->twoColumnDetail('Environment', app()->environment());
        $this->components->twoColumnDetail('Connection', DB::getDefaultConnection());
        $this->components->twoColumnDetail('Database', $databaseName);
        $this->components->twoColumnDetail('Mode', $execute ? 'EXECUTE' : 'DRY RUN / READ ONLY');

        $academicYear = AcademicYear::query()->where('year', self::ACADEMIC_YEAR)->first();

        if (! $this->academicYearMatchesAudit($academicYear)) {
            $this->error('Tahun ajaran 2026/2027 tidak ditemukan atau periodenya berbeda dari manifest audit.');

            return self::FAILURE;
        }

        $manifest = $this->candidateManifest($includeMultiDriver);
        $inspection = $this->inspectCandidates($manifest, $academicYear, $periodValidator);
        $exclusions = $this->inspectExclusions($academicYear);

        $this->newLine();
        $this->info('Kandidat: '.count($manifest));
        $this->renderCandidates($inspection['rows']);
        $this->renderExclusions($exclusions['rows'], $includeMultiDriver);
        $this->renderHistoricalImpact($inspection['rows'], $academicYear->id);

        if (! $inspection['valid'] || ! $exclusions['valid']) {
            $this->error('Manifest tidak cocok atau validasi gagal. Tidak ada perubahan dilakukan.');

            return self::FAILURE;
        }

        if (! $execute) {
            $this->warn('DRY RUN: tidak ada data yang diubah. Gunakan --execute hanya setelah approval dan backup.');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->confirmProductionExecution($databaseName)) {
            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($manifest, $academicYear, $periodValidator): array {
                $lockedCandidates = JemputanDriverAssignment::query()
                    ->whereKey(array_keys($manifest))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                JemputanDriverAssignment::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->whereIn('student_id', collect(self::PERMANENT_EXCLUSIONS)->pluck('student_id')->unique())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                JemputanDriverAssignment::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->whereIn('student_id', collect($manifest)->pluck('student_id')->unique())
                    ->orderBy('student_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $lockedInspection = $this->inspectCandidates($manifest, $academicYear, $periodValidator);
                $lockedExclusions = $this->inspectExclusions($academicYear);

                if (! $lockedInspection['valid'] || ! $lockedExclusions['valid']) {
                    throw new RuntimeException('Data berubah setelah preview atau validasi tidak lagi lulus.');
                }

                $pendingIds = collect($lockedInspection['rows'])
                    ->where('status', 'VALID')
                    ->pluck('assignment_id')
                    ->all();
                $updated = 0;

                foreach ($pendingIds as $pendingId) {
                    $assignment = $lockedCandidates->get($pendingId);

                    if (
                        ! $assignment instanceof JemputanDriverAssignment
                        || $assignment->academic_year_id !== $academicYear->id
                        || $assignment->effective_from->toDateString() !== self::ORIGINAL_DATE
                    ) {
                        throw new RuntimeException('Assignment berubah setelah validasi dan batch dibatalkan.');
                    }

                    $assignment->timestamps = false;
                    $assignment->effective_from = self::TARGET_DATE;

                    if (! $assignment->saveQuietly()) {
                        throw new RuntimeException('Assignment gagal diperbarui dan batch dibatalkan.');
                    }

                    $updated++;
                }

                $after = $this->inspectCandidates($manifest, $academicYear, $periodValidator);

                if (! $after['valid'] || collect($after['rows'])->contains('status', '!=', 'SUDAH DISESUAIKAN')) {
                    throw new RuntimeException('Verifikasi pasca-update gagal.');
                }

                return ['updated' => $updated, 'already_adjusted' => count($manifest) - $updated];
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Batch dibatalkan dan di-rollback: '.$exception->getMessage());

            return self::FAILURE;
        }

        Log::notice('Initial Jemputan membership dates adjusted.', [
            'environment' => app()->environment(),
            'connection' => DB::getDefaultConnection(),
            'database' => $databaseName,
            'academic_year' => self::ACADEMIC_YEAR,
            'target_date' => self::TARGET_DATE,
            'included_multi_driver' => $includeMultiDriver,
            ...$result,
        ]);

        $this->info(sprintf(
            'Selesai. Updated: %d; sudah sesuai: %d; tabel finansial tidak diubah.',
            $result['updated'],
            $result['already_adjusted'],
        ));

        return self::SUCCESS;
    }

    /** @return array<int, array{student_id: int, driver_id: int, scope: string}> */
    private function candidateManifest(bool $includeMultiDriver): array
    {
        $manifest = collect(self::SCOPE_A)
            ->map(fn (array $identity, int $assignmentId): array => [
                ...$identity,
                'scope' => 'A',
            ])
            ->all();

        if ($includeMultiDriver) {
            foreach (self::SCOPE_B as $assignmentId => $identity) {
                $manifest[$assignmentId] = [
                    ...$identity,
                    'scope' => 'B',
                ];
            }
        }

        ksort($manifest);

        return $manifest;
    }

    /**
     * @param  array<int, array{student_id: int, driver_id: int, scope: string}>  $manifest
     * @return array{valid: bool, rows: list<array<string, int|string>>}
     */
    private function inspectCandidates(
        array $manifest,
        AcademicYear $academicYear,
        JemputanDriverMembershipPeriodValidator $periodValidator,
    ): array {
        $assignments = JemputanDriverAssignment::query()
            ->whereKey(array_keys($manifest))
            ->with(['student:id,nama_lengkap', 'driver:id,name'])
            ->get()
            ->keyBy('id');
        $rows = [];
        $periods = [];
        $valid = true;

        foreach ($manifest as $assignmentId => $expected) {
            $assignment = $assignments->get($assignmentId);
            $status = 'VALID';

            if (! $assignment instanceof JemputanDriverAssignment) {
                $status = 'GAGAL: assignment tidak ditemukan';
                $valid = false;
            } elseif (
                $assignment->student_id !== $expected['student_id']
                || $assignment->jemputan_driver_id !== $expected['driver_id']
                || $assignment->academic_year_id !== $academicYear->id
            ) {
                $status = 'GAGAL: identitas berbeda dari manifest';
                $valid = false;
            } elseif ($assignment->effective_from->toDateString() === self::TARGET_DATE) {
                $status = 'SUDAH DISESUAIKAN';
            } elseif ($assignment->effective_from->toDateString() !== self::ORIGINAL_DATE) {
                $status = 'GAGAL: tanggal berubah sejak audit';
                $valid = false;
            }

            if ($assignment instanceof JemputanDriverAssignment && ! str_starts_with($status, 'GAGAL')) {
                $periods[] = [
                    'assignment_id' => $assignment->id,
                    'student_id' => $assignment->student_id,
                    'driver_id' => $assignment->jemputan_driver_id,
                    'academic_year_id' => $assignment->academic_year_id,
                    'effective_from' => CarbonImmutable::parse(self::TARGET_DATE),
                    'effective_until' => $assignment->effective_until?->toImmutable(),
                ];
            }

            $rows[] = [
                'assignment_id' => $assignmentId,
                'student_id' => $assignment?->student_id ?? $expected['student_id'],
                'student_name' => $assignment?->student?->nama_lengkap ?? '—',
                'driver_name' => $assignment?->driver?->name ?? '—',
                'old_date' => $assignment?->effective_from?->toDateString() ?? '—',
                'new_date' => self::TARGET_DATE,
                'status' => $status,
                'scope' => $expected['scope'],
            ];
        }

        if ($valid) {
            try {
                $periodValidator->assertValidBatch($periods);
            } catch (ValidationException $exception) {
                $valid = false;
                $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                $rows = collect($rows)->map(function (array $row) use ($message): array {
                    if ($row['status'] === 'VALID' || $row['status'] === 'SUDAH DISESUAIKAN') {
                        $row['status'] = 'GAGAL: '.$message;
                    }

                    return $row;
                })->all();
            }
        }

        return ['valid' => $valid, 'rows' => $rows];
    }

    /** @return array{valid: bool, rows: list<array<string, int|string>>} */
    private function inspectExclusions(AcademicYear $academicYear): array
    {
        $assignments = JemputanDriverAssignment::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('student_id', collect(self::PERMANENT_EXCLUSIONS)->pluck('student_id')->unique())
            ->with(['student:id,nama_lengkap', 'driver:id,name'])
            ->get()
            ->sortBy('id')
            ->values();
        $rows = [];
        $valid = true;

        foreach (self::PERMANENT_EXCLUSIONS as $assignmentId => $expected) {
            $identityMatches = $assignments->filter(fn (JemputanDriverAssignment $assignment): bool => $assignment->student_id === $expected['student_id']
                && $assignment->jemputan_driver_id === $expected['driver_id']
                && $assignment->effective_from->toDateString() === $expected['effective_from']);
            $assignment = $expected['match_by_identity']
                ? ($identityMatches->count() === 1 ? $identityMatches->first() : null)
                : $assignments->firstWhere('id', $assignmentId);
            $matches = $assignment instanceof JemputanDriverAssignment
                && $assignment->student_id === $expected['student_id']
                && $assignment->jemputan_driver_id === $expected['driver_id']
                && $assignment->academic_year_id === $academicYear->id
                && $assignment->effective_from->toDateString() === $expected['effective_from']
                && ($expected['match_by_identity']
                    ? $this->hasValidActualPeriod($assignment, $academicYear)
                    : $assignment->effective_until?->toDateString() === $expected['effective_until']);
            $valid = $valid && $matches;
            $rows[] = [
                'assignment_id' => $assignment?->id ?? $assignmentId,
                'student_name' => $assignment?->student?->nama_lengkap ?? '—',
                'driver_name' => $assignment?->driver?->name ?? '—',
                'reason' => match (true) {
                    $matches => $expected['reason'],
                    $expected['match_by_identity'] && $identityMatches->count() > 1 => 'GAGAL: exclusion perpindahan ambigu',
                    default => 'GAGAL: exclusion berbeda dari manifest audit',
                },
            ];
        }

        return ['valid' => $valid, 'rows' => $rows];
    }

    private function hasValidActualPeriod(
        JemputanDriverAssignment $assignment,
        AcademicYear $academicYear,
    ): bool {
        $effectiveFrom = $assignment->effective_from->toImmutable()->startOfDay();
        $effectiveUntil = $assignment->effective_until?->toImmutable()->startOfDay();
        $academicYearEnd = $academicYear->end_date->toImmutable()->addDay()->startOfDay();

        return ($effectiveUntil === null || $effectiveFrom->lt($effectiveUntil))
            && ($effectiveUntil === null || ! $effectiveUntil->gt($academicYearEnd));
    }

    private function academicYearMatchesAudit(?AcademicYear $academicYear): bool
    {
        return $academicYear instanceof AcademicYear
            && $academicYear->start_date->toDateString() === self::ACADEMIC_YEAR_START
            && $academicYear->end_date->toDateString() === self::ACADEMIC_YEAR_END;
    }

    /** @param list<array<string, int|string>> $rows */
    private function renderCandidates(array $rows): void
    {
        $this->table(
            ['Scope', 'Assignment', 'Student ID', 'Siswa', 'Sopir', 'Tanggal Lama', 'Tanggal Baru', 'Validasi'],
            collect($rows)->map(fn (array $row): array => [
                $row['scope'],
                $row['assignment_id'],
                $row['student_id'],
                $row['student_name'],
                $row['driver_name'],
                $row['old_date'],
                $row['new_date'],
                $row['status'],
            ])->all(),
        );
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     */
    private function renderExclusions(array $rows, bool $includeMultiDriver): void
    {
        if (! $includeMultiDriver) {
            $multiDriverAssignments = JemputanDriverAssignment::query()
                ->whereKey(array_keys(self::SCOPE_B))
                ->with(['student:id,nama_lengkap', 'driver:id,name'])
                ->get()
                ->keyBy('id');

            foreach (self::SCOPE_B as $assignmentId => $identity) {
                $assignment = $multiDriverAssignments->get($assignmentId);
                $rows[] = [
                    'assignment_id' => $assignmentId,
                    'student_name' => $assignment?->student?->nama_lengkap ?? 'Student ID '.$identity['student_id'],
                    'driver_name' => $assignment?->driver?->name ?? 'Driver ID '.$identity['driver_id'],
                    'reason' => 'Multi-Sopir; perlu --execute --include-multi-driver.',
                ];
            }
        }

        $this->newLine();
        $this->info('Kandidat dikecualikan:');
        $this->table(
            ['Assignment', 'Siswa', 'Sopir', 'Alasan'],
            collect($rows)->map(fn (array $row): array => [
                $row['assignment_id'],
                $row['student_name'],
                $row['driver_name'],
                $row['reason'],
            ])->all(),
        );
    }

    /** @param list<array<string, int|string>> $rows */
    private function renderHistoricalImpact(array $rows, int $academicYearId): void
    {
        $studentIds = collect($rows)->pluck('student_id')->unique();
        $allocationCount = JemputanDriverSalaryAllocation::query()
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->where('period_year', 2026)
            ->whereBetween('period_month', [7, 10])
            ->count();

        $this->newLine();
        $this->warn('Dampak potensial: Gaji Sopir dan Rekap 6 Bulan Juli–Oktober dapat berubah.');
        $this->line('Stored allocation Juli–Oktober pada kandidat: '.$allocationCount.'. Allocation dan tabel finansial tidak akan diubah.');
    }

    private function confirmProductionExecution(string $databaseName): bool
    {
        $this->error('PRODUCTION: pastikan preview production telah diverifikasi dan backup database tersedia.');

        if (! $this->confirm('Lanjutkan batch pada production?', false)) {
            $this->warn('Eksekusi production dibatalkan.');

            return false;
        }

        if ($this->ask('Ketik nama database untuk konfirmasi') !== $databaseName) {
            $this->error('Nama database tidak cocok. Eksekusi production dibatalkan.');

            return false;
        }

        return true;
    }
}
