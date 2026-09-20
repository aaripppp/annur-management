<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExam;
use App\Models\StudentExamRequirement;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\StudentExamEligibilityService;

function makeStudentExam(string $name = 'UTS Semester Ganjil'): StudentExam
{
    [, , $academicYear] = makeEnrolledStudent(SchoolLevel::TK);

    return StudentExam::factory()->create([
        'name' => $name,
        'academic_year_id' => $academicYear->id,
    ]);
}

function makeExamRequirement(
    StudentExam $exam,
    PaymentType $type,
    SchoolLevel $level,
    BillFrequency $frequency,
    float $percentage,
    ?string $startMonth = null,
    ?string $endMonth = null,
): StudentExamRequirement {
    return StudentExamRequirement::factory()->create([
        'student_exam_id' => $exam->id,
        'school_level' => $level,
        'payment_type_id' => $type->id,
        'billing_frequency' => $frequency,
        'start_month' => $startMonth,
        'end_month' => $endMonth,
        'required_percentage' => $percentage,
    ]);
}

function makeApplicableExamType(
    Student $student,
    string $name,
    SchoolLevel $level,
    int $classLevel,
    BillFrequency $frequency = BillFrequency::Monthly,
    bool $required = true,
): PaymentType {
    $type = makeBillType($name);
    makeLevelDefault($type, $level, $required);
    makeBillRate($type, $classLevel, 100_000, ['billing_frequency' => $frequency]);

    if ($required) {
        makeActiveSetting($student, $type);
    }

    return $type;
}

function createExamBillAllocation(
    StudentBill $bill,
    float $amount,
    string $paymentDate = '2026-09-15',
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $user = User::factory()->create();
    $bank = Bank::query()->first() ?? Bank::factory()->cash()->create();
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-UJIAN-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);

    return $payment;
}

