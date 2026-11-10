<?php

use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentManagement;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function tuCrudUser(SchoolLevel $schoolLevel = SchoolLevel::SD): User
{
    return User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $schoolLevel,
        'is_active' => true,
    ]);
}

function tuCrudClass(SchoolLevel $schoolLevel, string $suffix = 'A'): SchoolClass
{
    $level = match ($schoolLevel) {
        SchoolLevel::TK => -2,
        SchoolLevel::SD => 1,
        SchoolLevel::SMP => 7,
        SchoolLevel::SMA => 10,
    };

    return SchoolClass::factory()->create([
        'name' => $schoolLevel->value.' '.$suffix,
        'level' => $level,
    ]);
}

function tuCrudStudent(SchoolClass $schoolClass, AcademicYear $academicYear, string $name): Student
{
    $student = Student::factory()->create([
        'class_id' => $schoolClass->id,
        'nama_lengkap' => $name,
        'status' => 'aktif',
    ]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

function tuCrudApplicant(SchoolClass $schoolClass, AcademicYear $academicYear, string $name): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'nama_lengkap' => $name,
        'status' => ProspectiveStudentStatus::Registered,
    ]);
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false]);

    $this->activeYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
});

it('allows TU to create a Student only in its assigned unit', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $tu = tuCrudUser();

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->call('openModal')
        ->assertSet('isModalOpen', true)
        ->set('nama_lengkap', 'Student Created By Tu')
        ->set('class_id', (string) $sdClass->id)
        ->set('status', 'aktif')
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::query()
        ->where('nama_lengkap', 'STUDENT CREATED BY TU')
        ->where('class_id', $sdClass->id)
        ->exists())->toBeTrue();
});

it('denies TU Student creation with a foreign class before any write', function () {
    $foreignClass = tuCrudClass(SchoolLevel::SMP);
    $tu = tuCrudUser();
    $studentCount = Student::query()->count();
    $billCount = StudentBill::query()->count();

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->set('nama_lengkap', 'Forbidden Student')
        ->set('class_id', (string) $foreignClass->id)
        ->set('status', 'aktif')
        ->call('save')
        ->assertForbidden();

    expect(Student::query()->count())->toBe($studentCount)
        ->and(StudentBill::query()->count())->toBe($billCount);
});

it('allows TU to edit own-unit Student biodata and class within the same unit', function () {
    $sdClassA = tuCrudClass(SchoolLevel::SD, 'A');
    $sdClassB = tuCrudClass(SchoolLevel::SD, 'B');
    $student = tuCrudStudent($sdClassA, $this->activeYear, 'Own Student');

    Livewire::actingAs(tuCrudUser())
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('nama_lengkap', 'Updated Own Student')
        ->set('class_id', (string) $sdClassB->id)
        ->call('save')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->nama_lengkap)->toBe('UPDATED OWN STUDENT')
        ->and($student->class_id)->toBe($sdClassB->id)
        ->and($student->enrollments()->where('academic_year_id', $this->activeYear->id)->value('school_class_id'))->toBe($sdClassB->id);
});

it('denies TU foreign Student edit and class reassignment tampering', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $ownStudent = tuCrudStudent($sdClass, $this->activeYear, 'Own Student');
    $foreignStudent = tuCrudStudent($smpClass, $this->activeYear, 'Foreign Student');
    $tu = tuCrudUser();

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->call('edit', $foreignStudent->id)
        ->assertForbidden();

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->call('edit', $ownStudent->id)
        ->set('class_id', (string) $smpClass->id)
        ->call('save')
        ->assertForbidden();

    expect($ownStudent->refresh()->class_id)->toBe($sdClass->id)
        ->and($foreignStudent->refresh()->class_id)->toBe($smpClass->id);
});

it('allows own-unit Student status changes and destructive deletion for TU', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $student = tuCrudStudent($sdClass, $this->activeYear, 'Protected Student');
    $bill = StudentBill::factory()->create(['student_id' => $student->id]);
    $tu = tuCrudUser();
    $payment = Payment::query()->create([
        'receipt_number' => 'TU-CRUD-001',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-11-07',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $tu->id,
    ]);

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->assertSee('Tambah Siswa')
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Hapus"', false)
        ->call('edit', $student->id)
        ->set('status', 'pindah')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(Student::query()->whereKey($student)->exists())->toBeFalse()
        ->and(StudentBill::query()->whereKey($bill)->exists())->toBeFalse()
        ->and(Payment::query()->whereKey($payment)->exists())->toBeFalse();
});

