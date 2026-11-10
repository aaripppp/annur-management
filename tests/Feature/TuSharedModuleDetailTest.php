<?php

use App\Enums\SchoolLevel;
use App\Livewire\PpdbApplicantEditor;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function sharedDetailTu(SchoolLevel $level = SchoolLevel::SD): User
{
    return User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $level,
        'is_active' => true,
    ]);
}

function sharedDetailClass(SchoolLevel $level): SchoolClass
{
    return SchoolClass::factory()->create(['level' => match ($level) {
        SchoolLevel::TK => -1,
        SchoolLevel::SD => 4,
        SchoolLevel::SMP => 8,
        SchoolLevel::SMA => 11,
    }]);
}

beforeEach(function () {
    $this->year = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
    $this->sdClass = sharedDetailClass(SchoolLevel::SD);
    $this->smpClass = sharedDetailClass(SchoolLevel::SMP);
    $this->tu = sharedDetailTu();
});

it('uses the shared Student detail for own-unit read-only billing', function () {
    $student = Student::factory()->create(['class_id' => $this->sdClass->id]);
    StudentBill::factory()->create(['student_id' => $student->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($this->tu)->get(route('siswa.show', $student));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertSee($student->nama_lengkap)
        ->assertSee('Biodata')
        ->assertSee('Tagihan &amp; Pembayaran', false)
        ->assertSee('Cetak Tagihan')
        ->assertDontSee('wire:click="openAddBill', false);

    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'student_bills')))->not->toBeEmpty();
});

it('denies foreign Student shared detail and hidden finance calls', function () {
    $own = Student::factory()->create(['class_id' => $this->sdClass->id]);
    $foreign = Student::factory()->create(['class_id' => $this->smpClass->id]);

    $this->actingAs($this->tu)->get(route('siswa.show', $foreign))->assertForbidden();
    Livewire::actingAs($this->tu)
        ->test(StudentDetail::class, ['student' => $own])
        ->call('generateBills')
        ->assertForbidden();
});

it('uses the shared Applicant detail and detailed editor without finance queries', function () {
    $applicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->sdClass->id,
    ]);
    ProspectiveStudentBill::factory()->create(['prospective_student_id' => $applicant->id]);
    ProspectiveStudentPayment::factory()->create(['prospective_student_id' => $applicant->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($this->tu)->get(route('calon-siswa.show', $applicant));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertSee($applicant->nama_lengkap)
        ->assertSee('Edit Biodata')
        ->assertSee('Dokumen')
        ->assertDontSee('Tagihan &amp; Pembayaran', false)
        ->assertDontSee('Riwayat Pembayaran');

    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'prospective_student_bills')
        || str_contains($query['query'], 'prospective_student_payments')))->toBeEmpty();
});

it('denies foreign Applicant shared detail and hidden payment deletion calls', function () {
    $own = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->sdClass->id,
    ]);
    $foreign = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->smpClass->id,
    ]);

    $this->actingAs($this->tu)->get(route('calon-siswa.show', $foreign))->assertForbidden();
    Livewire::actingAs($this->tu)
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $own])
        ->call('confirmDeletePayment', 999999)
        ->assertForbidden();
});

it('reauthorizes the detailed PPDB editor after Applicant unit changes', function () {
    $applicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->sdClass->id,
    ]);

    $component = Livewire::actingAs($this->tu)
        ->test(PpdbApplicantEditor::class, ['prospectiveStudent' => $applicant]);

    $applicant->update(['school_class_id' => $this->smpClass->id]);

    $component->call('openEditor')->assertForbidden();

    Livewire::actingAs($this->tu)
        ->test(PpdbApplicantEditor::class, ['prospectiveStudent' => $applicant])
        ->assertForbidden();
});

it('keeps the existing shared detail finance interface for administrators', function (string $role) {
    $student = Student::factory()->create(['class_id' => $this->sdClass->id]);
    $applicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->sdClass->id,
    ]);
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('siswa.show', $student))
        ->assertOk()
        ->assertSee('Tagihan &amp; Pembayaran', false)
        ->assertSee('Cetak Tagihan');
    $this->get(route('calon-siswa.show', $applicant))
        ->assertOk()
        ->assertSee('Tagihan &amp; Pembayaran', false)
        ->assertSee('Riwayat Pembayaran');
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);