it('uses explicit per-level requirements and ignores unselected unpaid bills', function () {
    $exam = makeStudentExam();
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $smpRequired = makeApplicableExamType($smpStudent, 'Organisasi SMP', SchoolLevel::SMP, 8);
    $sdRequired = makeApplicableExamType($sdStudent, 'Iuran SD', SchoolLevel::SD, 5);
    $unselectedTransport = makeApplicableExamType($smpStudent, 'Transportasi Opsional', SchoolLevel::SMP, 8);
    makeExamRequirement($exam, $smpRequired, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-08-01');
    makeExamRequirement($exam, $sdRequired, SchoolLevel::SD, BillFrequency::Monthly, 100, '2026-08-01', '2026-08-01');
    $smpBill = makeMonthlyBill($smpStudent, $smpRequired, 100_000);
    $sdBill = makeMonthlyBill($sdStudent, $sdRequired, 100_000);
    makeMonthlyBill($smpStudent, $unselectedTransport, 500_000);
    createExamBillAllocation($smpBill, 100_000);
    createExamBillAllocation($sdBill, 100_000);

    $service = app(StudentExamEligibilityService::class);
    $smpResult = $service->evaluate($exam, $smpStudent);
    $sdResult = $service->evaluate($exam, $sdStudent);

    expect($smpResult['is_eligible'])->toBeTrue()
        ->and(collect($smpResult['requirements'])->pluck('payment_type_id'))->toContain($smpRequired->id)
        ->and(collect($smpResult['requirements'])->pluck('payment_type_id'))->not->toContain($unselectedTransport->id)
        ->and($sdResult['is_eligible'])->toBeTrue()
        ->and(collect($sdResult['requirements'])->pluck('payment_type_id'))->toContain($sdRequired->id)
        ->and(collect($sdResult['requirements'])->pluck('payment_type_id'))->not->toContain($smpRequired->id);
});

it('evaluates monthly periods independently and recognizes later allocations by bill id', function () {
    $exam = makeStudentExam('UTS Bulanan Mandiri');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeApplicableExamType($student, 'Iuran Bulanan Ujian', SchoolLevel::SMP, 8);
    makeExamRequirement($exam, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-09-01');
    $augustBill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    $septemberBill = makeMonthlyBill($student, $type, 100_000, 9, 2026);
    createExamBillAllocation($augustBill, 200_000, '2026-08-20');

    $before = app(StudentExamEligibilityService::class)->evaluate($exam, $student);
    $august = collect($before['requirements'])->firstWhere('period_key', '2026-08');
    $september = collect($before['requirements'])->firstWhere('period_key', '2026-09');

    expect($before['is_eligible'])->toBeFalse()
        ->and($august['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($august['paid'])->toBe(100_000.0)
        ->and($september['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and($before['progress_percentage'])->toBe(50.0);

    createExamBillAllocation($septemberBill, 100_000, '2026-10-10');
    $after = app(StudentExamEligibilityService::class)->evaluate($exam->fresh(), $student);

    expect($after['is_eligible'])->toBeTrue()
        ->and(collect($after['requirements'])->firstWhere('period_key', '2026-09')['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($after['progress_percentage'])->toBe(100.0);
});

it('uses adjusted effective targets and applies yearly percentage thresholds precisely', function () {
    $exam = makeStudentExam('UTS Tahunan');
    [$passingStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$failingStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeApplicableExamType($passingStudent, 'Iuran Tahunan Ujian', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    makeActiveSetting($failingStudent, $type);
    makeExamRequirement($exam, $type, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $passingBill = makeYearlyBill($passingStudent, $type, 1_000_000);
    $failingBill = makeYearlyBill($failingStudent, $type, 1_000_000);
    BillAdjustment::factory()->create(['bill_id' => $passingBill->id, 'amount' => -200_000]);
    createExamBillAllocation($passingBill, 400_000);
    createExamBillAllocation($failingBill, 499_900);

    $service = app(StudentExamEligibilityService::class);
    $passing = $service->evaluate($exam, $passingStudent);
    $failing = $service->evaluate($exam, $failingStudent);

    expect($passing['requirements'][0]['target'])->toBe(800_000.0)
        ->and($passing['requirements'][0]['percentage'])->toBe(50.0)
        ->and($passing['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($passing['is_eligible'])->toBeTrue()
        ->and($failing['requirements'][0]['percentage'])->toBe(49.99)
        ->and($failing['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and($failing['is_eligible'])->toBeFalse();
});

it('excludes cancelled allocations and distinguishes not applicable from missing bills', function () {
    $exam = makeStudentExam('UTS Status Aman');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $cancelledType = makeApplicableExamType($student, 'Iuran Dibatalkan', SchoolLevel::SMP, 8);
    $missingType = makeApplicableExamType($student, 'Iuran Hilang', SchoolLevel::SMP, 8);
    $optionalType = makeApplicableExamType($student, 'Iuran Tidak Berlaku', SchoolLevel::SMP, 8, required: false);
    makeExamRequirement($exam, $cancelledType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-09-01', '2026-09-01');
    makeExamRequirement($exam, $missingType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-09-01', '2026-09-01');
    makeExamRequirement($exam, $optionalType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-09-01', '2026-09-01');
    $cancelledBill = makeMonthlyBill($student, $cancelledType, 100_000, 9, 2026);
    createExamBillAllocation($cancelledBill, 100_000, status: Payment::STATUS_CANCELLED);

    $daycare = DaycarePayment::factory()->create(['total_amount' => 900_000]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Pembayaran Daycare Ujian',
        'amount' => 900_000,
    ]);

    $result = app(StudentExamEligibilityService::class)->evaluate($exam, $student);
    $statuses = collect($result['requirements'])->pluck('status', 'payment_type_name');

    expect($statuses['Iuran Dibatalkan'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and($statuses['Iuran Hilang'])->toBe(StudentExamEligibilityService::STATUS_MISSING_BILL)
        ->and($statuses['Iuran Tidak Berlaku'])->toBe(StudentExamEligibilityService::STATUS_NOT_APPLICABLE)
        ->and($result['applicable_count'])->toBe(2)
        ->and($result['satisfied_count'])->toBe(0)
        ->and($result['progress_percentage'])->toBe(0.0)
        ->and($result['is_eligible'])->toBeFalse()
        ->and($result['unmet_reasons'])->toContain('Tagihan Iuran Hilang September 2026 seharusnya berlaku tetapi StudentBill belum tersedia.')
        ->and(collect($result['requirements'])->sum('paid'))->toBe(0.0);
});

it('evaluates explicitly pooled yearly types as one capped cent-precise requirement', function () {
    $exam = makeStudentExam('UTS Syarat Gabungan');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $firstType = makeApplicableExamType($student, 'Dana Akademik', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $secondType = makeApplicableExamType($student, 'Dana Program', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $optionalType = makeApplicableExamType($student, 'Dana Opsional', SchoolLevel::SMP, 8, BillFrequency::Yearly, required: false);
    $requirement = makeExamRequirement($exam, $firstType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $requirement->pooledPaymentTypes()->attach([$firstType->id, $secondType->id, $optionalType->id]);
    $firstBill = makeYearlyBill($student, $firstType, 100_000);
    $secondBill = makeYearlyBill($student, $secondType, 300_000);
    BillAdjustment::factory()->create(['bill_id' => $secondBill->id, 'amount' => -100_000]);
    createExamBillAllocation($firstBill, 200_000);

    $service = app(StudentExamEligibilityService::class);
    $unpaid = $service->evaluate($exam->fresh(), $student);
    $unpaidRequirement = $unpaid['requirements'][0];

    expect($unpaid['applicable_count'])->toBe(1)
        ->and($unpaidRequirement['is_pooled'])->toBeTrue()
        ->and($unpaidRequirement['target'])->toBe(300_000.0)
        ->and($unpaidRequirement['paid'])->toBe(100_000.0)
        ->and($unpaidRequirement['required_amount'])->toBe(150_000.0)
        ->and($unpaidRequirement['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and(collect($unpaidRequirement['member_results'])->firstWhere('payment_type_id', $optionalType->id)['status'])
        ->toBe(StudentExamEligibilityService::STATUS_NOT_APPLICABLE);

    createExamBillAllocation($secondBill, 49_999.99);
    $oneCentShort = $service->evaluate($exam->fresh(), $student);

    expect($oneCentShort['requirements'][0]['paid'])->toBe(149_999.99)
        ->and($oneCentShort['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL);

    createExamBillAllocation($secondBill, 0.01);
    $passing = $service->evaluate($exam->fresh(), $student);

    expect($passing['requirements'][0]['paid'])->toBe(150_000.0)
        ->and($passing['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($passing['is_eligible'])->toBeTrue();

    $missingType = makeApplicableExamType($student, 'Dana Wajib Tanpa Tagihan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $requirement->pooledPaymentTypes()->attach($missingType->id);
    $missing = $service->evaluate($exam->fresh(), $student);

    expect($missing['requirements'])->toHaveCount(1)
        ->and($missing['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_MISSING_BILL)
        ->and($missing['is_eligible'])->toBeFalse()
        ->and($missing['unmet_reasons'][0])->toContain('Dana Wajib Tanpa Tagihan');
});

it('keeps the fifty percent pooled example as one evaluation without mutating billing data', function () {
    $exam = makeStudentExam('UTS Contoh Gabungan Lima Puluh Persen');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $firstType = makeApplicableExamType($student, 'Komponen Buku', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $secondType = makeApplicableExamType($student, 'Komponen Kegiatan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $pooledRequirement = makeExamRequirement($exam, $firstType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $pooledRequirement->pooledPaymentTypes()->attach([$firstType->id, $secondType->id]);
    makeExamRequirement($exam, $secondType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $firstBill = makeYearlyBill($student, $firstType, 1_000_000);
    $secondBill = makeYearlyBill($student, $secondType, 1_000_000);
    createExamBillAllocation($firstBill, 1_000_000);
    $billStateBefore = StudentBill::query()
        ->whereKey([$firstBill->id, $secondBill->id])
        ->orderBy('id')
        ->get(['id', 'amount', 'billing_frequency'])
        ->toArray();
    $paymentDetailStateBefore = PaymentDetail::query()
        ->whereIn('bill_id', [$firstBill->id, $secondBill->id])
        ->orderBy('id')
        ->get(['id', 'bill_id', 'payment_type_id', 'amount'])
        ->toArray();

    $result = app(StudentExamEligibilityService::class)->evaluate($exam->fresh(), $student);

    expect($result['requirements'])->toHaveCount(1)
        ->and($result['requirements'][0]['is_pooled'])->toBeTrue()
        ->and($result['requirements'][0]['target'])->toBe(2_000_000.0)
        ->and($result['requirements'][0]['paid'])->toBe(1_000_000.0)
        ->and($result['requirements'][0]['required_amount'])->toBe(1_000_000.0)
        ->and($result['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($result['is_eligible'])->toBeTrue()
        ->and(StudentBill::query()->whereKey([$firstBill->id, $secondBill->id])->orderBy('id')->get(['id', 'amount', 'billing_frequency'])->toArray())->toBe($billStateBefore)
        ->and(PaymentDetail::query()->whereIn('bill_id', [$firstBill->id, $secondBill->id])->orderBy('id')->get(['id', 'bill_id', 'payment_type_id', 'amount'])->toArray())->toBe($paymentDetailStateBefore);
});

it('ignores a disabled pooled requirement while evaluating other active requirements', function () {
    $exam = makeStudentExam('UTS Gabungan Nonaktif');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $firstPooledType = makeApplicableExamType($student, 'Komponen Nonaktif Pertama', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $secondPooledType = makeApplicableExamType($student, 'Komponen Nonaktif Kedua', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $activeType = makeApplicableExamType($student, 'Komponen Aktif', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $pooledRequirement = makeExamRequirement($exam, $firstPooledType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $pooledRequirement->update(['is_active' => false]);
    $pooledRequirement->pooledPaymentTypes()->attach([$firstPooledType->id, $secondPooledType->id]);
    makeExamRequirement($exam, $secondPooledType, SchoolLevel::SMP, BillFrequency::Yearly, 100);
    makeExamRequirement($exam, $activeType, SchoolLevel::SMP, BillFrequency::Yearly, 100);
    makeYearlyBill($student, $firstPooledType, 1_000_000);
    makeYearlyBill($student, $secondPooledType, 1_000_000);
    $activeBill = makeYearlyBill($student, $activeType, 100_000);
    createExamBillAllocation($activeBill, 100_000);

    $result = app(StudentExamEligibilityService::class)->evaluate($exam->fresh(), $student);

    expect($result['requirements'])->toHaveCount(1)
        ->and($result['requirements'][0]['payment_type_id'])->toBe($activeType->id)
        ->and($result['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($result['is_eligible'])->toBeTrue();
});

it('expects a non-monthly bill when its rate starts later in the academic year', function () {
    $exam = makeStudentExam('UTS Tarif Pertengahan Tahun');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeApplicableExamType($student, 'Program Semester Genap', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $type->rates()->update(['effective_from' => '2027-01-01']);
    makeExamRequirement($exam, $type, SchoolLevel::SMP, BillFrequency::Yearly, 50);

    $result = app(StudentExamEligibilityService::class)->evaluate($exam, $student);

    expect($result['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_MISSING_BILL)
        ->and($result['applicable_count'])->toBe(1)
        ->and($result['is_eligible'])->toBeFalse();
});

it('excludes a non-monthly type when its rate and student setting windows do not overlap', function () {
    $exam = makeStudentExam('UTS Jendela Tidak Beririsan');
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeApplicableExamType($student, 'Program Berjangka', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $type->rates()->update(['effective_until' => '2026-12-31']);
    StudentPaymentSetting::query()
        ->where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->update(['started_at' => '2027-01-01']);
    makeExamRequirement($exam, $type, SchoolLevel::SMP, BillFrequency::Yearly, 50);

    $result = app(StudentExamEligibilityService::class)->evaluate($exam, $student);

    expect($result['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_NOT_APPLICABLE)
        ->and($result['applicable_count'])->toBe(0);
});

it('scopes config-owned pooled yearly eligibility strictly to the selected academic year including promotion targets', function () {
    $activeYear = AcademicYear::active();
    $promotionYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $class7 = SchoolClass::factory()->create(['level' => 7]);
    $class8 = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class8->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $promotionYear->id,
        'school_class_id' => $class8->id,
        'status' => 'active',
    ]);

    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $book = makeApplicableExamType($student, 'Uang Buku Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $activity = makeApplicableExamType($student, 'Uang Kegiatan Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $pooledRequirement = StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $book->id,
        'billing_frequency' => BillFrequency::Yearly,
        'start_month' => null,
        'end_month' => null,
        'required_percentage' => 50,
        'is_active' => true,
    ]);
    $pooledRequirement->pooledPaymentTypes()->attach([$book->id, $activity->id]);

    makeYearlyBill($student, $book, 1_000_000, '2026/2027');
    makeYearlyBill($student, $activity, 1_000_000, '2026/2027');
    $bookBill2728 = makeYearlyBill($student, $book, 1_200_000, '2027/2028');
    makeYearlyBill($student, $activity, 1_200_000, '2027/2028');

    $requirements = collect([$pooledRequirement->fresh()]);
    $service = app(StudentExamEligibilityService::class);

    $priorYear = $service->evaluateCriteria($activeYear, $requirements, $student);

    expect($priorYear['academic_year'])->toBe('2026/2027')
        ->and($priorYear['requirements'])->toHaveCount(1)
        ->and($priorYear['requirements'][0]['target'])->toBe(2_000_000.0)
        ->and($priorYear['requirements'][0]['paid'])->toBe(0.0)
        ->and($priorYear['requirements'][0]['required_amount'])->toBe(1_000_000.0);

    $promotion = $service->evaluateCriteria($promotionYear, $requirements, $student);

    expect($promotion['academic_year'])->toBe('2027/2028')
        ->and($promotion['requirements'][0]['target'])->toBe(2_400_000.0)
        ->and($promotion['requirements'][0]['paid'])->toBe(0.0)
        ->and($promotion['requirements'][0]['required_amount'])->toBe(1_200_000.0)
        ->and($promotion['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and($promotion['is_eligible'])->toBeFalse();

    createExamBillAllocation($bookBill2728, 1_200_000, '2028-09-15');

    $afterAllocation = $service->evaluateCriteria($promotionYear, $requirements, $student);

    expect($afterAllocation['requirements'][0]['paid'])->toBe(1_200_000.0)
        ->and($afterAllocation['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($afterAllocation['is_eligible'])->toBeTrue()
        ->and(collect($afterAllocation['non_monthly_requirements'])->first()['member_results'])->toHaveCount(2);
});

it('scopes config-owned independent yearly requirements to the selected academic year', function () {
    $activeYear = AcademicYear::active();
    $selectedYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $class = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $selectedYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makeApplicableExamType($student, 'Iuran Tahunan Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $requirement = StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $type->id,
        'billing_frequency' => BillFrequency::Yearly,
        'start_month' => null,
        'end_month' => null,
        'required_percentage' => 50,
        'is_active' => true,
    ]);
    $priorBill = makeYearlyBill($student, $type, 600_000, '2026/2027');
    makeYearlyBill($student, $type, 800_000, '2027/2028');
    createExamBillAllocation($priorBill, 600_000);

    $result = app(StudentExamEligibilityService::class)->evaluateCriteria(
        $selectedYear,
        collect([$requirement->fresh()]),
        $student,
    );

    expect($result['requirements'][0]['target'])->toBe(800_000.0)
        ->and($result['requirements'][0]['paid'])->toBe(0.0)
        ->and($result['requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL);
});

it('keeps config-owned monthly periods unchanged when the selected academic year changes', function () {
    [$student, $class, $activeYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $otherYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $otherYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makeApplicableExamType($student, 'Iuran Bulanan Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Monthly);
    $augustBill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    makeMonthlyBill($student, $type, 100_000, 9, 2026);
    createExamBillAllocation($augustBill, 100_000);

    $requirements = collect([
        StudentExamRequirement::query()->create([
            'student_exam_id' => null,
            'student_eligibility_config_id' => $config->id,
            'school_level' => SchoolLevel::SMP,
            'payment_type_id' => $type->id,
            'billing_frequency' => BillFrequency::Monthly,
            'start_month' => '2026-08-01',
            'end_month' => '2026-09-01',
            'required_percentage' => 100,
            'is_active' => true,
        ]),
    ]);

    $service = app(StudentExamEligibilityService::class);
    $underActive = $service->evaluateCriteria($activeYear, $requirements, $student);
    $underOther = $service->evaluateCriteria($otherYear, $requirements, $student);
    $statuses = fn (array $result): array => collect($result['requirements'])
        ->mapWithKeys(fn (array $row): array => [$row['period_key'] => [$row['status'], $row['paid']]])
        ->all();

    expect($underActive['academic_year'])->toBe('2026/2027')
        ->and($statuses($underActive))->toBe(['2026-08' => ['pass', 100_000.0], '2026-09' => ['fail', 0.0]])
        ->and($statuses($underOther))->toBe($statuses($underActive));
});

it('keeps one-time Uang Pangkal independent from the yearly pool when evaluating config-owned criteria', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $book = makeApplicableExamType($student, 'Uang Buku', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $activity = makeApplicableExamType($student, 'Uang Kegiatan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $pangkal = makeApplicableExamType($student, 'Uang Pangkal', SchoolLevel::SMP, 8, BillFrequency::OneTime);
    $pooledRequirement = StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $book->id,
        'billing_frequency' => BillFrequency::Yearly,
        'start_month' => null,
        'end_month' => null,
        'required_percentage' => 50,
        'is_active' => true,
    ]);
    $pooledRequirement->pooledPaymentTypes()->attach([$book->id, $activity->id]);
    $oneTimeRequirement = StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $pangkal->id,
        'billing_frequency' => BillFrequency::OneTime,
        'start_month' => null,
        'end_month' => null,
        'required_percentage' => 100,
        'is_active' => true,
    ]);

    $bookBill = makeYearlyBill($student, $book, 1_000_000);
    makeYearlyBill($student, $activity, 1_000_000);
    $pangkalBill = makeOneTimeBill($student, $pangkal, 500_000);
    createExamBillAllocation($bookBill, 500_000);
    createExamBillAllocation($pangkalBill, 500_000);

    $result = app(StudentExamEligibilityService::class)->evaluateCriteria(
        AcademicYear::active(),
        collect([$pooledRequirement->fresh(), $oneTimeRequirement->fresh()]),
        $student,
    );

    $pooled = collect($result['requirements'])->firstWhere('is_pooled', true);
    $pangkalEvaluation = collect($result['requirements'])->firstWhere('payment_type_id', $pangkal->id);

    expect($pooled['target'])->toBe(2_000_000.0)
        ->and($pooled['paid'])->toBe(500_000.0)
        ->and($pooled['required_amount'])->toBe(1_000_000.0)
        ->and($pooled['status'])->toBe(StudentExamEligibilityService::STATUS_FAIL)
        ->and($pangkalEvaluation['is_pooled'])->toBeFalse()
        ->and($pangkalEvaluation['target'])->toBe(500_000.0)
        ->and($pangkalEvaluation['paid'])->toBe(500_000.0)
        ->and($pangkalEvaluation['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($result['is_eligible'])->toBeFalse();
});

it('never counts daycare payments toward config-owned eligibility pools', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $book = makeApplicableExamType($student, 'Buku Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $activity = makeApplicableExamType($student, 'Kegiatan Kenaikan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $pooledRequirement = StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $book->id,
        'billing_frequency' => BillFrequency::Yearly,
        'start_month' => null,
        'end_month' => null,
        'required_percentage' => 50,
        'is_active' => true,
    ]);
    $pooledRequirement->pooledPaymentTypes()->attach([$book->id, $activity->id]);
    makeYearlyBill($student, $book, 1_000_000);
    makeYearlyBill($student, $activity, 1_000_000);
    $daycare = DaycarePayment::factory()->create(['total_amount' => 1_500_000]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Pembayaran Daycare',
        'amount' => 1_500_000,
    ]);

    $result = app(StudentExamEligibilityService::class)->evaluateCriteria(
        AcademicYear::active(),
        collect([$pooledRequirement->fresh()]),
        $student,
    );

    expect($result['requirements'][0]['target'])->toBe(2_000_000.0)
        ->and($result['requirements'][0]['paid'])->toBe(0.0)
        ->and($result['is_eligible'])->toBeFalse();
});