it('rechecks effective Student unit under locks for status changes and deletion', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $student = tuCrudStudent($sdClass, $this->activeYear, 'Moved Student');
    $tu = tuCrudUser();

    $statusComponent = Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('status', 'pindah');

    $student->enrollments()->where('academic_year_id', $this->activeYear->id)->update([
        'school_class_id' => $smpClass->id,
    ]);

    $statusComponent->call('save')->assertForbidden();
    expect($student->refresh()->status->value)->toBe('aktif');

    Livewire::actingAs($tu)
        ->test(StudentManagement::class)
        ->set('deletingId', $student->id)
        ->call('delete')
        ->assertForbidden();

    expect(Student::query()->whereKey($student)->exists())->toBeTrue();
});

it('rolls back TU Student deletion when its linked Applicant belongs to another unit', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $student = tuCrudStudent($sdClass, $this->activeYear, 'Linked Student');
    $bill = StudentBill::factory()->create(['student_id' => $student->id]);
    $applicant = tuCrudApplicant($smpClass, $this->activeYear, 'Foreign Linked Applicant');
    $applicant->update(['converted_student_id' => $student->id]);

    Livewire::actingAs(tuCrudUser())
        ->test(StudentManagement::class)
        ->set('deletingId', $student->id)
        ->call('delete')
        ->assertForbidden();

    expect(Student::query()->whereKey($student)->exists())->toBeTrue()
        ->and(StudentBill::query()->whereKey($bill)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($applicant)->exists())->toBeTrue();
});

it('allows TU to create and edit an Applicant only in its assigned unit', function () {
    $sdClassA = tuCrudClass(SchoolLevel::SD, 'A');
    $sdClassB = tuCrudClass(SchoolLevel::SD, 'B');
    $tu = tuCrudUser();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Applicant Created By Tu')
        ->set('academic_year_id', (string) $this->activeYear->id)
        ->set('school_class_id', (string) $sdClassA->id)
        ->call('save')
        ->assertHasNoErrors();

    $applicant = ProspectiveStudent::query()->where('nama_lengkap', 'APPLICANT CREATED BY TU')->firstOrFail();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('edit', $applicant->id)
        ->set('nama_lengkap', 'Applicant Updated By Tu')
        ->set('school_class_id', (string) $sdClassB->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($applicant->refresh()->nama_lengkap)->toBe('APPLICANT UPDATED BY TU')
        ->and($applicant->school_class_id)->toBe($sdClassB->id);
});

it('denies TU foreign Applicant create edit and class tampering before writes', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $ownApplicant = tuCrudApplicant($sdClass, $this->activeYear, 'Own Applicant');
    $foreignApplicant = tuCrudApplicant($smpClass, $this->activeYear, 'Foreign Applicant');
    $tu = tuCrudUser();
    $applicantCount = ProspectiveStudent::query()->count();
    $billCount = ProspectiveStudentBill::query()->count();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->set('nama_lengkap', 'Forbidden Applicant')
        ->set('academic_year_id', (string) $this->activeYear->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertForbidden();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('edit', $foreignApplicant->id)
        ->assertForbidden();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('edit', $ownApplicant->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertForbidden();

    expect(ProspectiveStudent::query()->count())->toBe($applicantCount)
        ->and(ProspectiveStudentBill::query()->count())->toBe($billCount)
        ->and($ownApplicant->refresh()->school_class_id)->toBe($sdClass->id);
});

it('blocks TU Applicant deletion with payments and preserves bills and documents', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $applicant = tuCrudApplicant($sdClass, $this->activeYear, 'Protected Applicant');
    $bill = ProspectiveStudentBill::factory()->create(['prospective_student_id' => $applicant->id]);
    $payment = ProspectiveStudentPayment::factory()->create(['prospective_student_id' => $applicant->id]);
    $document = PpdbDocument::query()->create([
        'prospective_student_id' => $applicant->id,
        'document_type' => 'kartu_keluarga',
        'disk' => 'local',
        'path' => 'ppdb/test/protected.pdf',
        'original_name' => 'protected.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);
    $tu = tuCrudUser();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->assertSee('Tambah Calon Siswa')
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Jadikan Siswa"', false)
        ->assertSee('title="Hapus"', false)
        ->call('confirmDelete', $applicant->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudent::query()->whereKey($applicant)->exists())->toBeTrue()
        ->and(ProspectiveStudentBill::query()->whereKey($bill)->exists())->toBeTrue()
        ->and(ProspectiveStudentPayment::query()->whereKey($payment)->exists())->toBeTrue()
        ->and(PpdbDocument::query()->whereKey($document)->exists())->toBeTrue();
});

it('allows TU to delete an own-unit Applicant with unpaid bills and documents', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $applicant = tuCrudApplicant($sdClass, $this->activeYear, 'Deletable Applicant');
    $bill = ProspectiveStudentBill::factory()->create(['prospective_student_id' => $applicant->id]);
    $document = PpdbDocument::query()->create([
        'prospective_student_id' => $applicant->id,
        'document_type' => 'kartu_keluarga',
        'disk' => 'local',
        'path' => 'ppdb/documents/test/deletable.pdf',
        'original_name' => 'deletable.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    Livewire::actingAs(tuCrudUser())
        ->test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $applicant->id)
        ->call('delete');

    expect(ProspectiveStudent::query()->whereKey($applicant)->exists())->toBeFalse()
        ->and(ProspectiveStudentBill::query()->whereKey($bill)->exists())->toBeFalse()
        ->and(PpdbDocument::query()->whereKey($document)->exists())->toBeFalse();
});

it('denies foreign and re-scoped Applicant deletion at the locked write boundary', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $foreignApplicant = tuCrudApplicant($smpClass, $this->activeYear, 'Foreign Applicant');
    $movedApplicant = tuCrudApplicant($sdClass, $this->activeYear, 'Moved Applicant');
    $tu = tuCrudUser();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $foreignApplicant->id)
        ->assertForbidden();

    $component = Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $movedApplicant->id);
    $movedApplicant->update(['school_class_id' => $smpClass->id]);
    $component->call('delete')->assertForbidden();

    expect(ProspectiveStudent::query()->whereKey($foreignApplicant)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($movedApplicant)->exists())->toBeTrue();
});

