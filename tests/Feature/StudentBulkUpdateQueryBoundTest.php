<?php

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\StudentBulkUpdateService;
use Illuminate\Support\Facades\DB;

/**
 * Batch 5 Stage 2 — bulk student update N+1 query bounds.
 *
 * Fokus: perubahan NIS pada banyak baris tidak boleh menumbuhkan jumlah SELECT
 * per baris, tanpa mengubah perilaku (konflik NIS, pemilihan enrollment,
 * klasifikasi baris, dan transaksi apply).
 */
function n5b2ActiveYear(): AcademicYear
{
    return AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function n5b2FutureYear(): AcademicYear
{
    return AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30'],
    );
}

function n5b2SchoolClass(SchoolLevel $level): SchoolClass
{
    $classLevel = match ($level) {
        SchoolLevel::TK => -2,
        SchoolLevel::SD => 5,
        SchoolLevel::SMP => 8,
        SchoolLevel::SMA => 11,
    };

    return SchoolClass::factory()->create(['level' => $classLevel]);
}

function n5b2Student(SchoolClass $class, string $nis): Student
{
    return Student::factory()->create(['class_id' => $class->id, 'nis' => $nis]);
}

function n5b2Enroll(Student $student, SchoolClass $class, AcademicYear $year, string $status = 'active'): StudentAcademicEnrollment
{
    return StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => $status,
    ]);
}

/**
 * @return array{0: array<string, mixed>, 1: int}
 */
function n5b2PreviewCounted(array $rows): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $preview = app(StudentBulkUpdateService::class)->preview($rows);

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$preview, $count];
}

/**
 * Bangun $count siswa berjenjang SMP dengan enrollment aktif; setiap baris
 * mengganti NIS ke nilai baru yang unik.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, Student>}
 */
function n5b2ChangedNisRows(int $count): array
{
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $rows = [];
    $students = [];

    for ($i = 0; $i < $count; $i++) {
        $student = n5b2Student($class, 'OLD'.$i);
        n5b2Enroll($student, $class, $activeYear);

        $students[] = $student;
        $rows[] = [
            'row_number' => $i + 1,
            'id_sistem' => $student->id,
            'nis' => 'NEW'.$i,
        ];
    }

    return [$rows, $students];
}

it('menjaga jumlah query tetap konstan untuk 1, 10, 50, dan 100 baris yang mengubah NIS', function (): void {
    $counts = [];

    foreach ([1, 10, 50, 100] as $size) {
        [$rows] = n5b2ChangedNisRows($size);

        [$preview, $count] = n5b2PreviewCounted($rows);

        expect($preview['summary']['ready'])->toBe($size);
        $counts[$size] = $count;
    }

    expect($counts[10])->toBe($counts[1])
        ->and($counts[50])->toBe($counts[1])
        ->and($counts[100])->toBe($counts[1])
        ->and($counts[1])->toBeLessThanOrEqual(8);
});

it('tidak menumbuhkan query saat NIS tidak berubah', function (): void {
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $counts = [];

    foreach ([1, 25] as $size) {
        $rows = [];

        for ($i = 0; $i < $size; $i++) {
            $student = n5b2Student($class, 'KEEP'.$size.'-'.$i);
            n5b2Enroll($student, $class, $activeYear);

            $rows[] = [
                'row_number' => $i + 1,
                'id_sistem' => $student->id,
                'nama_lengkap' => 'Nama Baru '.$i,
            ];
        }

        [$preview, $count] = n5b2PreviewCounted($rows);

        expect($preview['summary']['ready'])->toBe($size);
        $counts[$size] = $count;
    }

    expect($counts[25])->toBe($counts[1])
        ->and($counts[1])->toBeLessThanOrEqual(3);
});

it('menjaga jumlah query tetap konstan untuk campuran baris berubah dan tidak', function (): void {
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $rows = [];

    for ($i = 0; $i < 40; $i++) {
        $student = n5b2Student($class, 'MIX'.$i);
        n5b2Enroll($student, $class, $activeYear);

        $rows[] = $i % 2 === 0
            ? ['row_number' => $i + 1, 'id_sistem' => $student->id, 'nis' => 'MIXNEW'.$i]
            : ['row_number' => $i + 1, 'id_sistem' => $student->id, 'nama_lengkap' => 'Nama '.$i];
    }

    [$preview, $mixed] = n5b2PreviewCounted($rows);

    expect($preview['summary']['ready'])->toBe(40);

    // NIS berubah pada separuh baris tidak menambah query per baris.
    [$onlyOneRow] = n5b2ChangedNisRows(1);
    [, $single] = n5b2PreviewCounted($onlyOneRow);

    expect($mixed)->toBe($single);
});

it('mendeteksi konflik NIS pada jenjang dan tahun ajaran yang sama dengan pesan yang sama', function (): void {
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $a = n5b2Student($class, 'AAA');
    $b = n5b2Student($class, 'BBB');
    n5b2Enroll($a, $class, $activeYear);
    n5b2Enroll($b, $class, $activeYear);

    [$preview] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $a->id, 'nis' => 'BBB'],
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'][0])->toBe(
            "NIS BBB sudah terdaftar pada jenjang SMP di tahun ajaran {$activeYear->year}."
        );
});

