<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ProspectiveStudentBillGenerationService;
use Livewire\Livewire;

function makeWsProspectivePaymentFixture(int $amount = 350000): array
{
    $type = PaymentType::create([
        'name' => 'Formulir Pendaftaran Workspace',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => $amount,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    $class = SchoolClass::factory()->create(['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'nama_lengkap' => 'Siti Calon Workspace',
        'no_telp_orang_tua' => '081234567890',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first();

    return [$type, $ps, $bill];
}

function makeWsPartialProspectiveBill(): array
{
    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 200000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 200000,
    ]);

    $bill->refresh();

    return [$type, $ps, $bill];
}

function makeWsPaidProspectiveBill(): array
{
    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    $bill->refresh();

    return [$type, $ps, $bill];
}

/*
|------------------------------------------------------------------------
| #1 Workspace on search select
|------------------------------------------------------------------------
*/

it('opens the workspace when selecting a registered calon siswa from payment search', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Siti')
        ->assertSee('Siti Calon Workspace')
        ->assertSee(route('pembayaran.prospective.workspace', $ps));

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $ps))
        ->assertOk()
        ->assertSee($ps->nama_lengkap)
        ->assertSee($ps->registration_number);
});

/*
|------------------------------------------------------------------------
| #2 Profile info on workspace
|------------------------------------------------------------------------
*/

it('displays profile information on the prospective payment workspace', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $ps))
        ->assertOk()
        ->assertSee($ps->nama_lengkap)
        ->assertSee($ps->registration_number)
        ->assertSee($ps->academicYear->year)
        ->assertSee($ps->schoolClass->name)
        ->assertSee($ps->no_telp_orang_tua);
});

/*
|------------------------------------------------------------------------
| #3 Ganti Siswa
|------------------------------------------------------------------------
*/

it('Ganti Siswa button navigates back to payment index', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Ganti Siswa')
        ->assertSee(route('pembayaran.index'), false);
});

/*
|------------------------------------------------------------------------
| #4 Edit Profil
|------------------------------------------------------------------------
*/

it('can edit and save profile from the workspace', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    $newClass = SchoolClass::factory()->create(['level' => 8]);
    $newAcademicYear = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30']
    );

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openEditProfile')
        ->assertSet('isEditProfileOpen', true)
        ->set('nama_lengkap', 'Siti Update')
        ->set('academic_year_id', (string) $newAcademicYear->id)
        ->set('school_class_id', (string) $newClass->id)
        ->call('saveProfile')
        ->assertSet('isEditProfileOpen', false)
        ->assertHasNoErrors();

    expect($ps->fresh()->nama_lengkap)->toBe('Siti Update')
        ->and($ps->fresh()->academic_year_id)->toBe($newAcademicYear->id)
        ->and($ps->fresh()->school_class_id)->toBe($newClass->id);
});

it('cannot edit profile of a converted prospective student', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();
    $ps->update(['status' => ProspectiveStudent::STATUS_CONVERTED]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps->fresh()])
        ->call('openEditProfile')
        ->assertSet('isEditProfileOpen', false)
        ->assertHasNoErrors();
});

/*
|------------------------------------------------------------------------
| #5 Input Pembayaran
|------------------------------------------------------------------------
*/

it('Input Pembayaran button links to the prospective payment create page', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Input Pembayaran')
        ->assertSee(route('pembayaran.prospective.create', $ps));
});

/*
|------------------------------------------------------------------------
| #6 Bills rendered
|------------------------------------------------------------------------
*/

it('renders tagihan pendaftaran bills in the workspace table', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee(['Tagihan Pendaftaran', 'Formulir Pendaftaran Workspace', 'Rp 350.000']);
});

/*
|------------------------------------------------------------------------
| #7 Paid/remaining/status
|------------------------------------------------------------------------
*/

it('displays correct paid amount, remaining amount, and status badge', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsPartialProspectiveBill();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Rp 200.000')
        ->assertSee('Rp 150.000')
        ->assertSee('Sebagian');
});

it('displays Lunas status badge for fully paid bill', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsPaidProspectiveBill();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Lunas');
});

/*
|------------------------------------------------------------------------
| #8 Edit unpaid bill
|------------------------------------------------------------------------
*/

it('can edit an unpaid bill amount', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('editBill', $bill->id)
        ->assertSet('isEditOpen', true)
        ->assertSet('editBillId', $bill->id)
        ->set('editAmount', '400000')
        ->call('saveEditBill')
        ->assertSet('isEditOpen', false)
        ->assertHasNoErrors();

    expect($bill->fresh()->amount)->toBe('400000.00')
        ->and($bill->fresh()->is_manual_override)->toBeTrue();
});

/*
|------------------------------------------------------------------------
| #9 Partial not below paid
|------------------------------------------------------------------------
*/

it('rejects editing a partially paid bill below its paid amount', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsPartialProspectiveBill();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('editBill', $bill->id)
        ->set('editAmount', '100000')
        ->call('saveEditBill')
        ->assertHasErrors(['editAmount']);

    expect($bill->fresh()->amount)->toBe('350000.00');
});

/*
|------------------------------------------------------------------------
| #10 Paid not below paid
|------------------------------------------------------------------------
*/

it('rejects editing a fully paid bill below its paid amount', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsPaidProspectiveBill();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('editBill', $bill->id)
        ->set('editAmount', '200000')
        ->call('saveEditBill')
        ->assertHasErrors(['editAmount']);

    expect($bill->fresh()->amount)->toBe('350000.00');
});

