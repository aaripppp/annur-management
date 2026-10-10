<?php

use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\Payment;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentCategory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function selectedStudentMetadataHtml(string $html): string
{
    preg_match('/<div data-testid="selected-student-metadata".*?<\/div>/s', $html, $matches);

    return $matches[0] ?? '';
}

it('renders initial category colors from the category code', function (string $code, string $expectedClasses) {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Warna '.$code]);
    $category = StudentCategory::query()->where('code', $code)->sole();
    $student->categories()->attach($category);

    $html = Livewire::test(StudentManagement::class)->html();

    expect($html)->toContain('data-category-code="'.$code.'"')
        ->and($html)->toContain($expectedClasses);
})->with([
    'anak guru kuning' => ['anak_guru', 'bg-yellow-50 text-yellow-800 border-yellow-200'],
    'anak yatim hijau' => ['anak_yatim', 'bg-green-50 text-green-800 border-green-200'],
    'beasiswa biru' => ['beasiswa', 'bg-blue-50 text-blue-800 border-blue-200'],
]);

it('uses a neutral fallback for an unknown category code', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Kategori Baru']);
    $category = StudentCategory::factory()->create([
        'name' => 'Kategori Baru',
        'code' => 'kategori_baru',
    ]);
    $student->categories()->attach($category);

    $html = Livewire::test(StudentDetail::class, ['student' => $student])->html();

    expect($html)->toContain('data-category-code="kategori_baru"')
        ->and($html)->toContain('bg-surface-container-high text-on-surface-variant border-outline-variant');
});

it('renders anak pegawai as yellow everywhere student category badges are shown', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Anak Pegawai']);
    $category = StudentCategory::factory()->create([
        'name' => 'Anak Pegawai',
        'code' => 'anak_pegawai',
    ]);
    $student->categories()->attach($category);

    $screens = [
        Livewire::test(StudentManagement::class)->html(),
        Livewire::test(StudentDetail::class, ['student' => $student])->html(),
        Livewire::test(PaymentIndex::class)->set('studentSearch', 'Siswa Anak Pegawai')->html(),
        Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id)->html(),
    ];

    foreach ($screens as $html) {
        expect($html)->toContain('data-category-code="anak_pegawai"')
            ->and($html)->toContain('bg-yellow-50 text-yellow-800 border-yellow-200');
    }
});

it('renders assigned categories beside a student name in payment search', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Alfarabi Hamizan Zaman']);
    $teacher = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $student->categories()->attach($teacher);

    $html = Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Alfarabi Hamizan')
        ->html();

    expect($html)->toContain('Alfarabi Hamizan Zaman')
        ->and($html)->toContain('data-category-code="anak_guru"')
        ->and($html)->toContain('bg-yellow-50 text-yellow-800 border-yellow-200');
});

it('renders multiple category badges in a payment student result', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Multi Pembayaran']);
    $categories = StudentCategory::query()
        ->whereIn('code', ['anak_guru', 'anak_yatim', 'beasiswa'])
        ->get();
    $student->categories()->attach($categories);

    $html = Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Siswa Multi')
        ->html();

    expect($html)->toContain('flex flex-wrap items-center gap-x-1.5 gap-y-1')
        ->and($html)->toContain('data-category-code="anak_guru"')
        ->and($html)->toContain('data-category-code="anak_yatim"')
        ->and($html)->toContain('data-category-code="beasiswa"');
});

it('does not render a category badge for a normal student payment result', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Budi Normal Pembayaran']);

    $html = Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Budi Normal')
        ->html();

    expect($html)->toContain($student->nama_lengkap)
        ->and($html)->not->toContain('data-category-code=');
});

it('leaves prospective student payment results without category badges', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Calon Tanpa Kategori',
    ]);

    $html = Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Calon Tanpa')
        ->html();

    expect($html)->toContain($prospectiveStudent->nama_lengkap)
        ->and($html)->toContain('Calon Siswa')
        ->and($html)->not->toContain('data-category-code=');
});

