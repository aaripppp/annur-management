<?php

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function readIsolationTu(SchoolLevel $schoolLevel, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $schoolLevel,
        'is_active' => true,
    ], $attributes));
}

function readIsolationClass(SchoolLevel $schoolLevel, string $suffix = 'A'): SchoolClass
{
    $level = match ($schoolLevel) {
        SchoolLevel::TK => -2,
        SchoolLevel::SD => 1,
        SchoolLevel::SMP => 7,
        SchoolLevel::SMA => 10,
    };

    return SchoolClass::factory()->create([
        'name' => $schoolLevel->value.' '.$suffix,
        'level' => $level,
    ]);
}

function readIsolationStudent(SchoolClass $schoolClass, string $name, StudentStatus $status = StudentStatus::Active): Student
{
    return Student::factory()->create([
        'class_id' => $schoolClass->id,
        'nama_lengkap' => $name,
        'status' => $status,
    ]);
}

function readIsolationEnroll(Student $student, AcademicYear $year, SchoolClass $schoolClass, string $status = 'active'): StudentAcademicEnrollment
{
    return StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $schoolClass->id,
        'status' => $status,
    ]);
}

function readIsolationLivewireSnapshot(string $html): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

    return html_entity_decode($matches[1] ?? '', ENT_QUOTES);
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false]);

    $this->pastYear = AcademicYear::query()->updateOrCreate(['year' => '2025/2026'], [
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $this->activeYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->futureYear = AcademicYear::query()->updateOrCreate(['year' => '2027/2028'], [
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->laterFutureYear = AcademicYear::query()->updateOrCreate(['year' => '2028/2029'], [
        'is_active' => false,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);
});

it('shows TU TK SD SMP and SMA only students with an effective class in their unit', function (SchoolLevel $schoolLevel) {
    $ownClass = readIsolationClass($schoolLevel, 'Own');
    $otherLevel = $schoolLevel === SchoolLevel::SMA ? SchoolLevel::SMP : SchoolLevel::SMA;
    $otherClass = readIsolationClass($otherLevel, 'Other');
    $ownStudent = readIsolationStudent($ownClass, 'Visible '.$schoolLevel->value);
    $otherStudent = readIsolationStudent($otherClass, 'Hidden '.$otherLevel->value);
    readIsolationEnroll($ownStudent, $this->activeYear, $ownClass);
    readIsolationEnroll($otherStudent, $this->activeYear, $otherClass);

    Livewire::actingAs(readIsolationTu($schoolLevel))
        ->test(StudentManagement::class)
        ->assertSee($ownStudent->nama_lengkap)
        ->assertDontSee($otherStudent->nama_lengkap);
})->with([
    'TK' => SchoolLevel::TK,
    'SD' => SchoolLevel::SD,
    'SMP' => SchoolLevel::SMP,
    'SMA' => SchoolLevel::SMA,
]);

it('uses active-year enrollment before historical enrollment and stored class', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $smpClass = readIsolationClass(SchoolLevel::SMP);
    $student = readIsolationStudent($sdClass, 'Current SMP Student');
    readIsolationEnroll($student, $this->pastYear, $sdClass);
    readIsolationEnroll($student, $this->activeYear, $smpClass);

    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $student))
        ->assertForbidden();

    $this->actingAs(readIsolationTu(SchoolLevel::SMP))
        ->get(route('siswa.biodata', $student))
        ->assertOk()
        ->assertSee($student->nama_lengkap)
        ->assertSee($smpClass->name);
});

it('prefers active-year enrollment over any future enrollment', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $smpClass = readIsolationClass(SchoolLevel::SMP);
    $student = readIsolationStudent($smpClass, 'Current SD Future SMP Student');
    readIsolationEnroll($student, $this->activeYear, $sdClass);
    readIsolationEnroll($student, $this->futureYear, $smpClass);

    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $student))
        ->assertOk()
        ->assertSee($sdClass->name);

    $this->actingAs(readIsolationTu(SchoolLevel::SMP))
        ->get(route('siswa.biodata', $student))
        ->assertForbidden();
});

it('uses the earliest future active enrollment only for a future active student', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $smpClass = readIsolationClass(SchoolLevel::SMP);
    $student = readIsolationStudent($smpClass, 'Future SD Student');
    readIsolationEnroll($student, $this->futureYear, $sdClass);
    readIsolationEnroll($student, $this->laterFutureYear, $smpClass);

    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $student))
        ->assertOk();

    $this->actingAs(readIsolationTu(SchoolLevel::SMP))
        ->get(route('siswa.biodata', $student))
        ->assertForbidden();
});