/*
|------------------------------------------------------------------------
| #11 Edit doesn't touch PaymentRate
|------------------------------------------------------------------------
*/

it('editing a bill does not change the associated PaymentRate', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);
    $rateAmount = (float) PaymentRate::query()->where('payment_type_id', $type->id)->first()->amount;

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('editBill', $bill->id)
        ->set('editAmount', '500000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    expect($bill->fresh()->amount)->toBe('500000.00')
        ->and((float) PaymentRate::query()->where('payment_type_id', $type->id)->first()->amount)->toBe($rateAmount);
});

/*
|------------------------------------------------------------------------
| #12 Edit doesn't touch other bills
|------------------------------------------------------------------------
*/

it('editing one bill does not change other bills of the same prospective student', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);
    $otherType = PaymentType::create([
        'name' => 'Formulir Tes Lain',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);
    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $otherType->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);
    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $otherType->id,
        'class_level' => 8,
        'amount' => 200000,
        'effective_from' => '2026-01-01',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $otherBill = $ps->fresh()->bills->first(fn ($b) => $b->payment_type_id === $otherType->id);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps->fresh()])
        ->call('editBill', $bill->id)
        ->set('editAmount', '500000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    expect($bill->fresh()->amount)->toBe('500000.00')
        ->and($otherBill->fresh()->amount)->toBe('200000.00');
});

/*
|------------------------------------------------------------------------
| #13 No manual button for Student
|------------------------------------------------------------------------
*/

it('does not display a Pembayaran Manual button on the student payment page', function () {
    Livewire::test(PaymentIndex::class)
        ->assertDontSee('Pembayaran Manual');
});

/*
|------------------------------------------------------------------------
| #14 No manual button for Prospective
|------------------------------------------------------------------------
*/

it('does not display a Pembayaran Manual button on the prospective workspace', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertDontSee('Pembayaran Manual');
});

/*
|------------------------------------------------------------------------
| #15 Historical manual records still render
|------------------------------------------------------------------------
*/

it('still shows historical manual payment records in transaction history', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();
    $student = makeBillStudent();
    $bank = Bank::factory()->create(['is_active' => true]);
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-MANUAL-HIST-001',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-26',
        'total_amount' => 500000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->details()->create([
        'bill_id' => null,
        'payment_type_id' => null,
        'description' => 'Infaq Manual',
        'amount' => 500000,
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('activeTab', 'history')
        ->assertSee('Infaq Manual')
        ->assertSee('Rp 500.000');
});

/*
|------------------------------------------------------------------------
| #16 Student workspace intact
|------------------------------------------------------------------------
*/

it('selecting a student in payment search still loads the student workspace normally', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent();
    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee($student->nama_lengkap)
        ->assertSee('Billbook Siswa');
});

/*
|------------------------------------------------------------------------
| #17 Existing prospective tests still pass
|------------------------------------------------------------------------
*/

it('prospective payment create page still loads for direct access', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.create', $ps))
        ->assertOk()
        ->assertSee('Pembayaran Pendaftaran');
});

/*
|------------------------------------------------------------------------
| #18 sync override preserved after manual edit
|------------------------------------------------------------------------
*/

it('preserves manually edited bill amount after sync when class target changes', function () {
    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    $bill->update(['amount' => 400000, 'is_manual_override' => true]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SD,
        'is_active' => true,
        'is_required' => false,
    ]);
    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 5,
        'amount' => 300000,
        'effective_from' => '2026-01-01',
    ]);

    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $ps->update(['school_class_id' => $sdClass->id]);

    app(ProspectiveStudentBillGenerationService::class)->sync($ps);

    expect($ps->fresh()->bills->first()->amount)->toBe('400000.00');
});

it('sync updates unpaid non-override bill when class target changes', function () {
    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    expect($bill->is_manual_override)->toBeFalse();

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SD,
        'is_active' => true,
        'is_required' => false,
    ]);
    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 5,
        'amount' => 300000,
        'effective_from' => '2026-01-01',
    ]);

    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $ps->update(['school_class_id' => $sdClass->id]);

    app(ProspectiveStudentBillGenerationService::class)->sync($ps);

    expect($ps->fresh()->bills->first()->amount)->toBe('300000.00');
});

it('sync preserves paid bill amount even without is_manual_override', function () {
    [$type, $ps, $bill] = makeWsPaidProspectiveBill();

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SD,
        'is_active' => true,
        'is_required' => false,
    ]);
    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 5,
        'amount' => 300000,
        'effective_from' => '2026-01-01',
    ]);

    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $ps->update(['school_class_id' => $sdClass->id]);

    app(ProspectiveStudentBillGenerationService::class)->sync($ps);

    expect($ps->fresh()->bills->first()->amount)->toBe('350000.00');
});

it('bill delete succeeds for unpaid bill', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsProspectivePaymentFixture(350000);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('isDeleteOpen', true)
        ->assertSet('deleteBillId', $bill->id)
        ->assertSet('deletePaidAmount', 0.0)
        ->call('deleteBill')
        ->assertSet('isDeleteOpen', false)
        ->assertHasNoErrors();

    expect(ProspectiveStudentBill::query()->whereKey($bill->id)->exists())->toBeFalse();
});

it('bill delete blocked for paid bill', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeWsPaidProspectiveBill();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('isDeleteOpen', true)
        ->call('deleteBill')
        ->assertHasErrors(['deleteConfirm']);

    expect(ProspectiveStudentBill::query()->whereKey($bill->id)->exists())->toBeTrue();
});
