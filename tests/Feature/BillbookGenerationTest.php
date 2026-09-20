<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\BillGenerationService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;

function billbookStudent(int $level): Student
{
    $student = makeBillStudent($level);

    $student->paymentSettings()->update(['started_at' => '2026-01-01']);

    return $student;
}

function assertMonthlyBillExists(Student $student, int $paymentTypeId, int $month, int $year): void
{
    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $paymentTypeId)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->exists())->toBeTrue("Bill bulan $month/$year harus ada");
}

function assertNoMonthlyBillFor(Student $student, int $paymentTypeId, int $month, int $year): void
{
    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $paymentTypeId)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->exists())->toBeFalse("Bill bulan $month/$year tidak boleh ada");
}

function billbookPeriods(): array
{
    return [[9, 2026], [10, 2026], [11, 2026], [12, 2026], [1, 2027], [2, 2027], [3, 2027], [4, 2027], [5, 2027], [6, 2027]];
}

function billbookAugustPeriods(): array
{
    return [[8, 2026], [9, 2026], [10, 2026], [11, 2026], [12, 2026], [1, 2027], [2, 2027], [3, 2027], [4, 2027], [5, 2027], [6, 2027]];
}

beforeEach(function () {
    $this->travelTo('2026-08-15');
});

it('mulai September 2026 menghasilkan tagihan September sampai Juni 2027', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    foreach (billbookPeriods() as [$month, $year]) {
        assertMonthlyBillExists($student, $spp->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10);
});

it('mulai Agustus 2026 menghasilkan tagihan Agustus sampai Juni 2027', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-08-15'));

    foreach (billbookAugustPeriods() as [$month, $year]) {
        assertMonthlyBillExists($student, $spp->id, $month, $year);
    }

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(11);
});

it('Juni selalu menjadi bulan terakhir yang dihasilkan', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    assertMonthlyBillExists($student, $spp->id, 6, 2027);
    assertNoMonthlyBillFor($student, $spp->id, 7, 2027);
});

it('tidak menghasilkan bulan Juli ketika mulai September', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    assertNoMonthlyBillFor($student, $spp->id, 7, 2026);
    assertNoMonthlyBillFor($student, $spp->id, 7, 2027);
});

it('menjalankan generate buku tagihan dua kali tidak membuat duplikat', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10);
});

it('jenjang TK hanya menghasilkan SPP bulanan', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, -2, 300000);
    makeLevelDefault($spp, SchoolLevel::TK);

    $student = billbookStudent(-2);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10);
});

it('jenjang SD menghasilkan SPP dan Ekskul bulanan', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 4, 450000);
    makeLevelDefault($spp, SchoolLevel::SD);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, 4, 40000);
    makeLevelDefault($ekskul, SchoolLevel::SD);

    $student = billbookStudent(4);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $ekskul->id)
            ->count())->toBe(10);
});

it('jenjang SMP menghasilkan SPP, Ekskul, dan OSIS bulanan', function () {
    foreach (['SPP', 'Ekskul', 'OSIS'] as $name) {
        $type = makeBillType($name, auto: true, required: true);
        makeBillRate($type, 8, 970000);
        makeLevelDefault($type, SchoolLevel::SMP);
    }

    $student = billbookStudent(8);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    foreach (['SPP', 'Ekskul', 'OSIS'] as $name) {
        $typeId = PaymentType::where('name', $name)->first()->id;

        expect(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $typeId)
            ->count())->toBe(10, "$name harus muncul 10 kali (September 2026 - Juni 2027)");
    }
});

it('jenjang SMA menghasilkan SPP dan Ekskul bulanan', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 10, 1000000);
    makeLevelDefault($spp, SchoolLevel::SMA);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, 10, 70000);
    makeLevelDefault($ekskul, SchoolLevel::SMA);

    $student = billbookStudent(10);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(10)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $ekskul->id)
            ->count())->toBe(10);
});

it('menghasilkan Uang Buku sekali per tahun ajaran', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = billbookStudent(8);
    makeActiveSetting($student, $type);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bills = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->get();

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->academic_year)->toBe('2026/2027')
        ->and($bills->first()->period_month)->toBeNull();
});

it('menghasilkan Uang Kegiatan sekali per tahun ajaran', function () {
    $type = makeBillType('Uang Kegiatan');
    makeBillRate($type, 8, 100000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = billbookStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(1)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $type->id)
            ->first()
            ->academic_year)->toBe('2026/2027');
});

it('menghasilkan Uang Pangkal sekali tanpa periode', function () {
    $pangkal = makeBillType('Uang Pangkal');
    PaymentRate::factory()->create([
        'payment_type_id' => $pangkal->id,
        'class_level' => 8,
        'amount' => 5000000,
        'is_monthly' => false,
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);
    makeLevelDefault($pangkal, SchoolLevel::SMP, required: false);
    $student = billbookStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1)
        ->and($bill)->not->toBeNull()
        ->and((int) $bill->amount)->toBe(5000000)
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and($bill->academic_year)->toBe('2026/2027');
});

it('tidak menghasilkan layanan opsional seperti Jemputan secara otomatis', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = billbookStudent(8);
    makeActiveSetting($student, $jemputan);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe(10);
});

it('generate buku tagihan tidak menimpa nominal yang sudah diedit', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = billbookStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $september = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 9)
        ->where('period_year', 2026)
        ->first();

    $september->update(['amount' => 950000]);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $september->refresh();

    expect((float) $september->amount)->toBe(950000.0);
});

it('BillbookPeriod menghitung akhir siklus di bulan Juni', function () {
    expect(BillbookPeriod::endDateFor(Carbon::parse('2026-09-15'))->format('Y-m'))->toBe('2027-06')
        ->and(BillbookPeriod::endDateFor(Carbon::parse('2026-08-15'))->format('Y-m'))->toBe('2027-06')
        ->and(BillbookPeriod::endDateFor(Carbon::parse('2026-07-15'))->format('Y-m'))->toBe('2027-06')
        ->and(BillbookPeriod::endDateFor(Carbon::parse('2027-01-15'))->format('Y-m'))->toBe('2027-06');
});

it('BillbookPeriod menyimpan dan membaca bulan awal konfigurasi admin', function () {
    $this->travelTo('2026-08-15');

    expect(BillbookPeriod::startDate()->format('Y-m'))->toBe('2026-08');

    BillbookPeriod::setStartMonth(Carbon::parse('2026-09-01'));

    expect(BillbookPeriod::startDate()->format('Y-m'))->toBe('2026-09')
        ->and(Setting::get(BillbookPeriod::SETTING_START_MONTH))->toBe('2026-09');
});

it('BillbookPeriod menghasilkan daftar bulan dari awal sampai Juni', function () {
    $months = BillbookPeriod::months(Carbon::parse('2026-09-15'));

    expect(array_map(fn ($m) => $m->format('Y-m'), $months))
        ->toBe(['2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06']);
});
