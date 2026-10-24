<?php

use App\Enums\StudentStatus;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use App\Services\TransactionHistoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function statusManagementUser(string $role = User::ROLE_ADMIN): User
{
    return User::factory()->create(['role' => $role]);
}

function statusManagementStudent(array $attributes = []): Student
{
    $schoolClass = SchoolClass::factory()->create(['level' => 8]);

    return Student::factory()->create([
        'class_id' => $schoolClass->id,
        ...$attributes,
    ]);
}

it('lets an admin edit biodata and change an active student to a supported inactive status', function (StudentStatus $status) {
    $student = statusManagementStudent();

    Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('status', StudentStatus::Active->value)
        ->set('nama_lengkap', 'Nama dan Status Diperbarui')
        ->set('status', $status->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isModalOpen', false)
        ->assertSee($status->label());

    expect($student->fresh()->status)->toBe($status)
        ->and($student->fresh()->nama_lengkap)->toBe('Nama dan Status Diperbarui')
        ->and($student->bills()->count())->toBe(0)
        ->and($student->enrollments()->count())->toBe(0);
})->with([
    'pindah' => StudentStatus::Transferred,
    'keluar' => StudentStatus::Withdrawn,
]);

it('lets a super admin change student status', function () {
    $student = statusManagementStudent(['status' => StudentStatus::Transferred]);

    Livewire::actingAs(statusManagementUser(User::ROLE_SUPER_ADMIN))
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('status', StudentStatus::Transferred->value)
        ->set('status', StudentStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->status)->toBe(StudentStatus::Active);
});

it('allows supported inactive statuses to be reactivated from edit siswa', function (StudentStatus $status) {
    $student = statusManagementStudent(['status' => $status]);

    Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('status', $status->value)
        ->set('status', StudentStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->status)->toBe(StudentStatus::Active);
})->with([
    'pindah' => StudentStatus::Transferred,
    'keluar' => StudentStatus::Withdrawn,
]);

it('rejects unauthorized direct status changes through edit siswa', function () {
    $student = statusManagementStudent();

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', StudentStatus::Withdrawn->value)
        ->call('save')
        ->assertForbidden();

    expect($student->fresh()->status)->toBe(StudentStatus::Active);
});

it('rejects unsupported status values including manual graduation', function (string $status) {
    $student = statusManagementStudent();

    Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', $status)
        ->call('save')
        ->assertHasErrors(['status']);

    expect($student->fresh()->status)->toBe(StudentStatus::Active);
})->with(['invalid', StudentStatus::Graduated->value]);

it('does not write when status is unchanged', function () {
    $student = statusManagementStudent();
    $student->forceFill(['updated_at' => '2020-01-01 00:00:00'])->saveQuietly();
    $updatedAt = $student->fresh()->updated_at;

    Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', StudentStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('preserves bills payments and enrollment records when status changes', function () {
    $user = statusManagementUser();
    $bank = Bank::factory()->create();
    $student = statusManagementStudent();
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $enrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);
    $type = makeBillType('SPP Status Preservation');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $payment = Payment::create([
        'receipt_number' => 'STATUS-PRESERVE-001',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-20',
        'total_amount' => 200000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    $paymentDetail = $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 200000,
    ]);

    Livewire::actingAs($user)
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', StudentStatus::Withdrawn->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->status)->toBe(StudentStatus::Withdrawn)
        ->and($student->bills()->whereKey($bill->id)->exists())->toBeTrue()
        ->and($student->payments()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($payment->details()->whereKey($paymentDetail->id)->exists())->toBeTrue()
        ->and($student->enrollments()->whereKey($enrollment->id)->where('status', 'active')->exists())->toBeTrue()
        ->and((float) $bill->fresh()->amount)->toBe(970000.0)
        ->and((float) $payment->fresh()->total_amount)->toBe(200000.0)
        ->and(app(TransactionHistoryService::class)
            ->getHistory(studentCategory: StudentStatus::Withdrawn->value)
            ->getCollection()
            ->pluck('receiptNumber')
            ->all())->toContain($payment->receipt_number);
});

it('renders status editing in edit siswa and removes the old detail status action', function () {
    $withdrawn = statusManagementStudent([
        'nama_lengkap' => 'Siswa Keluar Status',
        'status' => StudentStatus::Withdrawn,
    ]);
    $active = statusManagementStudent(['nama_lengkap' => 'Siswa Aktif Status']);
    $admin = statusManagementUser();

    Livewire::actingAs($admin)
        ->test(StudentDetail::class, ['student' => $withdrawn])
        ->assertSee('Drop Out')
        ->assertDontSee('Ubah Status')
        ->assertDontSee('Status Baru');

    $editComponent = Livewire::actingAs($admin)
        ->test(StudentManagement::class)
        ->call('edit', $withdrawn->id)
        ->assertSet('status', StudentStatus::Withdrawn->value)
        ->assertSee('Status Siswa')
        ->assertSeeHtml('<option value="aktif">Aktif</option>')
        ->assertSeeHtml('<option value="pindah">Pindah</option>')
        ->assertSeeHtml('<option value="keluar">Drop Out</option>');

    expect(substr_count($editComponent->html(), '<option value="lulus">Lulus</option>'))->toBe(1);

    Livewire::test(StudentManagement::class)
        ->assertSeeHtml('<option value="keluar">Drop Out</option>')
        ->set('filterStatus', StudentStatus::Withdrawn->value)
        ->assertSee($withdrawn->nama_lengkap)
        ->assertSee('Drop Out')
        ->assertDontSee($active->nama_lengkap);

    expect(TransactionHistoryService::studentCategoryOptions())
        ->toHaveKey(StudentStatus::Withdrawn->value, 'Drop Out');
});

it('preserves graduated status during unrelated biodata edits', function () {
    $student = statusManagementStudent([
        'nama_lengkap' => 'Lulusan Sebelum Edit',
        'status' => StudentStatus::Graduated,
    ]);

    $component = Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('status', StudentStatus::Graduated->value)
        ->assertSee('Status Lulus hanya dapat diubah melalui proses akademik yang berlaku.')
        ->set('nama_lengkap', 'Lulusan Setelah Edit')
        ->call('save')
        ->assertHasNoErrors();

    expect(substr_count($component->html(), '<option value="lulus">Lulus</option>'))->toBe(1)
        ->and($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and($student->fresh()->nama_lengkap)->toBe('Lulusan Setelah Edit');
});

it('rejects attempts to reactivate a graduated student through edit siswa', function () {
    $student = statusManagementStudent(['status' => StudentStatus::Graduated]);

    Livewire::actingAs(statusManagementUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', StudentStatus::Active->value)
        ->call('save')
        ->assertHasErrors(['status']);

    expect($student->fresh()->status)->toBe(StudentStatus::Graduated);
});

it('keeps calon siswa and graduation status behavior unchanged', function () {
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30'],
    );
    $prospective = statusManagementStudent();
    StudentAcademicEnrollment::create([
        'student_id' => $prospective->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $prospective->class_id,
        'status' => 'active',
    ]);
    $graduated = statusManagementStudent(['status' => StudentStatus::Graduated]);

    expect($prospective->academicStatus())->toBe('calon_siswa')
        ->and($prospective->academicStatusLabel)->toBe('Calon Siswa')
        ->and($graduated->academicStatus())->toBe(StudentStatus::Graduated->value)
        ->and($graduated->academicStatusLabel)->toBe('Lulus')
        ->and($activeYear->is_active)->toBeTrue();
});

it('preserves optimized academic context for withdrawn students', function () {
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $student = statusManagementStudent(['status' => StudentStatus::Withdrawn]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);
    $student = Student::query()
        ->with(['schoolClass', 'enrollments.academicYear', 'enrollments.schoolClass', 'categories'])
        ->findOrFail($student->id);
    $student->setRelation('academicContextActiveYear', $activeYear);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($student->academicStatus())->toBe(StudentStatus::Withdrawn->value)
        ->and($student->academicStatusLabel)->toBe('Drop Out')
        ->and($student->academicClassLabel())->toBe($student->schoolClass->name)
        ->and($student->academicYearContextLabel())->toBe($activeYear->year)
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

it('prevents direct graduation of transferred and withdrawn students', function (StudentStatus $status) {
    $fromYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $toYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30'],
    );
    $student = statusManagementStudent(['status' => $status]);
    $sourceClass = $student->schoolClass;
    $service = new class(app(BillGenerationService::class)) extends ClassPromotionService
    {
        public function invokeGraduation(Student $student, SchoolClass $sourceClass, AcademicYear $fromYear, AcademicYear $toYear): void
        {
            $this->graduateStudent($student, $sourceClass, $fromYear, $toYear);
        }
    };

    $service->invokeGraduation($student, $sourceClass, $fromYear, $toYear);

    expect($student->fresh()->status)->toBe($status)
        ->and($student->enrollments()->count())->toBe(0);
})->with([
    'pindah' => StudentStatus::Transferred,
    'keluar' => StudentStatus::Withdrawn,
]);
