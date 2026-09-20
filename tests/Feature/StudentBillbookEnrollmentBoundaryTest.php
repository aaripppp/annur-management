<?php

use App\Enums\BillFrequency;
use App\Enums\StudentStatus;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use Livewire\Livewire;

function boundaryYear(string $year, bool $active = false): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);

    return AcademicYear::firstOrCreate(
        ['year' => $year],
        [
            'is_active' => $active,
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
        ]
    );
}

function enrollForYear(Student $student, AcademicYear $year, string $status = 'active'): StudentAcademicEnrollment
{
    return StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $student->class_id,
        'status' => $status,
    ]);
}

function boundaryStudent(int $level = 7): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);

    return Student::factory()->create(['class_id' => $class->id]);
}

function graduatedStudent(): array
{
    $y26 = boundaryYear('2026/2027', true);
    $y27 = boundaryYear('2027/2028');
    $y28 = boundaryYear('2028/2029');
    $y29 = boundaryYear('2029/2030');
    $y30 = boundaryYear('2030/2031');

    $student = boundaryStudent(7);
    enrollForYear($student, $y26);
    enrollForYear($student, $y27);
    enrollForYear($student, $y28);
    enrollForYear($student, $y29, 'lulus');
    $student->update(['status' => StudentStatus::Graduated]);

    return [$student, $y26, $y27, $y28, $y29, $y30];
}

it('keeps the one-time bill visible only within the enrollment period and never duplicates it', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 10000000, '2026/2027');

    expect(StudentBill::where('student_id', $student->id)
        ->where('billing_frequency', BillFrequency::OneTime->value)
        ->count())->toBe(1);

    foreach (['2026/2027', '2027/2028', '2028/2029'] as $year) {
        Livewire::test(StudentDetail::class, ['student' => $student])
            ->set('selectedAcademicYear', $year)
            ->assertSee('Uang Pangkal')
            ->assertSee('Rp 10.000.000');
    }

    foreach (['2029/2030', '2030/2031'] as $year) {
        Livewire::test(StudentDetail::class, ['student' => $student])
            ->set('selectedAcademicYear', $year)
            ->assertDontSee('Uang Pangkal')
            ->assertSee('Belum ada tagihan untuk siswa ini');
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('billing_frequency', BillFrequency::OneTime->value)
        ->count())->toBe(1);
});

it('does not carry an old yearly bill into a post-enrollment academic year', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 7, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);
    makeYearlyBill($student, $buku, 500000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Tahun Ajaran 2026/2027')
        ->assertSee('Uang Buku');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->assertDontSee('Uang Buku')
        ->assertSee('Belum ada tagihan untuk siswa ini');
});

it('keeps normal monthly period behavior in enrolled years and carries nothing into a post-enrollment year', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 975000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 975000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Rp 975.000');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->assertDontSee('Tagihan Agustus 2026')
        ->assertDontSee('Rp 975.000')
        ->assertSee('Belum ada tagihan untuk siswa ini');
});

it('keeps historical billbook access for every school year after graduation', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 10000000, '2026/2027');

    foreach (['2026/2027', '2027/2028', '2028/2029'] as $year) {
        Livewire::test(StudentDetail::class, ['student' => $student])
            ->set('selectedAcademicYear', $year)
            ->assertSee('Uang Pangkal');
    }

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->assertDontSee('Uang Pangkal');
});

it('exposes the historical one-time bill through the Semua views after graduation', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 10000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '')
        ->assertSee('Uang Pangkal')
        ->assertSee('Rp 10.000.000');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029')
        ->set('summaryCategory', 'all')
        ->assertSee('Uang Pangkal');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->set('summaryCategory', 'all')
        ->assertDontSee('Uang Pangkal');
});

it('does not mutate or delete an unpaid one-time bill when a post-enrollment year is selected', function () {
    [$student, $y26, $y27, $y28, $y29, $y30] = graduatedStudent();

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 10000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    $bill = makeOneTimeBill($student, $pangkal, 10000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->assertDontSee('Uang Pangkal');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Uang Pangkal')
        ->assertSee('Rp 10.000.000');

    $bill->refresh();

    expect((float) $bill->amount)->toBe(10000000.0)
        ->and((float) $bill->paid_amount)->toBe(0.0)
        ->and((float) $bill->remaining_amount)->toBe(10000000.0);
});

it('keeps candidate billing visible in the candidate entry year', function () {
    $y26 = boundaryYear('2026/2027', true);
    $y27 = boundaryYear('2027/2028');
    boundaryYear('2028/2029');

    $student = boundaryStudent(7);
    enrollForYear($student, $y27);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2027/2028');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2027/2028')
        ->assertSee('Uang Pangkal')
        ->assertSee('Rp 5.000.000');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2028/2029')
        ->assertDontSee('Uang Pangkal');
});

it('preserves legacy carry-forward for students without any enrollment history', function () {
    boundaryYear('2026/2027', true);
    boundaryYear('2030/2031');

    $student = boundaryStudent(7);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2030/2031')
        ->assertSee('Uang Pangkal');
});
