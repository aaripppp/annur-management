<?php

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\ClassPromotionService;
use Database\Seeders\SchoolDataSeeder;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Facades\DB;

function runSeeder(): void
{
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-08-01'));

    try {
        (new TestingDataSeeder)->run();
    } finally {
        Carbon\Carbon::setTestNow();
    }
}

beforeEach(function () {
    (new SchoolDataSeeder)->run();

    AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $this->ratesBefore = PaymentRate::query()->orderBy('id')->get()
        ->map(fn ($r) => $r->toArray())->toArray();
    $this->typesBefore = PaymentType::query()->orderBy('id')->get()
        ->map(fn ($t) => $t->toArray())->toArray();
    $this->classCountBefore = SchoolClass::count();
    $this->bankCountBefore = Bank::count();
});

/*
|--------------------------------------------------------------------------
| Test 1: Seeder cannot run in production
|--------------------------------------------------------------------------
*/
it('cannot run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    (new TestingDataSeeder)->run();
})->throws(RuntimeException::class, 'TestingDataSeeder tidak boleh dijalankan di production.');

/*
|--------------------------------------------------------------------------
| Test 2: Existing PaymentRate values are unchanged
|--------------------------------------------------------------------------
*/
it('preserves existing PaymentRate values', function () {
    runSeeder();

    $ratesAfter = PaymentRate::query()->orderBy('id')->get()
        ->map(fn ($r) => $r->toArray())->toArray();

    expect($ratesAfter)->toEqual($this->ratesBefore);
});

/*
|--------------------------------------------------------------------------
| Test 3: Existing PaymentType rows are unchanged
|--------------------------------------------------------------------------
*/
it('preserves existing PaymentType rows', function () {
    runSeeder();

    $typesAfter = PaymentType::query()->orderBy('id')->get()
        ->map(fn ($t) => $t->toArray())->toArray();

    expect($typesAfter)->toEqual($this->typesBefore);
});

/*
|--------------------------------------------------------------------------
| Test 4: Existing SchoolClass rows are unchanged
|--------------------------------------------------------------------------
*/
it('preserves existing SchoolClass rows', function () {
    runSeeder();

    expect(SchoolClass::count())->toBe($this->classCountBefore);
});

/*
|--------------------------------------------------------------------------
| Test 5: Existing Bank rows are unchanged
|--------------------------------------------------------------------------
*/
it('preserves existing Bank rows', function () {
    runSeeder();

    expect(Bank::count())->toBe($this->bankCountBefore);
});