it('mengizinkan NIS yang sama pada jenjang berbeda', function (): void {
    $activeYear = n5b2ActiveYear();
    $smp = n5b2SchoolClass(SchoolLevel::SMP);
    $sma = n5b2SchoolClass(SchoolLevel::SMA);

    $a = n5b2Student($smp, 'AAA');
    $b = n5b2Student($sma, 'BBB');
    n5b2Enroll($a, $smp, $activeYear);
    n5b2Enroll($b, $sma, $activeYear);

    // Siswa SMA mengambil NIS milik siswa SMP — jenjang berbeda, bukan konflik.
    [$preview] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $b->id, 'nis' => 'AAA'],
    ]);

    expect($preview['rows'][0]['status'])->toBe('ready')
        ->and($preview['rows'][0]['errors'])->toBe([]);
});

it('memakai enrollment tahun ajaran mendatang saat tidak ada enrollment aktif', function (): void {
    n5b2ActiveYear();
    $futureYear = n5b2FutureYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $a = n5b2Student($class, 'FUTA');
    $b = n5b2Student($class, 'FUTB');
    n5b2Enroll($a, $class, $futureYear, 'active');
    n5b2Enroll($b, $class, $futureYear, 'active');

    [$preview] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $a->id, 'nis' => 'FUTB'],
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'][0])->toBe(
            "NIS FUTB sudah terdaftar pada jenjang SMP di tahun ajaran {$futureYear->year}."
        );
});

it('memilih enrollment aktif alih-alih enrollment tahun ajaran mendatang', function (): void {
    $activeYear = n5b2ActiveYear();
    $futureYear = n5b2FutureYear();

    $smp = n5b2SchoolClass(SchoolLevel::SMP);
    $sma = n5b2SchoolClass(SchoolLevel::SMA);

    // Siswa A: enrollment aktif SMP + enrollment masa depan SMA.
    $a = n5b2Student($smp, 'AAA');
    n5b2Enroll($a, $smp, $activeYear);
    n5b2Enroll($a, $sma, $futureYear);

    // Konflik di SMA (masa depan) tidak relevan karena enrollment aktif adalah SMP.
    $smaPeer = n5b2Student($sma, 'SMA1');
    n5b2Enroll($smaPeer, $sma, $futureYear);

    [$preview] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $a->id, 'nis' => 'SMA1'],
    ]);

    expect($preview['rows'][0]['status'])->toBe('ready');

    // Konflik di SMP tahun aktif terdeteksi.
    $smpPeer = n5b2Student($smp, 'SMP1');
    n5b2Enroll($smpPeer, $smp, $activeYear);

    [$preview2] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $a->id, 'nis' => 'SMP1'],
    ]);

    expect($preview2['rows'][0]['status'])->toBe('blocked');
});

it('tidak melakukan query enrollment ketika siswa tidak memiliki enrollment terwakili', function (): void {
    n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $student = n5b2Student($class, 'NOENR');

    [$preview, $count] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $student->id, 'nis' => 'NOENR-NEW'],
    ]);

    expect($preview['rows'][0]['status'])->toBe('ready')
        ->and($count)->toBeLessThanOrEqual(5);
});

it('menandai baris ID ganda dan NIS tidak valid tanpa mengubah perilaku', function (): void {
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $a = n5b2Student($class, 'DUP1');
    n5b2Enroll($a, $class, $activeYear);

    [$preview] = n5b2PreviewCounted([
        ['row_number' => 1, 'id_sistem' => $a->id, 'nis' => 'NEW-DUP'],
        ['row_number' => 2, 'id_sistem' => $a->id, 'nis' => 'NEW-DUP'],
        ['row_number' => 3, 'id_sistem' => $a->id, 'nis' => str_repeat('X', 51)],
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'][0])->toContain('muncul lebih dari satu kali');
    expect($preview['rows'][1]['status'])->toBe('blocked');
    expect($preview['rows'][2]['status'])->toBe('blocked');
});

it('tetap hanya menerapkan baris siap dalam transaksi', function (): void {
    $activeYear = n5b2ActiveYear();
    $class = n5b2SchoolClass(SchoolLevel::SMP);

    $ready = n5b2Student($class, 'APPLY1');
    $conflict = n5b2Student($class, 'APPLY2');
    $target = n5b2Student($class, 'APPLY3');
    n5b2Enroll($ready, $class, $activeYear);
    n5b2Enroll($conflict, $class, $activeYear);
    n5b2Enroll($target, $class, $activeYear);

    $rows = [
        ['row_number' => 1, 'id_sistem' => $ready->id, 'nama_lengkap' => 'Nama Siap'],
        ['row_number' => 2, 'id_sistem' => $conflict->id, 'nis' => 'APPLY3'],
    ];

    $updated = app(StudentBulkUpdateService::class)->apply($rows);

    expect($updated)->toBe(1)
        ->and($ready->fresh()->nama_lengkap)->toBe('Nama Siap')
        ->and($conflict->fresh()->nis)->toBe('APPLY2')
        ->and($target->fresh()->nis)->toBe('APPLY3');
});
