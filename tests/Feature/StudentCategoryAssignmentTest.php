<?php

use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentCategory;
use App\Services\ClassPromotionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function categoryStudentData(SchoolClass $class, string $nis, string $name): array
{
    return [
        'nis' => $nis,
        'nama_lengkap' => $name,
        'nama_panggilan' => $name,
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Alamat test',
    ];
}

it('derives Siswa Normal when a student has no categories', function () {
    $student = Student::factory()->create();

    expect($student->isNormalCategoryState())->toBeTrue()
        ->and($student->categoryDisplayLabels()->all())->toBe([]);

    Livewire::test(StudentManagement::class)
        ->assertSee($student->nama_lengkap)
        ->assertSee('Siswa Normal');
});

it('creates a student with one category', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();

    $component = Livewire::test(StudentManagement::class)->call('openModal');

    foreach (categoryStudentData($class, 'CAT-001', 'Ahmad Kategori') as $field => $value) {
        $component->set($field, $value);
    }

    $component->set('categoryIds', [$category->id])->call('save')->assertHasNoErrors();

    expect(Student::query()->where('nis', 'CAT-001')->sole()->categories()->pluck('code')->all())
        ->toBe(['anak_guru']);
});

it('creates a student with multiple categories', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);
    $categories = StudentCategory::query()->whereIn('code', ['anak_guru', 'anak_yatim'])->get();
    $component = Livewire::test(StudentManagement::class)->call('openModal');

    foreach (categoryStudentData($class, 'CAT-002', 'Ahmad Dua Kategori') as $field => $value) {
        $component->set($field, $value);
    }

    $component->set('categoryIds', $categories->pluck('id')->all())->call('save')->assertHasNoErrors();

    expect(Student::query()->where('nis', 'CAT-002')->sole()->categories()->pluck('code')->sort()->values()->all())
        ->toBe(['anak_guru', 'anak_yatim']);
});

