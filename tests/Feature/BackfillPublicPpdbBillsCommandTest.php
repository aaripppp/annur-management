<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\PpdbPublicSubmission;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Str;

function backfillPublicPpdbProspect(): ProspectiveStudent
{
    $academicYear = AcademicYear::query()->create([
        'year' => '2062/2063',
        'is_active' => false,
        'start_date' => '2062-07-01',
        'end_date' => '2063-06-30',
    ]);
    $schoolClass = SchoolClass::factory()->create([
        'name' => 'Kelas 7 Backfill',
        'level' => 7,
    ]);
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'created_by' => User::factory()->create()->id,
    ]);

    PpdbPublicSubmission::query()->create([
        'idempotency_key_hash' => hash('sha256', Str::random(64)),
        'success_reference_hash' => hash('sha256', Str::random(64)),
        'prospective_student_id' => $prospectiveStudent->id,
        'consented_at' => now(),
        'consent_policy_version' => 'test-policy',
        'completed_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    return $prospectiveStudent;
}

function backfillPublicPpdbType(string $name, int $amount): PaymentType
{
    $paymentType = PaymentType::query()->create([
        'name' => $name,
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::query()->create([
        'payment_type_id' => $paymentType->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $paymentType->id,
        'class_level' => 7,
        'amount' => $amount,
        'effective_from' => '2062-01-01',
        'effective_until' => null,
    ]);

    return $paymentType;
}

it('defaults to dry run and reports candidates without writing bills', function () {
    $prospectiveStudent = backfillPublicPpdbProspect();
    backfillPublicPpdbType('Formulir Backfill Dry Run', 456789);

    $this->artisan('ppdb:backfill-public-bills')
        ->expectsOutputToContain('Candidate prospect IDs: '.$prospectiveStudent->id)
        ->expectsOutputToContain('DRY RUN: no bills were created')
        ->expectsOutputToContain('Created: 0; Existing: 0; Skipped: 1.')
        ->assertSuccessful();

    expect(ProspectiveStudentBill::query()->count())->toBe(0)
        ->and(ProspectiveStudentPayment::query()->count())->toBe(0)
        ->and(ProspectiveStudentPaymentDetail::query()->count())->toBe(0);
});

it('creates partially missing bills in execute mode and is safe to rerun', function () {
    $prospectiveStudent = backfillPublicPpdbProspect();
    $existingType = backfillPublicPpdbType('Formulir Backfill Existing', 300000);
    $missingType = backfillPublicPpdbType('Formulir Backfill Missing', 175000);
    $existingBill = ProspectiveStudentBill::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'payment_type_id' => $existingType->id,
        'amount' => 300000,
        'billing_frequency' => 'one_time',
        'academic_year' => '2062/2063',
        'due_date' => null,
    ]);
    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'total_amount' => 100000,
    ]);
    $paymentDetail = ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $existingBill->id,
        'payment_type_id' => $existingType->id,
        'amount' => 100000,
    ]);

    $this->artisan('ppdb:backfill-public-bills --execute')
        ->expectsOutputToContain('Candidate prospect IDs: '.$prospectiveStudent->id)
        ->expectsOutputToContain('Created: 1; Existing: 1; Skipped: 0.')
        ->assertSuccessful();

    expect(ProspectiveStudentBill::query()->count())->toBe(2)
        ->and(ProspectiveStudentBill::query()->where('payment_type_id', $missingType->id)->value('amount'))->toBe('175000.00')
        ->and($payment->fresh()->total_amount)->toBe('100000.00')
        ->and($paymentDetail->fresh()->amount)->toBe('100000.00');

    $this->artisan('ppdb:backfill-public-bills --execute')
        ->expectsOutputToContain('Candidate prospect IDs: -')
        ->expectsOutputToContain('Created: 0; Existing: 2; Skipped: 0.')
        ->assertSuccessful();

    expect(ProspectiveStudentBill::query()->count())->toBe(2)
        ->and(ProspectiveStudentPayment::query()->count())->toBe(1)
        ->and(ProspectiveStudentPaymentDetail::query()->count())->toBe(1);
});
