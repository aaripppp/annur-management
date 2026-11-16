<?php

use App\Console\Commands\AdjustInitialJemputanMembership;
use App\Models\AcademicYear;
use App\Models\JemputanDriverAssignment;
use App\Models\SchoolClass;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/** @return array<int, mixed> */
function initialMembershipManifest(string $constant): array
{
    $reflection = new ReflectionClass(AdjustInitialJemputanMembership::class);

    return $reflection->getReflectionConstant($constant)->getValue();
}

function seedInitialMembershipAuditDataset(): AcademicYear
{
    $scopeA = initialMembershipManifest('SCOPE_A');
    $scopeB = initialMembershipManifest('SCOPE_B');
    $exclusions = initialMembershipManifest('PERMANENT_EXCLUSIONS');
    $manifest = $scopeA + $scopeB + $exclusions;
    $now = '2026-11-13 12:00:00';
    $academicYear = AcademicYear::query()->updateOrCreate([
        'year' => '2026/2027',
    ], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $schoolClass = SchoolClass::factory()->create();
    $studentRows = collect($manifest)
        ->pluck('student_id')
        ->unique()
        ->sort()
        ->map(fn (int $studentId): array => [
            'id' => $studentId,
            'nis' => 'JEM-'.$studentId,
            'nama_lengkap' => 'SISWA '.$studentId,
            'nama_panggilan' => 'SISWA',
            'class_id' => $schoolClass->id,
            'jenis_kelamin' => 'L',
            'alamat' => 'Alamat test',
            'created_at' => $now,
            'updated_at' => $now,
        ])
        ->values()
        ->all();
    $driverRows = collect($manifest)
        ->pluck('driver_id')
        ->unique()
        ->sort()
        ->map(fn (int $driverId): array => [
            'id' => $driverId,
            'name' => 'SOPIR '.$driverId,
            'phone' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])
        ->values()
        ->all();

    DB::table('students')->insert($studentRows);
    DB::table('jemputan_drivers')->insert($driverRows);

    $assignmentRows = [];

    foreach ($scopeA + $scopeB as $assignmentId => $identity) {
        $assignmentRows[] = [
            'id' => $assignmentId,
            'jemputan_driver_id' => $identity['driver_id'],
            'student_id' => $identity['student_id'],
            'academic_year_id' => $academicYear->id,
            'effective_from' => '2026-11-10',
            'effective_until' => match ($assignmentId) {
                1 => '2026-09-01',
                22 => '2026-11-12',
                default => null,
            },
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach ($exclusions as $assignmentId => $identity) {
        $assignmentRows[] = [
            'id' => $assignmentId,
            'jemputan_driver_id' => $identity['driver_id'],
            'student_id' => $identity['student_id'],
            'academic_year_id' => $academicYear->id,
            'effective_from' => $identity['effective_from'],
            'effective_until' => $identity['effective_until'],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('jemputan_driver_assignments')->insert($assignmentRows);

    return $academicYear;
}

/** @return array<string, array<int, array<string, mixed>>> */
function initialMembershipFinancialSnapshot(): array
{
    return collect([
        'student_bills',
        'payments',
        'payment_details',
        'bill_adjustments',
        'student_payment_settings',
        'jemputan_driver_salary_allocations',
    ])->mapWithKeys(fn (string $table): array => [
        $table => DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
    ])->all();
}

it('is read only by default and in explicit dry-run mode with bounded queries', function (array $arguments) {
    seedInitialMembershipAuditDataset();
    $before = DB::table('jemputan_driver_assignments')->orderBy('id')->get();
    $selectQueries = 0;

    DB::listen(function (QueryExecuted $query) use (&$selectQueries): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
            $selectQueries++;
        }
    });

    $this->artisan('jemputan:adjust-initial-membership', $arguments)
        ->expectsOutputToContain('Kandidat: 107')
        ->expectsOutputToContain('DRY RUN: tidak ada data yang diubah')
        ->assertSuccessful();

    expect(DB::table('jemputan_driver_assignments')->orderBy('id')->get()->all())
        ->toEqual($before->all())
        ->and($selectQueries)->toBeLessThanOrEqual(15);
})->with([
    'default' => [[]],
    'explicit dry run' => [['--dry-run' => true]],
]);

it('requires execute and rejects conflicting or unapproved multi-driver options', function () {
    seedInitialMembershipAuditDataset();

    $this->artisan('jemputan:adjust-initial-membership', [
        '--include-multi-driver' => true,
    ])->assertExitCode(Command::FAILURE);

    $this->artisan('jemputan:adjust-initial-membership', [
        '--dry-run' => true,
        '--execute' => true,
    ])->assertExitCode(Command::FAILURE);

    expect(JemputanDriverAssignment::query()->whereDate('effective_from', '2026-07-01')->count())->toBe(0);
});

it('executes exact scope A only, preserves every other column and is idempotent', function () {
    seedInitialMembershipAuditDataset();
    $scopeAIds = array_keys(initialMembershipManifest('SCOPE_A'));
    $beforeRows = JemputanDriverAssignment::query()->orderBy('id')->get()->keyBy('id');
    $financialBefore = initialMembershipFinancialSnapshot();
    $writeQueries = [];

    DB::listen(function (QueryExecuted $query) use (&$writeQueries): void {
        if (preg_match('/^(update|insert|delete)\b/i', ltrim($query->sql)) === 1) {
            $writeQueries[] = $query->sql;
        }
    });

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('Updated: 107; sudah sesuai: 0')
        ->assertSuccessful();

    $afterRows = JemputanDriverAssignment::query()->orderBy('id')->get()->keyBy('id');

    foreach ($scopeAIds as $assignmentId) {
        expect($afterRows[$assignmentId]->effective_from->toDateString())->toBe('2026-07-01')
            ->and($afterRows[$assignmentId]->effective_until?->toDateString())->toBe($beforeRows[$assignmentId]->effective_until?->toDateString())
            ->and($afterRows[$assignmentId]->student_id)->toBe($beforeRows[$assignmentId]->student_id)
            ->and($afterRows[$assignmentId]->jemputan_driver_id)->toBe($beforeRows[$assignmentId]->jemputan_driver_id)
            ->and($afterRows[$assignmentId]->academic_year_id)->toBe($beforeRows[$assignmentId]->academic_year_id)
            ->and($afterRows[$assignmentId]->updated_at?->format('Y-m-d H:i:s'))->toBe($beforeRows[$assignmentId]->updated_at?->format('Y-m-d H:i:s'));
    }

    expect($afterRows[23]->effective_from->toDateString())->toBe('2026-11-10')
        ->and($afterRows[110]->effective_from->toDateString())->toBe('2026-11-10')
        ->and($afterRows[18]->effective_from->toDateString())->toBe('2026-07-01')
        ->and($afterRows[18]->effective_until)->toBeNull()
        ->and($afterRows[22]->effective_from->toDateString())->toBe('2026-07-01')
        ->and($afterRows[22]->effective_until?->toDateString())->toBe('2026-11-12')
        ->and($afterRows[67]->effective_from->toDateString())->toBe('2026-11-10')
        ->and($afterRows[111]->student_id)->toBe(1528)
        ->and($afterRows[111]->jemputan_driver_id)->toBe(3)
        ->and($afterRows[111]->effective_from->toDateString())->toBe('2026-11-12')
        ->and($afterRows[111]->effective_until)->toBeNull()
        ->and(initialMembershipFinancialSnapshot())->toBe($financialBefore)
        ->and($writeQueries)->toHaveCount(107)
        ->and(collect($writeQueries)->every(
            fn (string $query): bool => str_contains($query, 'jemputan_driver_assignments')
                && str_contains($query, 'effective_from'),
        ))->toBeTrue();

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('Updated: 0; sudah sesuai: 107')
        ->assertSuccessful();

    expect(initialMembershipFinancialSnapshot())->toBe($financialBefore);
});

it('recognizes the current audited state and leaves only assignment 18 pending in scope A', function () {
    seedInitialMembershipAuditDataset();
    $alreadyAdjustedIds = array_values(array_filter(
        array_keys(initialMembershipManifest('SCOPE_A')),
        fn (int $assignmentId): bool => $assignmentId !== 18,
    ));
    DB::table('jemputan_driver_assignments')
        ->whereIn('id', [...$alreadyAdjustedIds, 23, 110])
        ->update(['effective_from' => '2026-07-01']);
    $beforeDryRun = DB::table('jemputan_driver_assignments')->orderBy('id')->get()->all();

    $this->artisan('jemputan:adjust-initial-membership', ['--dry-run' => true])
        ->expectsOutputToContain('Kandidat: 107')
        ->expectsOutputToContain('DRY RUN: tidak ada data yang diubah')
        ->assertSuccessful();

    expect(DB::table('jemputan_driver_assignments')->orderBy('id')->get()->all())->toEqual($beforeDryRun);

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('Updated: 1; sudah sesuai: 106')
        ->assertSuccessful();

    expect(JemputanDriverAssignment::query()->findOrFail(18)->effective_from->toDateString())->toBe('2026-07-01')
        ->and(JemputanDriverAssignment::query()->findOrFail(18)->effective_until)->toBeNull()
        ->and(JemputanDriverAssignment::query()->findOrFail(22)->effective_until?->toDateString())->toBe('2026-11-12')
        ->and(JemputanDriverAssignment::query()->findOrFail(23)->effective_from->toDateString())->toBe('2026-07-01')
        ->and(JemputanDriverAssignment::query()->findOrFail(110)->effective_from->toDateString())->toBe('2026-07-01')
        ->and(JemputanDriverAssignment::query()->findOrFail(67)->effective_from->toDateString())->toBe('2026-11-10')
        ->and(JemputanDriverAssignment::query()->findOrFail(111)->effective_from->toDateString())->toBe('2026-11-12');
});

it('processes the two multi-driver assignments together only when explicitly included', function () {
    $academicYear = seedInitialMembershipAuditDataset();
    DB::table('jemputan_driver_salary_allocations')->insert([
        [
            'student_id' => 1485,
            'academic_year_id' => $academicYear->id,
            'period_year' => 2026,
            'period_month' => 7,
            'jemputan_driver_id' => 3,
            'allocated_amount' => 400000,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'student_id' => 1485,
            'academic_year_id' => $academicYear->id,
            'period_year' => 2026,
            'period_month' => 7,
            'jemputan_driver_id' => 7,
            'allocated_amount' => 303500,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    $allocationBefore = initialMembershipFinancialSnapshot();

    $this->artisan('jemputan:adjust-initial-membership', [
        '--execute' => true,
        '--include-multi-driver' => true,
    ])->expectsOutputToContain('Kandidat: 109')
        ->expectsOutputToContain('Updated: 109; sudah sesuai: 0')
        ->assertSuccessful();

    expect(JemputanDriverAssignment::query()->findOrFail(23)->effective_from->toDateString())->toBe('2026-07-01')
        ->and(JemputanDriverAssignment::query()->findOrFail(110)->effective_from->toDateString())->toBe('2026-07-01')
        ->and(initialMembershipFinancialSnapshot())->toBe($allocationBefore);
});

it('rejects the multi-driver package when either audited assignment is missing', function () {
    seedInitialMembershipAuditDataset();
    JemputanDriverAssignment::query()->whereKey(110)->delete();

    $this->artisan('jemputan:adjust-initial-membership', [
        '--execute' => true,
        '--include-multi-driver' => true,
    ])->assertExitCode(Command::FAILURE);

    expect(JemputanDriverAssignment::query()->findOrFail(23)->effective_from->toDateString())->toBe('2026-11-10')
        ->and(JemputanDriverAssignment::query()->whereDate('effective_from', '2026-07-01')->count())->toBe(0);
});

it('audits the moved assignment by identity and actual period instead of its old id', function () {
    seedInitialMembershipAuditDataset();
    DB::table('jemputan_driver_assignments')
        ->where('id', 111)
        ->update(['id' => 511]);

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('Updated: 107; sudah sesuai: 0')
        ->assertSuccessful();

    $movedAssignment = JemputanDriverAssignment::query()->findOrFail(511);

    expect($movedAssignment->student_id)->toBe(1528)
        ->and($movedAssignment->jemputan_driver_id)->toBe(3)
        ->and($movedAssignment->effective_from->toDateString())->toBe('2026-11-12')
        ->and($movedAssignment->effective_until)->toBeNull()
        ->and(JemputanDriverAssignment::query()->whereKey(111)->exists())->toBeFalse();
});

it('rejects an ambiguous moved-assignment identity without partial updates', function () {
    $academicYear = seedInitialMembershipAuditDataset();
    JemputanDriverAssignment::query()->forceCreate([
        'id' => 511,
        'jemputan_driver_id' => 3,
        'student_id' => 1528,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-11-12',
        'effective_until' => '2027-02-01',
    ]);

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('exclusion perpindahan ambigu')
        ->assertExitCode(Command::FAILURE);

    expect(JemputanDriverAssignment::query()->whereKey(array_keys(initialMembershipManifest('SCOPE_A')))
        ->whereDate('effective_from', '2026-07-01')->count())->toBe(0);
});

it('rolls back the whole batch when the target period overlaps', function () {
    $academicYear = seedInitialMembershipAuditDataset();
    $candidate = JemputanDriverAssignment::query()->findOrFail(1);
    JemputanDriverAssignment::query()->forceCreate([
        'id' => 500,
        'jemputan_driver_id' => $candidate->jemputan_driver_id,
        'student_id' => $candidate->student_id,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-09-01',
    ]);

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('tumpang tindih')
        ->assertExitCode(Command::FAILURE);

    expect(JemputanDriverAssignment::query()->whereKey(array_keys(initialMembershipManifest('SCOPE_A')))
        ->whereDate('effective_from', '2026-07-01')->count())->toBe(0);
});

it('rejects data changed since the audited preview without partial updates', function () {
    seedInitialMembershipAuditDataset();
    DB::table('jemputan_driver_assignments')->where('id', 1)->update(['effective_from' => '2026-10-01']);

    $this->artisan('jemputan:adjust-initial-membership', ['--execute' => true])
        ->expectsOutputToContain('tanggal berubah sejak audit')
        ->assertExitCode(Command::FAILURE);

    expect(JemputanDriverAssignment::query()->findOrFail(1)->effective_from->toDateString())->toBe('2026-10-01')
        ->and(JemputanDriverAssignment::query()->whereKey(array_keys(initialMembershipManifest('SCOPE_A')))
            ->whereDate('effective_from', '2026-07-01')->count())->toBe(0);
});
