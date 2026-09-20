<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentManagement;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use App\Services\StudentCreationService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Livewire\Livewire;

function autoBillbookPeriods(): array
{
    return [[9, 2026], [10, 2026], [11, 2026], [12, 2026], [1, 2027], [2, 2027], [3, 2027], [4, 2027], [5, 2027], [6, 2027]];
}

function autoBillbookAugustPeriods(): array
{
    return [[8, 2026], [9, 2026], [10, 2026], [11, 2026], [12, 2026], [1, 2027], [2, 2027], [3, 2027], [4, 2027], [5, 2027], [6, 2027]];
}

function assertAutoMonthlyBillExists(Student $student, int $paymentTypeId, int $month, int $year): void
{
    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $paymentTypeId)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->exists())->toBeTrue("Bill bulan $month/$year harus ada");
}

function assertNoAutoMonthlyBill(Student $student, int $paymentTypeId, int $month, int $year): void
{
    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $paymentTypeId)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->exists())->toBeFalse("Bill bulan $month/$year tidak boleh ada");
}

function createStudentWithBillbook(int $level): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);

    return app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Baru',
        'nama_panggilan' => 'Baru',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
    ]);
}

it('membuat siswa TK baru menghasilkan SPP otomatis dari September sampai Juni', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, -2, 300000);
    makeLevelDefault($spp, SchoolLevel::TK);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(-2);

    foreach (autoBillbookPeriods() as [$month, $year]) {
        assertAutoMonthlyBillExists($student, $spp->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10);
});

it('membuat siswa SD baru menghasilkan SPP dan Ekskul otomatis dari September sampai Juni', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 4, 450000);
    makeLevelDefault($spp, SchoolLevel::SD);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, 4, 45000);
    makeLevelDefault($ekskul, SchoolLevel::SD);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(4);

    foreach (autoBillbookPeriods() as [$month, $year]) {
        assertAutoMonthlyBillExists($student, $spp->id, $month, $year);
        assertAutoMonthlyBillExists($student, $ekskul->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $ekskul->id)
            ->count())->toBe(10);
});

it('membuat siswa SMP baru menghasilkan SPP, Ekskul, dan OSIS otomatis dari September sampai Juni', function () {
    $this->travelTo('2026-08-15');

    foreach (['SPP', 'Ekskul', 'OSIS'] as $name) {
        $type = makeBillType($name, auto: true, required: true);
        makeBillRate($type, 8, 970000);
        makeLevelDefault($type, SchoolLevel::SMP);
    }

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    foreach (['SPP', 'Ekskul', 'OSIS'] as $name) {
        $typeId = PaymentType::where('name', $name)->first()->id;

        foreach (autoBillbookPeriods() as [$month, $year]) {
            assertAutoMonthlyBillExists($student, $typeId, $month, $year);
        }

        expect(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $typeId)
            ->count())->toBe(10, "$name harus muncul 10 kali (September 2026 - Juni 2027)");
    }
});

it('membuat siswa SMA baru menghasilkan SPP dan Ekskul otomatis dari September sampai Juni', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 10, 1000000);
    makeLevelDefault($spp, SchoolLevel::SMA);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, 10, 70000);
    makeLevelDefault($ekskul, SchoolLevel::SMA);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(10);

    foreach (autoBillbookPeriods() as [$month, $year]) {
        assertAutoMonthlyBillExists($student, $spp->id, $month, $year);
        assertAutoMonthlyBillExists($student, $ekskul->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $ekskul->id)
            ->count())->toBe(10);
});

it('siswa baru mendapatkan Uang Buku, Uang Kegiatan, dan Uang Pangkal otomatis', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $kegiatan = makeBillType('Uang Kegiatan');
    makeBillRate($kegiatan, 8, 100000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    $bukuBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $buku->id)
        ->first();
    $kegiatanBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $kegiatan->id)
        ->first();
    $pangkalBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $buku->id)
        ->count())->toBe(1)
        ->and($bukuBill)->not->toBeNull()
        ->and($bukuBill->academic_year)->toBe('2026/2027')
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $kegiatan->id)
            ->count())->toBe(1)
        ->and($kegiatanBill)->not->toBeNull()
        ->and($kegiatanBill->academic_year)->toBe('2026/2027')
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pangkal->id)
            ->count())->toBe(1)
        ->and($pangkalBill)->not->toBeNull()
        ->and($pangkalBill->period_month)->toBeNull()
        ->and($pangkalBill->period_year)->toBeNull()
        ->and($pangkalBill->academic_year)->toBe('2026/2027');
});

it('siswa baru tidak mendapatkan Jemputan secara otomatis', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0)
        ->and(StudentPaymentSetting::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->exists())->toBeFalse()
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe(10);
});

it('mulai Agustus menghasilkan buku tagihan otomatis dari Agustus sampai Juni', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-08-01'));

    $student = createStudentWithBillbook(8);

    foreach (autoBillbookAugustPeriods() as [$month, $year]) {
        assertAutoMonthlyBillExists($student, $spp->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(11);
});

it('tidak ada pemotongan di bulan Desember: Juni selalu bulan terakhir', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    assertAutoMonthlyBillExists($student, $spp->id, 12, 2026);
    assertAutoMonthlyBillExists($student, $spp->id, 1, 2027);
    assertAutoMonthlyBillExists($student, $spp->id, 6, 2027);
    assertNoAutoMonthlyBill($student, $spp->id, 7, 2027);
});

it('generator manual dan generator otomatis menggunakan logika yang sama', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    $before = StudentBill::where('student_id', $student->id)->count();

    $created = app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect($created)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)->count())->toBe($before);
});

it('menjalankan pembuatan buku tagihan dua kali tidak membuat duplikat', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $buku->id)
            ->count())->toBe(1)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pangkal->id)
            ->count())->toBe(1);
});

it('buku tagihan otomatis tidak menimpa nominal yang sudah diedit', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $student = createStudentWithBillbook(8);

    $september = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 9)
        ->where('period_year', 2026)
        ->first();

    $september->update(['amount' => 950000]);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect((float) $september->refresh()->amount)->toBe(950000.0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe(10);
});

it('membuat siswa lewat Student Management menghasilkan buku tagihan otomatis', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    $class = SchoolClass::factory()->create(['level' => 8]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', '20269999')
        ->set('nama_lengkap', 'Siswa Baru Auto')
        ->set('nama_panggilan', 'Baru')
        ->set('class_id', $class->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Auto No. 1')
        ->call('save');

    $student = Student::where('nis', '20269999')->first();

    expect($student)->not->toBeNull();

    foreach (autoBillbookPeriods() as [$month, $year]) {
        assertAutoMonthlyBillExists($student, $spp->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10);
});