it('eager loads payment search categories without per-student query growth', function () {
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();

    Student::factory()->count(8)->sequence(
        fn (Sequence $sequence): array => ['nama_lengkap' => 'Cari Kategori '.($sequence->index + 1)]
    )->create()->each(function (Student $student) use ($category): void {
        $student->categories()->attach($category);
    });

    $component = Livewire::test(PaymentIndex::class);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $component->set('studentSearch', 'Cari Kategori');

    $categoryQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'student_category_student'))
        ->count();

    DB::disableQueryLog();
    DB::flushQueryLog();

    expect($categoryQueries)->toBeGreaterThan(0)
        ->and($categoryQueries)->toBeLessThanOrEqual(2);
});

it('renders selected-student category colors in the metadata row', function (string $code, string $expectedClasses) {
    $student = Student::factory()->create([
        'nama_lengkap' => 'Profil Warna '.$code,
        'nama_panggilan' => 'Profil',
        'jenis_kelamin' => 'L',
    ]);
    $category = StudentCategory::query()->where('code', $code)->sole();
    $student->categories()->attach($category);

    $html = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->html();
    $metadata = selectedStudentMetadataHtml($html);

    expect($html)->toContain($student->academic_status_label)
        ->and($metadata)->toContain('NIS '.$student->nis)
        ->and($metadata)->toContain('Laki-laki')
        ->and($metadata)->toContain('data-category-code="'.$code.'"')
        ->and($metadata)->toContain($expectedClasses);
})->with([
    'selected anak guru kuning' => ['anak_guru', 'bg-yellow-50 text-yellow-800 border-yellow-200'],
    'selected anak yatim hijau' => ['anak_yatim', 'bg-green-50 text-green-800 border-green-200'],
    'selected beasiswa biru' => ['beasiswa', 'bg-blue-50 text-blue-800 border-blue-200'],
]);

it('renders multiple selected-student categories together without moving the status badge', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Profil Multi Kategori']);
    $student->categories()->attach(
        StudentCategory::query()->whereIn('code', ['anak_guru', 'anak_yatim', 'beasiswa'])->get()
    );

    $html = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->html();
    $metadata = selectedStudentMetadataHtml($html);

    expect($html)->toContain('flex flex-wrap items-center gap-2')
        ->and($html)->toContain($student->academic_status_label)
        ->and($metadata)->toContain('flex flex-wrap items-center gap-1.5 min-w-0')
        ->and($metadata)->toContain('data-category-code="anak_guru"')
        ->and($metadata)->toContain('data-category-code="anak_yatim"')
        ->and($metadata)->toContain('data-category-code="beasiswa"');
});

it('does not show Siswa Normal in the selected payment profile metadata', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Profil Siswa Normal']);

    $html = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->html();
    $metadata = selectedStudentMetadataHtml($html);

    expect($metadata)->not->toContain('data-category-code=')
        ->and($metadata)->not->toContain('Siswa Normal');
});

it('keeps an inactive assigned category visible in the selected payment profile', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Profil Kategori Nonaktif']);
    $category = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $category->update(['is_active' => false]);
    $student->categories()->attach($category);

    $metadata = selectedStudentMetadataHtml(
        Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id)->html()
    );

    expect($metadata)->toContain('Anak Guru')
        ->and($metadata)->toContain('bg-yellow-50 text-yellow-800 border-yellow-200');
});

it('does not create or change financial records when selected category badges render', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Profil Aman Finansial']);
    $category = StudentCategory::query()->where('code', 'beasiswa')->sole();
    $student->categories()->attach($category);
    $billCount = StudentBill::query()->where('student_id', $student->id)->count();
    $paymentCount = Payment::query()->where('student_id', $student->id)->count();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Beasiswa');

    expect(StudentBill::query()->where('student_id', $student->id)->count())->toBe($billCount)
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe($paymentCount);
});