it('edits and removes student categories', function () {
    $student = Student::factory()->create();
    $teacher = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $scholarship = StudentCategory::query()->where('code', 'beasiswa')->sole();
    $student->categories()->attach($teacher);

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('categoryIds', [$teacher->id])
        ->set('categoryIds', [$scholarship->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($student->categories()->pluck('code')->all())->toBe(['beasiswa']);

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('categoryIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($student->categories()->exists())->toBeFalse()
        ->and($student->fresh()->isNormalCategoryState())->toBeTrue();
});

it('rejects assigning an inactive category while preserving an existing inactive assignment', function () {
    $inactive = StudentCategory::factory()->create(['name' => 'Kategori Lama', 'code' => 'kategori_lama', 'is_active' => false]);
    $student = Student::factory()->create();

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('categoryIds', [$inactive->id])
        ->call('save')
        ->assertHasErrors(['categoryIds.0']);

    $student->categories()->attach($inactive);

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('categoryIds', [])
        ->assertSee('Kategori Lama')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->categories()->whereKey($inactive->id)->exists())->toBeTrue();
});

it('prevents duplicate student category pivot pairs', function () {
    $student = Student::factory()->create();
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $student->categories()->attach($category);

    expect(fn () => $student->categories()->attach($category))->toThrow(QueryException::class);
});

it('renders multiple badges and inactive assigned categories on the student index', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Badge']);
    $categories = StudentCategory::query()->whereIn('code', ['anak_guru', 'anak_yatim'])->get();
    $inactive = StudentCategory::factory()->create(['name' => 'Kategori Historis', 'code' => 'kategori_historis', 'is_active' => false]);
    $student->categories()->attach([...$categories->pluck('id')->all(), $inactive->id]);

    $component = Livewire::test(StudentManagement::class)
        ->assertSee('Siswa Badge')
        ->assertSee('Anak Guru')
        ->assertSee('Anak Yatim')
        ->assertSee('Kategori Historis');

    expect(substr_count($component->html(), '<option value="kategori_historis">'))->toBe(0);
});

it('filters normal and each category independently', function () {
    $normal = Student::factory()->create(['nama_lengkap' => 'Budi Normal']);
    $multi = Student::factory()->create(['nama_lengkap' => 'Ahmad Multi']);
    $teacherOnly = Student::factory()->create(['nama_lengkap' => 'Guru Saja']);
    $teacher = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $orphan = StudentCategory::query()->where('code', 'anak_yatim')->sole();
    $multi->categories()->attach([$teacher->id, $orphan->id]);
    $teacherOnly->categories()->attach($teacher);

    Livewire::test(StudentManagement::class)
        ->set('filterCategory', 'normal')
        ->assertSee($normal->nama_lengkap)
        ->assertDontSee($multi->nama_lengkap)
        ->set('filterCategory', 'anak_guru')
        ->assertSee($multi->nama_lengkap)
        ->assertSee($teacherOnly->nama_lengkap)
        ->assertDontSee($normal->nama_lengkap)
        ->set('filterCategory', 'anak_yatim')
        ->assertSee($multi->nama_lengkap)
        ->assertDontSee($teacherOnly->nama_lengkap);
});

it('resets pagination when the category filter changes', function () {
    Student::factory()->count(11)->create();

    Livewire::test(StudentManagement::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('filterCategory', 'normal')
        ->assertSet('paginators.page', 1);
});

it('shows categories and Siswa Normal on student detail pages', function () {
    $categorized = Student::factory()->create(['nama_lengkap' => 'Detail Khusus']);
    $normal = Student::factory()->create(['nama_lengkap' => 'Detail Normal']);
    $teacher = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $categorized->categories()->attach($teacher);

    Livewire::test(StudentDetail::class, ['student' => $categorized])
        ->assertSee('Kategori Siswa')
        ->assertSee('Anak Guru');

    Livewire::test(StudentDetail::class, ['student' => $normal])
        ->assertSee('Kategori Siswa')
        ->assertSee('Siswa Normal');
});

it('deleting a student cascades category pivot rows', function () {
    $student = Student::factory()->create();
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $student->categories()->attach($category);
    $student->delete();

    expect(DB::table('student_category_student')->where('student_id', $student->id)->exists())->toBeFalse();
});

it('category assignment has no academic or financial side effects', function () {
    $student = Student::factory()->create();
    $category = StudentCategory::query()->where('code', 'beasiswa')->sole();
    $academicStatus = $student->academicStatus();
    $billCount = StudentBill::query()->where('student_id', $student->id)->count();
    $paymentCount = Payment::query()->where('student_id', $student->id)->count();

    $student->categories()->sync([$category->id]);

    expect($student->fresh()->academicStatus())->toBe($academicStatus)
        ->and(StudentBill::query()->where('student_id', $student->id)->count())->toBe($billCount)
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe($paymentCount);
});

it('promotion and graduation preserve category relations', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $fromYear = AcademicYear::query()->firstOrCreate(['year' => '2026/2027'], [
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'is_active' => false,
    ]);
    $fromYear->update(['is_active' => true]);
    $toYear = AcademicYear::query()->firstOrCreate(['year' => '2027/2028'], [
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
        'is_active' => false,
    ]);
    $class = SchoolClass::factory()->create(['level' => 12]);
    createPromotionRule($class, 'graduate');
    $student = Student::factory()->create(['class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $fromYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    $category = StudentCategory::query()->where('code', 'anak_yatim')->sole();
    $student->categories()->attach($category);

    app(ClassPromotionService::class)->processPromotion($fromYear, $toYear);

    expect($student->fresh()->getRawOriginal('status'))->toBe('lulus')
        ->and($student->categories()->whereKey($category->id)->exists())->toBeTrue();
});

it('eager loads categories without per-student query growth', function () {
    Student::factory()->count(10)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(StudentManagement::class)->assertOk();

    $categoryQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'student_category_student'))
        ->count();

    expect($categoryQueries)->toBeLessThanOrEqual(2);
});