it('uses the last actually attended active enrollment for terminal students', function (StudentStatus $status) {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $smpClass = readIsolationClass(SchoolLevel::SMP);
    $smaClass = readIsolationClass(SchoolLevel::SMA);
    $student = readIsolationStudent($smaClass, 'Terminal SMP Student', $status);
    readIsolationEnroll($student, $this->pastYear, $sdClass);
    readIsolationEnroll($student, $this->activeYear, $smpClass);
    readIsolationEnroll($student, $this->futureYear, $smaClass, 'lulus');

    $this->actingAs(readIsolationTu(SchoolLevel::SMP))
        ->get(route('siswa.biodata', $student))
        ->assertOk()
        ->assertSee($smpClass->name);

    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $student))
        ->assertForbidden();
})->with([
    'graduated' => StudentStatus::Graduated,
    'transferred' => StudentStatus::Transferred,
    'withdrawn' => StudentStatus::Withdrawn,
]);

it('falls back to legacy class only when no suitable enrollment exists', function () {
    $tkClass = readIsolationClass(SchoolLevel::TK);
    $student = readIsolationStudent($tkClass, 'Legacy TK Student');

    $this->actingAs(readIsolationTu(SchoolLevel::TK))
        ->get(route('siswa.biodata', $student))
        ->assertOk();

    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $student))
        ->assertForbidden();
});

it('prevents search class and level filter tampering from escaping the assigned unit', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD, 'Allowed');
    $smpClass = readIsolationClass(SchoolLevel::SMP, 'Forbidden');
    $ownStudent = readIsolationStudent($sdClass, 'Own Search Result');
    $crossUnitStudent = readIsolationStudent($smpClass, 'Cross Unit Secret');
    readIsolationEnroll($ownStudent, $this->activeYear, $sdClass);
    readIsolationEnroll($crossUnitStudent, $this->activeYear, $smpClass);

    $component = Livewire::actingAs(readIsolationTu(SchoolLevel::SD))
        ->test(StudentManagement::class)
        ->assertSee($ownStudent->nama_lengkap)
        ->assertDontSee($crossUnitStudent->nama_lengkap)
        ->assertSee($sdClass->name)
        ->assertDontSee($smpClass->name);

    $component
        ->set('search', 'Cross Unit Secret')
        ->assertDontSee($crossUnitStudent->nama_lengkap)
        ->set('search', '')
        ->set('filterClassId', (string) $smpClass->id)
        ->assertDontSee($crossUnitStudent->nama_lengkap)
        ->assertDontSee($ownStudent->nama_lengkap)
        ->set('filterClassId', '')
        ->set('filterLevel', SchoolLevel::SMP->value)
        ->assertDontSee($crossUnitStudent->nama_lengkap)
        ->assertDontSee($ownStudent->nama_lengkap);
});

it('limits statistics and class dropdowns to the assigned effective unit', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD, 'Stats');
    $smpClass = readIsolationClass(SchoolLevel::SMP, 'Stats');
    $ownStudent = readIsolationStudent($sdClass, 'Own Count');
    $crossStudent = readIsolationStudent($smpClass, 'Cross Count');
    readIsolationEnroll($ownStudent, $this->activeYear, $sdClass);
    readIsolationEnroll($crossStudent, $this->activeYear, $smpClass);

    $component = Livewire::actingAs(readIsolationTu(SchoolLevel::SD))
        ->test(StudentManagement::class)
        ->assertSee($sdClass->name)
        ->assertDontSee($smpClass->name);

    expect($component->viewData('statCounts'))->toBe([
        'total' => 1,
        'active' => 1,
        'prospective' => 0,
        'graduated' => 0,
    ]);
});

it('renders a TU biodata-only detail without billing queries or billing HTML', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $student = readIsolationStudent($sdClass, 'Safe Biodata Student');
    readIsolationEnroll($student, $this->activeYear, $sdClass);
    $this->actingAs(readIsolationTu(SchoolLevel::SD));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->get(route('siswa.biodata', $student));
    $queries = collect(DB::getQueryLog())->pluck('query');

    $response
        ->assertOk()
        ->assertSee($student->nama_lengkap)
        ->assertSee('Biodata Siswa')
        ->assertDontSee('Tagihan')
        ->assertDontSee('Pembayaran')
        ->assertDontSee('Penyesuaian')
        ->assertDontSee('Buku Tagihan');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'student_bills')
        || str_contains($query, 'payments')
        || str_contains($query, 'bill_adjustments')))->toBeEmpty()
        ->and($queries->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'))->count())->toBe(11);
});

it('does not expose another unit through TU biodata component state tampering', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $smpClass = readIsolationClass(SchoolLevel::SMP);
    $ownStudent = readIsolationStudent($sdClass, 'Own Biodata Student');
    $foreignStudent = readIsolationStudent($smpClass, 'Foreign Biodata Student');
    readIsolationEnroll($ownStudent, $this->activeYear, $sdClass);
    readIsolationEnroll($foreignStudent, $this->activeYear, $smpClass);

    $page = $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get(route('siswa.biodata', $ownStudent))
        ->assertOk()
        ->assertSee($ownStudent->nama_lengkap);

    $response = $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => readIsolationLivewireSnapshot($page->getContent()),
                'updates' => ['student' => $foreignStudent->getKey()],
                'calls' => [],
            ]],
        ]);

    $response
        ->assertOk()
        ->assertSee($ownStudent->nama_lengkap)
        ->assertDontSee($foreignStudent->nama_lengkap);
});

