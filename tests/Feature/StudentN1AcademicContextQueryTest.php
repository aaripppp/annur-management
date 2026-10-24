<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Facades\DB;

function n1ActiveYear(): AcademicYear
{
    return AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
}

function n1Year(string $year, string $start): AcademicYear
{
    return AcademicYear::firstOrCreate(
        ['year' => $year],
        ['is_active' => false, 'start_date' => $start, 'end_date' => '2027-06-30']
    );
}

function n1ListStudent(Student $student, ?AcademicYear $activeYear): Student
{
    $loaded = Student::query()
        ->with(['schoolClass', 'enrollments.academicYear', 'enrollments.schoolClass', 'categories'])
        ->findOrFail($student->id);

    $loaded->setRelation('academicContextActiveYear', $activeYear);

    return $loaded;
}

function n1AssertSameAcademicOutputs(Student $fresh, Student $listPath): void
{
    expect($listPath->academicStatus())->toBe($fresh->academicStatus())
        ->and($listPath->academicStatusLabel)->toBe($fresh->academicStatusLabel)
        ->and($listPath->academicClassLabel())->toBe($fresh->academicClassLabel())
        ->and($listPath->academicYearContextLabel())->toBe($fresh->academicYearContextLabel());
}

it('keeps academic-context queries bounded while list page size grows', function (int $count) {
    $activeYear = n1ActiveYear();
    $class = SchoolClass::factory()->create(['level' => 8]);

    $students = Student::factory()->count($count)->create(['class_id' => $class->id]);

    foreach ($students as $student) {
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    $page = Student::query()
        ->with(['schoolClass', 'enrollments.academicYear', 'enrollments.schoolClass', 'categories'])
        ->latest()
        ->limit($count)
        ->get();

    foreach ($page as $student) {
        $student->setRelation('academicContextActiveYear', $activeYear);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach ($page as $student) {
        $student->academicStatus();
        $student->academicStatusLabel;
        $student->academicClassLabel();
        $student->academicYearContextLabel();
    }

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    $total = count($queries);
    $academicYearQueries = collect($queries)->filter(fn (array $q): bool => str_contains($q['query'], 'academic_years'))->count();
    $enrollmentQueries = collect($queries)->filter(fn (array $q): bool => str_contains($q['query'], 'student_academic_enrollments'))->count();

    expect($total)->toBe(0)
        ->and($academicYearQueries)->toBe(0)
        ->and($enrollmentQueries)->toBe(0);
})->with([1, 10, 25]);

it('preserves academic outputs for a student with an active year enrollment', function () {
    $activeYear = n1ActiveYear();
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    n1AssertSameAcademicOutputs(
        Student::findOrFail($student->id),
        n1ListStudent($student, $activeYear)
    );

    expect(Student::findOrFail($student->id)->academicStatus())->toBe('aktif')
        ->and(Student::findOrFail($student->id)->academicYearContextLabel())->toBe('2026/2027');
});

it('preserves academic outputs for a future student with a distinct entry class', function () {
    $activeYear = n1ActiveYear();
    $futureYear = n1Year('2027/2028', '2027-07-01');
    $currentClass = SchoolClass::factory()->create(['level' => 8]);
    $entryClass = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $currentClass->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $entryClass->id,
        'status' => 'active',
    ]);

    $fresh = Student::findOrFail($student->id);

    n1AssertSameAcademicOutputs($fresh, n1ListStudent($student, $activeYear));

    expect($fresh->academicStatus())->toBe('calon_siswa')
        ->and($fresh->academicClassLabel())->toBe($entryClass->name)
        ->and($fresh->academicYearContextLabel())->toBe('2027/2028');
});

it('preserves academic outputs for a graduated student', function () {
    $activeYear = n1ActiveYear();
    $pastYear = n1Year('2025/2026', '2025-07-01');
    $class = SchoolClass::factory()->create(['level' => 12]);
    $student = Student::factory()->create(['class_id' => $class->id, 'status' => 'lulus']);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $pastYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $class->id,
        'status' => 'lulus',
    ]);

    $fresh = Student::findOrFail($student->id);

    n1AssertSameAcademicOutputs($fresh, n1ListStudent($student, $activeYear));

    expect($fresh->academicStatus())->toBe('lulus')
        ->and($fresh->academicClassLabel())->toBe($class->name);
});

it('preserves academic outputs for a legacy student without enrollments', function () {
    $activeYear = n1ActiveYear();
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    n1AssertSameAcademicOutputs(
        Student::findOrFail($student->id),
        n1ListStudent($student, $activeYear)
    );

    expect(Student::findOrFail($student->id)->academicStatus())->toBe('aktif')
        ->and(Student::findOrFail($student->id)->academicYearContextLabel())->toBeNull();
});

it('preserves academic outputs for multiple historical enrollments where ordering matters', function () {
    $activeYear = n1ActiveYear();
    $olderYear = n1Year('2024/2025', '2024-07-01');
    $middleYear = n1Year('2025/2026', '2025-07-01');
    $class = SchoolClass::factory()->create(['level' => 12]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $olderYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $middleYear->id,
        'school_class_id' => $class->id,
        'status' => 'lulus',
    ]);

    $fresh = Student::findOrFail($student->id);

    n1AssertSameAcademicOutputs($fresh, n1ListStudent($student, $activeYear));

    expect($fresh->academicStatus())->toBe('lulus')
        ->and($fresh->academicYearContextLabel())->toBe('2025/2026');
});

it('preserves academic outputs when no active academic year exists', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $pastYear = n1Year('2025/2026', '2025-07-01');
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $pastYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $fresh = Student::findOrFail($student->id);

    n1AssertSameAcademicOutputs($fresh, n1ListStudent($student, null));

    expect($fresh->academicStatus())->toBe('aktif')
        ->and($fresh->academicYearContextLabel())->toBe('2025/2026');
});
