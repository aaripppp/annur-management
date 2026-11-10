<?php

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Services\PpdbBiodataRecapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function recapAcademicYear(string $year): AcademicYear
{
    [$start, $end] = explode('/', $year);

    return AcademicYear::create([
        'year' => $year,
        'is_active' => false,
        'start_date' => $start.'-07-01',
        'end_date' => $end.'-06-30',
    ]);
}

function recapApplicant(AcademicYear $year, SchoolClass $class, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ], $overrides));
}

/** @return array<string, list<int>> */
function recapGroupIds(PpdbBiodataRecapQuery $query, AcademicYear $year, SchoolLevel $level): array
{
    $ids = array_fill_keys(array_keys(PpdbBiodataRecapQuery::GROUPS), []);

    $query->eachGroup($year->id, $level, function (string $group, Collection $chunk) use (&$ids): void {
        array_push($ids[$group], ...$chunk->pluck('id')->all());
    });

    return $ids;
}

beforeEach(function () {
    $this->query = app(PpdbBiodataRecapQuery::class);
    $this->year = recapAcademicYear('2030/2031');
    $this->otherYear = recapAcademicYear('2031/2032');
    $this->smpClass = SchoolClass::factory()->create(['level' => 8]);
    $this->sdClass = SchoolClass::factory()->create(['level' => 5]);
});

it('uses one shared year level and status scope', function () {
    $registered = recapApplicant($this->year, $this->smpClass);
    $converted = recapApplicant($this->year, $this->smpClass, [
        'status' => ProspectiveStudentStatus::Converted,
        'jenis_kelamin' => 'P',
    ]);
    recapApplicant($this->year, $this->smpClass, ['status' => ProspectiveStudentStatus::Cancelled]);
    recapApplicant($this->otherYear, $this->smpClass);
    recapApplicant($this->year, $this->sdClass);

    $groupIds = recapGroupIds($this->query, $this->year, SchoolLevel::SMP);
    $allIds = collect($groupIds)->flatten()->all();
    $counts = $this->query->counts($this->year->id, SchoolLevel::SMP);

    expect($allIds)->toHaveCount(2)
        ->toContain($registered->id, $converted->id)
        ->and($counts['grand_total'])->toBe(2)
        ->and(array_sum(array_intersect_key($counts, PpdbBiodataRecapQuery::GROUPS)))->toBe(2);
});