it('allows own-unit Student deletion and denies foreign deletion for TU', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $student = readIsolationStudent($sdClass, 'Read Only Student');
    readIsolationEnroll($student, $this->activeYear, $sdClass);
    $foreignClass = readIsolationClass(SchoolLevel::SMP);
    $foreignStudent = readIsolationStudent($foreignClass, 'Foreign Student');
    readIsolationEnroll($foreignStudent, $this->activeYear, $foreignClass);
    $tu = readIsolationTu(SchoolLevel::SD);

    $component = Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->assertSee('Tambah Siswa')
        ->assertDontSee('Import Siswa')
        ->assertDontSee('Update Data Siswa')
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Hapus"', false);

    Livewire::actingAs($tu)->test(StudentManagement::class)->call('confirmDelete', $foreignStudent->id)->assertForbidden();
    $component->call('confirmDelete', $student->id)->call('delete');

    expect(Student::query()->whereKey($student)->exists())->toBeFalse()
        ->and(Student::query()->whereKey($foreignStudent)->exists())->toBeTrue();
});

it('allows scoped Student Livewire reads but rejects snapshots from unapproved components', function () {
    $sdClass = readIsolationClass(SchoolLevel::SD);
    $student = readIsolationStudent($sdClass, 'Livewire Read Student');
    readIsolationEnroll($student, $this->activeYear, $sdClass);
    $foreignClass = readIsolationClass(SchoolLevel::SMP);
    $foreignStudent = readIsolationStudent($foreignClass, 'Foreign Livewire Student');
    readIsolationEnroll($foreignStudent, $this->activeYear, $foreignClass);
    $tu = readIsolationTu(SchoolLevel::SD);

    $studentPage = $this->actingAs($tu)->get(route('siswa.index'))->assertOk();
    $studentSnapshot = readIsolationLivewireSnapshot($studentPage->getContent());

    $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $studentSnapshot,
                'updates' => ['search' => 'Livewire Read Student'],
                'calls' => [],
            ]],
        ])
        ->assertOk();

    $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $studentSnapshot,
                'updates' => [],
                'calls' => [[
                    'path' => '',
                    'method' => 'confirmDelete',
                    'params' => [$foreignStudent->id],
                ]],
            ]],
        ])
        ->assertForbidden();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $dashboardSnapshot = readIsolationLivewireSnapshot(
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent()
    );

    $this->actingAs($tu)
        ->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $dashboardSnapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ])
        ->assertForbidden();
});

it('keeps imports bulk updates and exam eligibility denied', function (string $url) {
    $this->actingAs(readIsolationTu(SchoolLevel::SD))
        ->get($url)
        ->assertRedirect(route('tu.pending'));
})->with([
    'import' => fn () => route('siswa.import'),
    'bulk update' => fn () => route('siswa.update'),
    'exam eligibility' => fn () => route('siswa.exam-eligibility'),
]);

it('redirects TU login to the proven scoped Student listing', function () {
    $tu = readIsolationTu(SchoolLevel::SMA);

    $this->post(route('login'), [
        'email' => $tu->email,
        'password' => 'password',
    ])->assertRedirect(route('siswa.index'));

    $this->assertAuthenticatedAs($tu);
});

it('keeps admin and superadmin Student listing and billing detail access unchanged', function (string $role) {
    $class = readIsolationClass(SchoolLevel::SMP);
    $student = readIsolationStudent($class, 'Administrator Student');
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('siswa.index'))
        ->assertOk()
        ->assertSee($student->nama_lengkap);

    $this->get(route('siswa.show', $student))->assertOk();
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('keeps Student listing SELECT counts bounded as rows grow', function () {
    $class = readIsolationClass(SchoolLevel::SD);
    $tu = readIsolationTu(SchoolLevel::SD);
    $this->actingAs($tu);

    $measure = function (int $studentCount) use ($class): int {
        Student::query()->delete();

        Student::factory()->count($studentCount)->create(['class_id' => $class->id])->each(
            fn (Student $student) => readIsolationEnroll($student, $this->activeYear, $class)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(StudentManagement::class)->html();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
    };

    $measurements = [];

    foreach ([1, 10, 20, 50] as $studentCount) {
        $measurements[$studentCount] = $measure($studentCount);
    }

    expect(array_unique($measurements))->toHaveCount(1)
        ->and($measurements[1])->toBe(15);
});

it('denies an inactive TU before scoped Student access', function () {
    $tu = readIsolationTu(SchoolLevel::SD, ['is_active' => false]);

    $this->actingAs($tu)
        ->get(route('siswa.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
