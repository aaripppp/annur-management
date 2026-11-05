<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\PpdbApplicantCategory;
use App\Enums\PpdbDocumentType;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\PpdbAcademicYearSetting;
use App\Models\PpdbDefaultClassMapping;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Services\PpdbPublicRegistrationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function resetMysqlConcurrencyFixture(): void
{
    $database = (string) DB::selectOne('select database() as name')->name;
    $expected = (string) env('PPDB_MYSQL_CONCURRENCY_DATABASE');

    if ($expected === '' || ! hash_equals($expected, $database) || ! str_ends_with($database, '_test')) {
        throw new RuntimeException('Refusing to reset an unverified MySQL database.');
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    try {
        foreach ([
            'prospective_student_payment_details',
            'prospective_student_payments',
            'ppdb_documents',
            'ppdb_siblings',
            'ppdb_parents',
            'ppdb_profiles',
            'ppdb_public_submissions',
            'prospective_student_bills',
            'prospective_students',
            'prospective_student_sequences',
            'payment_rates',
            'payment_type_school_levels',
            'payment_types',
            'ppdb_default_class_mappings',
            'ppdb_academic_year_settings',
            'school_classes',
            'academic_years',
        ] as $table) {
            DB::table($table)->truncate();
        }
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

/** @return array{academic_year: AcademicYear, school_class: SchoolClass, payment_type: PaymentType} */
function mysqlConcurrencyFixture(string $year = '2090/2091', int $amount = 425000): array
{
    $academicYear = AcademicYear::query()->create([
        'year' => $year,
        'is_active' => false,
        'start_date' => substr($year, 0, 4).'-07-01',
        'end_date' => substr($year, 5, 4).'-06-30',
    ]);

    PpdbAcademicYearSetting::query()->create([
        'academic_year_id' => $academicYear->id,
        'is_open' => true,
    ]);

    $schoolClass = SchoolClass::factory()->create([
        'name' => 'Concurrency Class',
        'level' => 7,
    ]);

    PpdbDefaultClassMapping::query()->create([
        'class_level' => 7,
        'school_class_id' => $schoolClass->id,
    ]);

    $paymentType = PaymentType::query()->create([
        'name' => 'Concurrency Registration Fee',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::query()->create([
        'payment_type_id' => $paymentType->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $paymentType->id,
        'class_level' => 7,
        'amount' => $amount,
        'effective_from' => '2089-01-01',
        'effective_until' => null,
    ]);

    return compact('academicYear', 'schoolClass', 'paymentType');
}

/** @return array<string, mixed> */
function mysqlConcurrencyData(int $academicYearId, string $name): array
{
    return [
        'academic_year_id' => $academicYearId,
        'school_level' => 'SMP',
        'class_level' => 7,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
        'full_name' => $name,
        'nickname' => 'Concurrent',
        'gender' => 'L',
        'birthplace' => 'Jakarta',
        'birthdate' => '2015-01-01',
        'address' => 'Alamat pengujian concurrency',
        'father' => ['name' => 'Ayah Test', 'phone' => '081234567890', 'occupation' => 'Tester', 'income_range' => '3m_to_4_999m'],
        'mother' => ['name' => 'Ibu Test', 'phone' => '081234567891', 'occupation' => 'Tester', 'income_range' => '3m_to_4_999m'],
        'guardian' => ['name' => '', 'phone' => '', 'occupation' => '', 'income_range' => ''],
        'siblings' => [],
        'consent' => true,
    ];
}

/** @return array<string, UploadedFile> */
function mysqlConcurrencyDocuments(): array
{
    return collect(PpdbDocumentType::values())
        ->mapWithKeys(fn (string $type): array => [$type => UploadedFile::fake()->image($type.'.jpg')])
        ->all();
}

/**
 * @param  list<array{token: string, academic_year_id: int, name: string}>  $jobs
 * @return list<array{ok: bool, result?: array<string, string>, exception?: string, message?: string}>
 */
function runConcurrentMysqlRegistrations(array $jobs): array
{
    $runDirectory = sys_get_temp_dir().'/annur-ppdb-concurrency-'.Str::uuid();
    File::makeDirectory($runDirectory, 0700, true);
    $startFile = $runDirectory.'/start';
    $children = [];

    DB::disconnect('mysql');

    foreach ($jobs as $index => $job) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork the MySQL concurrency worker.');
        }

        if ($pid === 0) {
            while (! is_file($startFile)) {
                usleep(1000);
            }

            DB::purge('mysql');

            try {
                $result = app(PpdbPublicRegistrationService::class)->register(
                    mysqlConcurrencyData($job['academic_year_id'], $job['name']),
                    mysqlConcurrencyDocuments(),
                    $job['token'],
                );
                $payload = ['ok' => true, 'result' => $result];
            } catch (Throwable $exception) {
                $payload = [
                    'ok' => false,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ];
            }

            file_put_contents($runDirectory.'/result-'.$index.'.json', json_encode($payload, JSON_THROW_ON_ERROR));
            exit($payload['ok'] ? 0 : 1);
        }

        $children[] = $pid;
    }

    touch($startFile);

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    DB::purge('mysql');

    $results = collect(array_keys($jobs))->map(function (int $index) use ($runDirectory): array {
        $path = $runDirectory.'/result-'.$index.'.json';

        if (! is_file($path)) {
            return ['ok' => false, 'exception' => RuntimeException::class, 'message' => 'Worker produced no result.'];
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    })->all();

    File::deleteDirectory($runDirectory);

    return $results;
}

/**
 * Run an uncommitted configuration mutation in one process while registration
 * executes in another independent process.
 *
 * @param  Closure(): void  $mutation
 * @param  array{token: string, academic_year_id: int, name: string}  $job
 * @return array{ok: bool, result?: array<string, string>, exception?: string, message?: string}
 */
function runMysqlRegistrationDuringMutation(Closure $mutation, array $job): array
{
    $runDirectory = sys_get_temp_dir().'/annur-ppdb-race-'.Str::uuid();
    File::makeDirectory($runDirectory, 0700, true);
    $lockedFile = $runDirectory.'/locked';
    $registrationStartedFile = $runDirectory.'/registration-started';
    $releaseFile = $runDirectory.'/release';
    $resultFile = $runDirectory.'/registration.json';

    DB::disconnect('mysql');
    $mutationPid = pcntl_fork();

    if ($mutationPid === -1) {
        throw new RuntimeException('Unable to fork the MySQL mutation worker.');
    }

    if ($mutationPid === 0) {
        DB::purge('mysql');
        DB::beginTransaction();

        try {
            $mutation();
            touch($lockedFile);

            while (! is_file($releaseFile)) {
                usleep(1000);
            }

            DB::commit();
            exit(0);
        } catch (Throwable $exception) {
            DB::rollBack();
            file_put_contents($runDirectory.'/mutation-error', $exception::class.': '.$exception->getMessage());
            exit(1);
        }
    }

    while (! is_file($lockedFile) && ! is_file($runDirectory.'/mutation-error')) {
        usleep(1000);
    }

    if (is_file($runDirectory.'/mutation-error')) {
        throw new RuntimeException((string) file_get_contents($runDirectory.'/mutation-error'));
    }

    $registrationPid = pcntl_fork();

    if ($registrationPid === -1) {
        touch($releaseFile);
        pcntl_waitpid($mutationPid, $status);
        throw new RuntimeException('Unable to fork the MySQL registration worker.');
    }

    if ($registrationPid === 0) {
        DB::purge('mysql');
        touch($registrationStartedFile);

        try {
            $result = app(PpdbPublicRegistrationService::class)->register(
                mysqlConcurrencyData($job['academic_year_id'], $job['name']),
                mysqlConcurrencyDocuments(),
                $job['token'],
            );
            $payload = ['ok' => true, 'result' => $result];
        } catch (Throwable $exception) {
            $payload = [
                'ok' => false,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
        exit($payload['ok'] ? 0 : 1);
    }

    while (! is_file($registrationStartedFile)) {
        usleep(1000);
    }

    usleep(200000);
    touch($releaseFile);
    pcntl_waitpid($mutationPid, $mutationStatus);
    pcntl_waitpid($registrationPid, $registrationStatus);
    DB::purge('mysql');

    if (! is_file($resultFile)) {
        throw new RuntimeException('Registration race worker produced no result.');
    }

    $result = json_decode((string) file_get_contents($resultFile), true, flags: JSON_THROW_ON_ERROR);
    File::deleteDirectory($runDirectory);

    return $result;
}

beforeEach(function () {
    resetMysqlConcurrencyFixture();

    $storageRoot = sys_get_temp_dir().'/annur-ppdb-storage-'.Str::uuid();
    File::makeDirectory($storageRoot, 0700, true);
    config(['filesystems.disks.local.root' => $storageRoot]);
    Storage::forgetDisk('local');
    $this->mysqlStorageRoot = $storageRoot;
});

afterEach(function () {
    Storage::forgetDisk('local');

    if (is_string($this->mysqlStorageRoot)) {
        File::deleteDirectory($this->mysqlStorageRoot);
    }
});

it('uses InnoDB for every table participating in public registration locks', function () {
    $tables = [
        'ppdb_public_submissions',
        'academic_years',
        'ppdb_academic_year_settings',
        'ppdb_default_class_mappings',
        'school_classes',
        'prospective_student_sequences',
        'prospective_students',
        'prospective_student_bills',
    ];

    $engines = DB::table('information_schema.tables')
        ->where('table_schema', DB::raw('database()'))
        ->whereIn('table_name', $tables)
        ->pluck('engine', 'table_name');

    expect($engines)->toHaveCount(count($tables));

    foreach ($tables as $table) {
        expect(strtolower((string) $engines[$table]))->toBe('innodb');
    }
});

it('serializes simultaneous same-token submissions without duplicate applicants bills or payments', function () {
    $fixture = mysqlConcurrencyFixture();
    $token = Str::random(64);
    $jobs = collect(range(1, 3))->map(fn (int $worker): array => [
        'token' => $token,
        'academic_year_id' => $fixture['academicYear']->id,
        'name' => 'Concurrency Same Token '.$worker,
    ])->all();

    $results = runConcurrentMysqlRegistrations($jobs);

    foreach ($results as $result) {
        $this->assertTrue($result['ok'], json_encode($result, JSON_THROW_ON_ERROR));
    }

    expect(collect($results)->pluck('result.registration_number')->unique())->toHaveCount(1)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(1)
        ->and(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('ppdb_documents')->count())->toBe(6)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('generates unique registrations and one bill each for simultaneous distinct tokens', function () {
    $fixture = mysqlConcurrencyFixture();
    $jobs = collect(range(1, 4))->map(fn (int $worker): array => [
        'token' => Str::random(64),
        'academic_year_id' => $fixture['academicYear']->id,
        'name' => 'Concurrency Distinct Token '.$worker,
    ])->all();

    $results = runConcurrentMysqlRegistrations($jobs);

    foreach ($results as $result) {
        $this->assertTrue($result['ok'], json_encode($result, JSON_THROW_ON_ERROR));
    }

    expect(collect($results)->pluck('result.registration_number')->unique())->toHaveCount(4)
        ->and(DB::table('prospective_students')->count())->toBe(4)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(4)
        ->and(DB::table('ppdb_documents')->count())->toBe(24)
        ->and(DB::table('prospective_student_bills')->count())->toBe(4)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);

    expect(ProspectiveStudentBill::query()
        ->selectRaw('prospective_student_id, payment_type_id, academic_year, count(*) as aggregate')
        ->groupBy('prospective_student_id', 'payment_type_id', 'academic_year')
        ->having('aggregate', '>', 1)
        ->exists())->toBeFalse();
});

it('returns the committed result for a same-token replay without additional writes', function () {
    $fixture = mysqlConcurrencyFixture();
    $token = Str::random(64);
    $service = app(PpdbPublicRegistrationService::class);
    $first = $service->register(
        mysqlConcurrencyData($fixture['academicYear']->id, 'Concurrency Replay'),
        mysqlConcurrencyDocuments(),
        $token,
    );
    $replayed = $service->register([], [], $token);

    expect($replayed)->toBe($first)
        ->and(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('rejects registration when academic year closure commits before its locked authorization check', function () {
    $fixture = mysqlConcurrencyFixture();
    $settingId = PpdbAcademicYearSetting::query()->valueOrFail('id');
    $result = runMysqlRegistrationDuringMutation(
        function () use ($settingId): void {
            PpdbAcademicYearSetting::query()
                ->whereKey($settingId)
                ->lockForUpdate()
                ->update(['is_open' => false]);
        },
        [
            'token' => Str::random(64),
            'academic_year_id' => $fixture['academicYear']->id,
            'name' => 'Concurrency Closure Race',
        ],
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['exception'])->toBe(ValidationException::class)
        ->and(DB::table('prospective_students')->count())->toBe(0)
        ->and(DB::table('prospective_student_bills')->count())->toBe(0)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('uses the valid class mapping that commits before the mapping lock is acquired', function () {
    $fixture = mysqlConcurrencyFixture();
    $newClass = SchoolClass::factory()->create(['name' => 'Concurrency Updated Class', 'level' => 7]);
    $mappingId = PpdbDefaultClassMapping::query()->valueOrFail('id');
    $result = runMysqlRegistrationDuringMutation(
        function () use ($mappingId, $newClass): void {
            PpdbDefaultClassMapping::query()
                ->whereKey($mappingId)
                ->lockForUpdate()
                ->update(['school_class_id' => $newClass->id]);
        },
        [
            'token' => Str::random(64),
            'academic_year_id' => $fixture['academicYear']->id,
            'name' => 'Concurrency Mapping Race',
        ],
    );

    expect($result['ok'])->toBeTrue()
        ->and(DB::table('prospective_students')->value('school_class_id'))->toBe($newClass->id)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('creates one nonzero bill from a valid rate during a concurrent rate change', function () {
    $fixture = mysqlConcurrencyFixture(amount: 425000);
    $rateId = PaymentRate::query()->valueOrFail('id');
    $result = runMysqlRegistrationDuringMutation(
        function () use ($rateId): void {
            PaymentRate::query()
                ->whereKey($rateId)
                ->lockForUpdate()
                ->update(['amount' => 475000]);
        },
        [
            'token' => Str::random(64),
            'academic_year_id' => $fixture['academicYear']->id,
            'name' => 'Concurrency Rate Race',
        ],
    );

    $amount = (float) ProspectiveStudentBill::query()->value('amount');

    expect($result['ok'])->toBeTrue()
        ->and($amount)->toBeIn([425000.0, 475000.0])
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});