it('classifies all valid combinations and every invalid value exactly once', function () {
    $valid = [
        PpdbBiodataRecapQuery::GROUP_IKHWAN_ANNUR => recapApplicant($this->year, $this->smpClass),
        PpdbBiodataRecapQuery::GROUP_AKHWAT_ANNUR => recapApplicant($this->year, $this->smpClass, ['jenis_kelamin' => 'P']),
        PpdbBiodataRecapQuery::GROUP_IKHWAN_NON_ANNUR => recapApplicant($this->year, $this->smpClass, ['applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY]),
        PpdbBiodataRecapQuery::GROUP_AKHWAT_NON_ANNUR => recapApplicant($this->year, $this->smpClass, [
            'jenis_kelamin' => 'P',
            'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY,
        ]),
    ];
    $nullCategory = recapApplicant($this->year, $this->smpClass, ['applicant_category' => null]);
    $emptyCategory = recapApplicant($this->year, $this->smpClass);
    $invalidCategory = recapApplicant($this->year, $this->smpClass);
    $nullGender = recapApplicant($this->year, $this->smpClass, ['jenis_kelamin' => null]);
    DB::table('prospective_students')->where('id', $emptyCategory->id)->update(['applicant_category' => '']);
    DB::table('prospective_students')->where('id', $invalidCategory->id)->update(['applicant_category' => 'legacy_category']);

    DB::statement('PRAGMA ignore_check_constraints = ON');
    $emptyGender = recapApplicant($this->year, $this->smpClass);
    $invalidGender = recapApplicant($this->year, $this->smpClass);
    DB::table('prospective_students')->where('id', $emptyGender->id)->update(['jenis_kelamin' => '']);
    DB::table('prospective_students')->where('id', $invalidGender->id)->update(['jenis_kelamin' => 'X']);
    DB::statement('PRAGMA ignore_check_constraints = OFF');

    $groupIds = recapGroupIds($this->query, $this->year, SchoolLevel::SMP);
    $allIds = collect($groupIds)->flatten();
    $unclassifiedIds = $groupIds[PpdbBiodataRecapQuery::GROUP_UNCLASSIFIED];

    foreach ($valid as $group => $applicant) {
        expect($groupIds[$group])->toContain($applicant->id);
    }

    expect($unclassifiedIds)->toHaveCount(6)
        ->toContain(
            $nullCategory->id,
            $emptyCategory->id,
            $invalidCategory->id,
            $nullGender->id,
            $emptyGender->id,
            $invalidGender->id,
        )
        ->and($allIds)->toHaveCount(10)
        ->and($allIds->unique())->toHaveCount(10);

    $counts = $this->query->counts($this->year->id, SchoolLevel::SMP);
    expect($counts)->toMatchArray([
        PpdbBiodataRecapQuery::GROUP_IKHWAN_ANNUR => 1,
        PpdbBiodataRecapQuery::GROUP_AKHWAT_ANNUR => 1,
        PpdbBiodataRecapQuery::GROUP_IKHWAN_NON_ANNUR => 1,
        PpdbBiodataRecapQuery::GROUP_AKHWAT_NON_ANNUR => 1,
        PpdbBiodataRecapQuery::GROUP_UNCLASSIFIED => 6,
        'grand_total' => 10,
    ]);

    $legacyApplicant = null;
    $this->query->eachGroupChunk(
        $this->year->id,
        SchoolLevel::SMP,
        PpdbBiodataRecapQuery::GROUP_UNCLASSIFIED,
        function (Collection $chunk) use ($invalidCategory, &$legacyApplicant): void {
            $legacyApplicant = $chunk->firstWhere('id', $invalidCategory->id);
        },
    );

    expect($legacyApplicant)->toBeInstanceOf(ProspectiveStudent::class)
        ->and($this->query->applicantCategoryForExport($legacyApplicant))->toBe('legacy_category')
        ->and($this->query->genderForExport($legacyApplicant))->toBe('L');
});

it('reconciles aggregate counts retrieval counters and empty groups', function () {
    recapApplicant($this->year, $this->smpClass);
    recapApplicant($this->year, $this->smpClass, ['applicant_category' => null]);

    $aggregate = $this->query->counts($this->year->id, SchoolLevel::SMP);
    $retrieved = $this->query->eachGroup($this->year->id, SchoolLevel::SMP, function (): void {});

    expect($retrieved)->toMatchArray(array_intersect_key($aggregate, PpdbBiodataRecapQuery::GROUPS))
        ->and(array_sum($retrieved))->toBe($aggregate['grand_total'])
        ->and($aggregate['grand_total'])->toBe(2)
        ->and($retrieved[PpdbBiodataRecapQuery::GROUP_AKHWAT_ANNUR])->toBe(0)
        ->and($retrieved[PpdbBiodataRecapQuery::GROUP_IKHWAN_NON_ANNUR])->toBe(0)
        ->and($retrieved[PpdbBiodataRecapQuery::GROUP_AKHWAT_NON_ANNUR])->toBe(0);
});

it('processes one reconciled report snapshot with academic year metadata loaded once', function () {
    $classified = recapApplicant($this->year, $this->smpClass);
    $unclassified = recapApplicant($this->year, $this->smpClass, ['jenis_kelamin' => null]);
    $seenIds = [];

    $snapshot = $this->query->processSnapshot(
        $this->year->id,
        SchoolLevel::SMP,
        function (string $group, Collection $chunk) use (&$seenIds): void {
            foreach ($chunk as $applicant) {
                $seenIds[$group][] = $applicant->id;
            }
        },
    );

    expect($snapshot['academic_year']->is($this->year))->toBeTrue()
        ->and($snapshot['aggregate_counts']['grand_total'])->toBe(2)
        ->and(array_sum($snapshot['retrieved_counts']))->toBe(2)
        ->and(collect($seenIds)->flatten()->sort()->values()->all())->toBe(collect([$classified->id, $unclassified->id])->sort()->values()->all())
        ->and(collect($seenIds)->flatten()->unique())->toHaveCount(2);
});

it('calculates every recap total with one conditional aggregate select', function () {
    recapApplicant($this->year, $this->smpClass);
    recapApplicant($this->year, $this->smpClass, ['applicant_category' => null]);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $counts = $this->query->counts($this->year->id, SchoolLevel::SMP);

    $selectCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();
    DB::disableQueryLog();

    expect($selectCount)->toBe(1)
        ->and($counts['grand_total'])->toBe(2)
        ->and(array_sum(array_intersect_key($counts, PpdbBiodataRecapQuery::GROUPS)))->toBe(2);
});

it('uses stable descending keyset order across timestamp ties and chunk boundaries', function (int $size, int $expectedChunks) {
    $applicants = ProspectiveStudent::factory()->count($size)->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->smpClass->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);
    DB::table('prospective_students')
        ->whereIn('id', $applicants->pluck('id'))
        ->update(['created_at' => '2030-01-01 08:00:00']);
    $chunks = [];
    $retrievedIds = [];

    $retrievedCount = $this->query->eachGroupChunk(
        $this->year->id,
        SchoolLevel::SMP,
        PpdbBiodataRecapQuery::GROUP_IKHWAN_ANNUR,
        function (Collection $chunk) use (&$chunks, &$retrievedIds): void {
            $chunks[] = $chunk->count();
            array_push($retrievedIds, ...$chunk->pluck('id')->all());
        },
    );

    expect($retrievedCount)->toBe($size)
        ->and($chunks)->toHaveCount($expectedChunks)
        ->and($retrievedIds)->toBe($applicants->sortByDesc('id')->pluck('id')->values()->all())
        ->and(array_unique($retrievedIds))->toHaveCount($size);
})->with([
    '200 rows' => [200, 1],
    '201 rows' => [201, 2],
    '400 rows' => [400, 2],
]);

it('keeps selects bounded per chunk and eager loads every biodata relation', function (int $size, int $expectedSelects) {
    $applicants = ProspectiveStudent::factory()->count($size)->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->smpClass->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);

    foreach ($applicants as $applicant) {
        PpdbProfile::create(['prospective_student_id' => $applicant->id]);
        PpdbParent::create(['prospective_student_id' => $applicant->id, 'role' => 'father']);
        PpdbSibling::create([
            'prospective_student_id' => $applicant->id,
            'sort_order' => 1,
            'nama_lengkap' => 'Saudara '.$applicant->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $relationsLoaded = true;

    $this->query->eachGroupChunk(
        $this->year->id,
        SchoolLevel::SMP,
        PpdbBiodataRecapQuery::GROUP_IKHWAN_ANNUR,
        function (Collection $chunk) use (&$relationsLoaded): void {
            $relationsLoaded = $relationsLoaded && $chunk->every(fn (ProspectiveStudent $applicant): bool => $applicant->relationLoaded('schoolClass')
                && $applicant->relationLoaded('ppdbProfile')
                && $applicant->relationLoaded('ppdbParents')
                && $applicant->relationLoaded('ppdbSiblings'));

            foreach ($chunk as $applicant) {
                $applicant->schoolClass?->name;
                $applicant->ppdbProfile?->nisn;
                $applicant->ppdbParents->count();
                $applicant->ppdbSiblings->count();
            }
        },
    );

    $selectCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();
    DB::disableQueryLog();

    expect($relationsLoaded)->toBeTrue()
        ->and($selectCount)->toBe($expectedSelects);
})->with([
    'one chunk' => [1, 5],
    'two chunks' => [201, 10],
    'two exact chunks' => [400, 10],
]);

it('defines the six sheets and identical stable 78 column detail contract', function () {
    $headers = PpdbBiodataRecapQuery::detailHeaders();
    $keys = array_column(PpdbBiodataRecapQuery::DETAIL_COLUMNS, 'key');
    $guardianHeaders = collect(PpdbBiodataRecapQuery::DETAIL_COLUMNS)
        ->where('section', 'guardian')
        ->pluck('label')
        ->all();

    expect(PpdbBiodataRecapQuery::SHEET_NAMES)->toBe([
        'REKAP',
        'IKHWAN KELUARGA ANNUR',
        'AKHWAT KELUARGA ANNUR',
        'IKHWAN NON-KELUARGA',
        'AKHWAT NON-KELUARGA',
        'BELUM DIKATEGORIKAN',
    ])->and($headers)->toHaveCount(78)
        ->and($headers)->toHaveCount(count(array_unique($headers)))
        ->and($keys)->toHaveCount(count(array_unique($keys)))
        ->and($headers)->toContain(
            'No. Pendaftaran',
            'NISN',
            'Jumlah Saudara',
            'Ayah - Status Hidup',
            'Ibu - Status Hidup',
            'Jumlah Record Detail Saudara',
            'Nama Saudara',
            'Tanggal Lahir Saudara',
            'Jenis Kelamin Saudara',
            'Pendidikan/Pekerjaan Saudara',
        )->and($guardianHeaders)->toHaveCount(9)
        ->not->toContain('Wali - Status Hidup')
        ->and($headers)->not->toContain('ID Sistem', 'Catatan', 'Token Snapshot');
});

it('keeps TK NISN blank and formats sibling details in database order', function () {
    $applicant = recapApplicant($this->year, SchoolClass::factory()->create(['level' => -1]));
    PpdbProfile::create([
        'prospective_student_id' => $applicant->id,
        'nisn' => '0012345678',
    ]);
    PpdbSibling::create([
        'prospective_student_id' => $applicant->id,
        'sort_order' => 2,
        'nama_lengkap' => 'Saudara Kedua',
        'tanggal_lahir' => '2020-02-02',
        'jenis_kelamin' => 'P',
        'pendidikan_pekerjaan' => 'TK',
    ]);
    PpdbSibling::create([
        'prospective_student_id' => $applicant->id,
        'sort_order' => 1,
        'nama_lengkap' => 'Saudara Pertama',
        'tanggal_lahir' => '2018-01-01',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'SD',
    ]);
    $applicant->load(['ppdbProfile', 'ppdbSiblings']);

    $siblings = $this->query->siblingTextColumns($applicant);

    expect($this->query->nisnForExport($applicant, SchoolLevel::TK))->toBe('')
        ->and($this->query->nisnForExport($applicant, SchoolLevel::SD))->toBe('0012345678')
        ->and($siblings)->toMatchArray([
            'count' => 2,
            'names' => "1. SAUDARA PERTAMA\n2. SAUDARA KEDUA",
            'birthdates' => "1. 2018-01-01\n2. 2020-02-02",
            'genders' => "1. Laki-laki\n2. Perempuan",
            'education_occupations' => "1. SD\n2. TK",
        ]);
});