/*
|--------------------------------------------------------------------------
| Test 6: Old payments are removed
|--------------------------------------------------------------------------
*/
it('removes old payments', function () {
    runSeeder();

    expect(DB::table('payments')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 7: Old payment_details are removed
|--------------------------------------------------------------------------
*/
it('removes old payment_details', function () {
    runSeeder();

    expect(DB::table('payment_details')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 8: Old students are removed
|--------------------------------------------------------------------------
*/
it('removes old students', function () {
    runSeeder();

    $testStudents = Student::where('nis', 'like', 'TEST-%')->count();
    expect($testStudents)->toBe(SchoolClass::count());
});

/*
|--------------------------------------------------------------------------
| Test 9: Exactly 1 student is created per SchoolClass
|--------------------------------------------------------------------------
*/
it('creates exactly 1 student per SchoolClass', function () {
    runSeeder();

    foreach (SchoolClass::query()->get() as $class) {
        $count = Student::where('class_id', $class->id)->count();
        expect($count)->toBe(1);
    }
});

/*
|--------------------------------------------------------------------------
| Test 10: All created students status = aktif
|--------------------------------------------------------------------------
*/
it('creates all students with aktif status', function () {
    runSeeder();

    $nonAktif = Student::where('status', '!=', 'aktif')->count();
    expect($nonAktif)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 11: All created students have 2026/2027 enrollment
|--------------------------------------------------------------------------
*/
it('enrolls all students in 2026/2027', function () {
    runSeeder();

    $activeYear = AcademicYear::where('year', '2026/2027')->first();
    $enrolledCount = StudentAcademicEnrollment::where('academic_year_id', $activeYear->id)->count();
    expect($enrolledCount)->toBe(Student::count());
});

/*
|--------------------------------------------------------------------------
| Test 12: Enrollment class matches Student current class
|--------------------------------------------------------------------------
*/
it('matches enrollment class to student current class', function () {
    runSeeder();

    foreach (Student::with('enrollments')->get() as $student) {
        $enrollment = $student->enrollments->first();
        expect($enrollment)->not->toBeNull();
        expect($enrollment->school_class_id)->toBe($student->class_id);
    }
});

/*
|--------------------------------------------------------------------------
| Test 13: Enrollment status = active
|--------------------------------------------------------------------------
*/
it('creates all enrollments with active status', function () {
    runSeeder();

    $nonActive = StudentAcademicEnrollment::where('status', '!=', 'active')->count();
    expect($nonActive)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 14: Billbook is generated
|--------------------------------------------------------------------------
*/
it('generates billbook for all students', function () {
    runSeeder();

    $billCount = DB::table('student_bills')->count();
    expect($billCount)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Test 15: Bill amounts use existing PaymentRate amounts
|--------------------------------------------------------------------------
*/
it('uses existing PaymentRate amounts for bills', function () {
    runSeeder();

    $rates = PaymentRate::all()->keyBy(fn ($r) => $r->payment_type_id.'-'.$r->class_level);

    foreach (Student::with('bills.paymentType', 'schoolClass')->get() as $student) {
        $level = $student->schoolClass?->level;
        if ($level === null) {
            continue;
        }

        foreach ($student->bills as $bill) {
            $rateKey = $bill->payment_type_id.'-'.$level;
            $rate = $rates->get($rateKey);

            if ($rate) {
                expect((float) $bill->amount)->toBe((float) $rate->amount);
            }
        }
    }
});

/*
|--------------------------------------------------------------------------
| Test 16: Jemputan is not auto-generated as bill
|--------------------------------------------------------------------------
*/
it('does not auto-generate Jemputan bills', function () {
    runSeeder();

    $jemputanType = PaymentType::where('name', 'Jemputan')->first();
    if (! $jemputanType) {
        $this->markTestSkipped('Jemputan type not found.');
    }

    $billCount = DB::table('student_bills')
        ->where('payment_type_id', $jemputanType->id)
        ->count();
    expect($billCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 17: Lain-lain is not auto-generated as bill
|--------------------------------------------------------------------------
*/
it('does not auto-generate Lain-lain bills', function () {
    runSeeder();

    $lainType = PaymentType::where('name', 'Lain-lain')->first();
    if (! $lainType) {
        $this->markTestSkipped('Lain-lain type not found.');
    }

    $billCount = DB::table('student_bills')
        ->where('payment_type_id', $lainType->id)
        ->count();
    expect($billCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 18: Uang Pangkal generated according to one-time rule
|--------------------------------------------------------------------------
*/
it('generates exactly one Uang Pangkal bill per student', function () {
    runSeeder();

    $pangkalType = PaymentType::where('name', 'Uang Pangkal')->first();
    if (! $pangkalType) {
        $this->markTestSkipped('Uang Pangkal type not found.');
    }

    foreach (Student::with('schoolClass')->get() as $student) {
        $count = DB::table('student_bills')
            ->where('student_id', $student->id)
            ->where('payment_type_id', $pangkalType->id)
            ->count();
        expect($count)->toBe($student->schoolClass->level === -3 ? 0 : 1);
    }
});

/*
|--------------------------------------------------------------------------
| Test 19: payments count remains 0
|--------------------------------------------------------------------------
*/
it('ensures payments count is 0', function () {
    runSeeder();

    expect(DB::table('payments')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 20: payment_details count remains 0
|--------------------------------------------------------------------------
*/
it('ensures payment_details count is 0', function () {
    runSeeder();

    expect(DB::table('payment_details')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 21: 2026/2027 active
|--------------------------------------------------------------------------
*/
it('sets 2026/2027 as active', function () {
    runSeeder();

    $year = AcademicYear::where('year', '2026/2027')->first();
    expect($year)->not->toBeNull()
        ->and($year->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Test 22: 2027/2028 inactive
|--------------------------------------------------------------------------
*/
it('sets 2027/2028 as inactive', function () {
    runSeeder();

    $year = AcademicYear::where('year', '2027/2028')->first();
    expect($year)->not->toBeNull()
        ->and($year->is_active)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 23: 2027/2028 promotion_processed_at null
|--------------------------------------------------------------------------
*/
it('sets 2027/2028 promotion_processed_at to null', function () {
    runSeeder();

    $year = AcademicYear::where('year', '2027/2028')->first();
    expect($year)->not->toBeNull()
        ->and($year->promotion_processed_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Test 24: ClassPromotion preview detects seeded students
|--------------------------------------------------------------------------
*/
it('allows ClassPromotion preview to detect seeded students', function () {
    runSeeder();

    $activeYear = AcademicYear::where('year', '2026/2027')->first();
    $targetYear = AcademicYear::where('year', '2027/2028')->first();

    $service = app(ClassPromotionService::class);
    $preview = $service->getPreviewData($activeYear, $targetYear);

    expect($preview['students']->count())->toBe(Student::count());
    expect($preview['summary']['total_promoted'] + $preview['summary']['total_graduated'] + $preview['summary']['total_blocked'])
        ->toBe(Student::count());
});

/*
|--------------------------------------------------------------------------
| Test 25: Running seeder again returns the same clean state
|--------------------------------------------------------------------------
*/
it('returns same clean state on second run', function () {
    runSeeder();

    $firstStudents = Student::count();
    $firstBills = DB::table('student_bills')->count();
    $firstEnrollments = DB::table('student_academic_enrollments')->count();

    runSeeder();

    expect(Student::count())->toBe($firstStudents);
    expect(DB::table('student_bills')->count())->toBe($firstBills);
    expect(DB::table('student_academic_enrollments')->count())->toBe($firstEnrollments);
});

/*
|--------------------------------------------------------------------------
| Test 26: Tariffs still unchanged after second run
|--------------------------------------------------------------------------
*/
it('preserves tariffs unchanged after second run', function () {
    runSeeder();
    runSeeder();

    $ratesAfter = PaymentRate::query()->orderBy('id')->get()
        ->map(fn ($r) => $r->toArray())->toArray();

    expect($ratesAfter)->toEqual($this->ratesBefore);
});