it('allows TU to convert an own-unit Applicant into a Student in the same unit', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $applicant = tuCrudApplicant($sdClass, $this->activeYear, 'Convertible Applicant');

    Livewire::actingAs(tuCrudUser())
        ->test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $applicant->id)
        ->assertSet('isConvertModalOpen', true)
        ->call('convertToStudent')
        ->assertHasNoErrors();

    $applicant->refresh();
    $student = Student::query()->findOrFail($applicant->converted_student_id);

    expect($applicant->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($student->class_id)->toBe($sdClass->id);
});

it('denies foreign and changed-class Applicant conversion without financial writes', function () {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $smpClass = tuCrudClass(SchoolLevel::SMP);
    $ownApplicant = tuCrudApplicant($sdClass, $this->activeYear, 'Changed Applicant');
    $foreignApplicant = tuCrudApplicant($smpClass, $this->activeYear, 'Foreign Applicant');
    $tu = tuCrudUser();
    $studentCount = Student::query()->count();
    $studentBillCount = StudentBill::query()->count();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('convertToStudent')
        ->assertForbidden();

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $foreignApplicant->id)
        ->assertForbidden();

    $component = Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $ownApplicant->id)
        ->assertSet('isConvertModalOpen', true);

    $ownApplicant->update(['school_class_id' => $smpClass->id]);

    $component->call('convertToStudent')->assertForbidden();

    expect(Student::query()->count())->toBe($studentCount)
        ->and(StudentBill::query()->count())->toBe($studentBillCount)
        ->and($ownApplicant->refresh()->status)->toBe(ProspectiveStudentStatus::Registered)
        ->and($foreignApplicant->refresh()->status)->toBe(ProspectiveStudentStatus::Registered);
});

it('keeps Admin and Superadmin CRUD policy capabilities unchanged', function (string $role) {
    $sdClass = tuCrudClass(SchoolLevel::SD);
    $student = tuCrudStudent($sdClass, $this->activeYear, 'Administrator Student');
    $applicant = tuCrudApplicant($sdClass, $this->activeYear, 'Administrator Applicant');
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('create', Student::class))->toBeTrue()
        ->and($user->can('update', $student))->toBeTrue()
        ->and($user->can('delete', $student))->toBeTrue()
        ->and($user->can('create', ProspectiveStudent::class))->toBeTrue()
        ->and($user->can('update', $applicant))->toBeTrue()
        ->and($user->can('delete', $applicant))->toBeTrue()
        ->and($user->can('convert', $applicant))->toBeTrue();
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);
