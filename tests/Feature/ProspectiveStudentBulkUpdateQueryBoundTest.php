<?php

use App\Enums\ProspectiveStudentStatus;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ProspectiveStudentBulkUpdateService;
use Illuminate\Support\Facades\DB;

/**
 * @return array<int, array<string, mixed>>
 */
function ppdbBulkQueryRows(int $count, AcademicYear $academicYear, SchoolClass $schoolClass, string $dataset): array
{
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $prospects = ProspectiveStudent::factory()->count($count)->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);

    if ($dataset !== 'registered') {
        $students = Student::factory()->count($count)->create()->values();

        $prospects->values()->each(function (ProspectiveStudent $prospect, int $index) use ($dataset, $students): void {
            if ($dataset === 'converted' || $index % 2 === 0) {
                $prospect->update([
                    'status' => ProspectiveStudentStatus::Converted,
                    'converted_student_id' => $students[$index]->id,
                    'converted_at' => now(),
                ]);
            }
        });
    }
    $prospects->chunk(250)->each(function ($chunk): void {
        DB::table('ppdb_parents')->insert($chunk->map(fn (ProspectiveStudent $prospect): array => [
            'prospective_student_id' => $prospect->id,
            'role' => 'father',
            'nama' => 'AYAH QUERY '.$prospect->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    });
    $prospects->load(['ppdbProfile', 'ppdbParents', 'convertedStudent']);

    return $prospects->values()->map(fn (ProspectiveStudent $prospect, int $index): array => [
        'row_number' => $index + 2,
        'id_sistem' => $prospect->id,
        'no_pendaftaran' => $prospect->registration_number,
        'tahun_ajaran' => $academicYear->year,
        'kelas' => $schoolClass->name,
        'snapshot_token' => $service->snapshotToken($prospect),
        'nama_lengkap' => 'Nama Query Baru '.$index,
        'nama_panggilan' => '',
        'jenis_kelamin' => '',
        'nama_orang_tua' => '',
        'no_telp_orang_tua' => '',
        'alamat' => '',
        'notes' => '',
    ])->all();
}

function ppdbBulkPreviewQueryCount(array $rows): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function ppdbBulkApplySelectCount(array $rows): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(ProspectiveStudentBulkUpdateService::class)->apply($rows);

    $count = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();
    DB::disableQueryLog();

    return $count;
}

it('menjaga query preview dan apply proporsional terhadap chunk tanpa Student N+1', function (string $dataset, array $expected): void {
    $academicYear = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
    $schoolClass = SchoolClass::factory()->create(['name' => 'VIII QUERY', 'level' => 8]);
    $previewCounts = [];
    $applyCounts = [];

    foreach ([1, 50, 250, 500] as $size) {
        $rows = ppdbBulkQueryRows($size, $academicYear, $schoolClass, $dataset);
        $previewCounts[$size] = ppdbBulkPreviewQueryCount($rows);
        $applyCounts[$size] = ppdbBulkApplySelectCount($rows);
    }

    expect($previewCounts)->toBe($expected)
        ->and($applyCounts)->toBe($expected);
})->with([
    'registered only' => ['registered', [1 => 5, 50 => 5, 250 => 5, 500 => 10]],
    'converted only' => ['converted', [1 => 6, 50 => 6, 250 => 6, 500 => 12]],
    'mixed statuses' => ['mixed', [1 => 6, 50 => 6, 250 => 6, 500 => 12]],
]);
