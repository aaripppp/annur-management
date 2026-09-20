<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentTypeManagement;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ProspectiveStudentBillGenerationService;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Seed Migration
|--------------------------------------------------------------------------
*/

it('memiliki Formulir Pendaftaran dengan audience calon siswa', function () {
    $type = PaymentType::where('name', 'Formulir Pendaftaran')->first();

    expect($type)->not->toBeNull()
        ->and($type->audience)->toBe(PaymentTypeAudience::ProspectiveStudent)
        ->and($type->is_active)->toBeTrue()
        ->and($type->is_auto_enrolled)->toBeFalse()
        ->and($type->is_required)->toBeFalse();

    foreach (SchoolLevel::cases() as $level) {
        expect(PaymentTypeSchoolLevel::query()
            ->where('payment_type_id', $type->id)
            ->where('school_level', $level)
            ->where('is_active', true)
            ->exists())->toBeTrue("Mapping {$level->value} harus aktif");
    }
});

/*
|--------------------------------------------------------------------------
| PaymentType Audience
|--------------------------------------------------------------------------
*/

it('memiliki audience student secara default', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP Baru']);

    expect($type->audience)->toBe(PaymentTypeAudience::Student)
        ->and($type->audience_label)->toBe('Siswa');
});

it('bisa memiliki audience prospective_student', function () {
    $type = PaymentType::create([
        'name' => 'Formulir Tes',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    expect($type->fresh()->audience)->toBe(PaymentTypeAudience::ProspectiveStudent)
        ->and($type->audience_label)->toBe('Calon Siswa');
});

it('scope forStudents hanya mengembalikan tipe siswa', function () {
    PaymentType::factory()->create(['name' => 'SPP', 'audience' => PaymentTypeAudience::Student]);
    PaymentType::create(['name' => 'Formulir', 'audience' => PaymentTypeAudience::ProspectiveStudent, 'is_active' => true]);

    $studentTypes = PaymentType::forStudents()->pluck('name');

    expect($studentTypes)->toContain('SPP')
        ->and($studentTypes)->not->toContain('Formulir');
});

it('scope forProspectiveStudents hanya mengembalikan tipe calon siswa', function () {
    PaymentType::factory()->create(['name' => 'SPP', 'audience' => PaymentTypeAudience::Student]);
    PaymentType::create(['name' => 'Formulir', 'audience' => PaymentTypeAudience::ProspectiveStudent, 'is_active' => true]);

    $prospectiveTypes = PaymentType::forProspectiveStudents()->pluck('name');

    expect($prospectiveTypes)->toContain('Formulir')
        ->and($prospectiveTypes)->not->toContain('SPP');
});

/*
|--------------------------------------------------------------------------
| ProspectiveStudentBill Model
|--------------------------------------------------------------------------
*/

it('ProspectiveStudent memiliki relasi bills', function () {
    $ps = ProspectiveStudent::factory()->create();

    $bill = ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $ps->id,
    ]);

    expect($ps->bills)->toHaveCount(1)
        ->and($ps->bills->first())->toBeInstanceOf(ProspectiveStudentBill::class);
});

it('ProspectiveStudentBill status accessor mengembalikan unpaid', function () {
    $bill = ProspectiveStudentBill::factory()->create();

    expect($bill->status)->toBe('unpaid')
        ->and($bill->status_label)->toBe('Belum Bayar')
        ->and($bill->isUnpaid())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Generation Service
|--------------------------------------------------------------------------
*/

function makeProspectiveTypeWithRate(int $classLevel, int $amount = 350000, SchoolLevel $schoolLevel = SchoolLevel::SMP): array
{
    $type = PaymentType::create([
        'name' => 'Formulir Pendaftaran Test',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => $schoolLevel,
        'is_active' => true,
        'is_required' => false,
    ]);

    $rate = PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => $classLevel,
        'amount' => $amount,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    return [$type, $rate];
}

function makeProspectiveStudentForLevel(int $classLevel, ?int $academicYearId = null): ProspectiveStudent
{
    $class = SchoolClass::factory()->create(['level' => $classLevel]);

    if ($academicYearId === null) {
        $academicYear = AcademicYear::firstOrCreate(
            ['year' => '2027/2028'],
            ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
        );
        $academicYearId = $academicYear->id;
    }

    return ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYearId,
        'school_class_id' => $class->id,
    ]);
}

it('membuat tagihan otomatis jika target jenjang diketahui dan tarif tersedia', function () {
    [$type, $rate] = makeProspectiveTypeWithRate(8, 350000);
    $ps = makeProspectiveStudentForLevel(8);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(1)
        ->and($ps->fresh()->bills)->toHaveCount(1);

    $bill = $ps->fresh()->bills->first();
    expect($bill->amount)->toBe('350000.00')
        ->and($bill->academic_year)->toBe('2027/2028')
        ->and($bill->billing_frequency)->toBe(BillFrequency::OneTime);
});

it('tidak membuat tagihan jika target jenjang belum diketahui', function () {
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    [$type, $rate] = makeProspectiveTypeWithRate(8, 350000);

    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => null,
    ]);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(0)
        ->and($ps->fresh()->bills)->toHaveCount(0);
});

it('hanya menghasilkan tagihan untuk jenjang yang dikonfigurasi', function () {
    $type = PaymentType::create([
        'name' => 'Formulir SMP Only',
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
        'amount' => 350000,
        'effective_from' => '2026-01-01',
    ]);

    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $sdClass->id,
    ]);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(0)
        ->and($ps->fresh()->bills)->toHaveCount(0);
});

it('memilih tarif yang tepat untuk jenjang tujuan', function () {
    $type = PaymentType::create([
        'name' => 'Formulir Multi Level',
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
        'class_level' => 7,
        'amount' => 300000,
        'effective_from' => '2026-01-01',
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 350000,
        'effective_from' => '2026-01-01',
    ]);

    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $class = SchoolClass::factory()->create(['level' => 8]);
    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
    ]);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->amount)->toBe('350000.00');
});

it('tidak membuat tagihan duplikat jika dijalankan dua kali', function () {
    [$type, $rate] = makeProspectiveTypeWithRate(8);
    $ps = makeProspectiveStudentForLevel(8);

    $service = app(ProspectiveStudentBillGenerationService::class);
    $service->generateFor($ps);
    $service->generateFor($ps);

    expect($ps->fresh()->bills)->toHaveCount(1);
});

it('tidak membuat tagihan jika tarif belum berlaku (effective_from di masa depan)', function () {
    $type = PaymentType::create([
        'name' => 'Formulir Belum Berlaku',
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
        'amount' => 350000,
        'effective_from' => '2030-01-01',
    ]);

    $ps = makeProspectiveStudentForLevel(8);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(0)
        ->and($ps->fresh()->bills)->toHaveCount(0);
});

it('tidak membuat tagihan jika frekuensi bukan one_time', function () {
    $type = PaymentType::create([
        'name' => 'Formulir Monthly',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 350000,
        'billing_frequency' => BillFrequency::Monthly,
        'is_monthly' => true,
        'effective_from' => '2026-01-01',
    ]);

    $ps = makeProspectiveStudentForLevel(8);

    $bills = app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($bills)->toHaveCount(0)
        ->and($ps->fresh()->bills)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Level Change Sync
|--------------------------------------------------------------------------
*/

it('memperbarui nominal tagihan saat kelas tujuan diubah ke jenjang dengan tarif berbeda', function () {
    [$type] = makeProspectiveTypeWithRate(8, 350000, SchoolLevel::SMP);
    $ps = makeProspectiveStudentForLevel(8);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    expect($ps->fresh()->bills->first()->amount)->toBe('350000.00');

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

    expect($ps->fresh()->bills)->toHaveCount(1)
        ->and($ps->fresh()->bills->first()->amount)->toBe('300000.00');
});

/*
|--------------------------------------------------------------------------
| Student Billing Safety
|--------------------------------------------------------------------------
*/

it('Student billing tidak menghasilkan tipe pembayaran calon siswa', function () {
    $student = makeBillStudent(8);
    $student->update(['entry_date' => '2026-01-01']);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);
    makeLevelDefault($spp, SchoolLevel::SMP, required: true);

    $formulir = PaymentType::create([
        'name' => 'Formulir Pendaftaran Safety',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $formulir->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $formulir->id,
        'class_level' => 8,
        'amount' => 350000,
        'effective_from' => '2026-01-01',
    ]);

    // Simulasi setting bocor: sekalipun pengaturan siswa dibuat manual untuk
    // tipe calon siswa, billing siswa harus tetap menolaknya.
    makeActiveSetting($student, $formulir);

    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );

    app(BillGenerationService::class)->generateForStudent($student, $academicYear->start_date);

    expect(StudentBill::where('payment_type_id', $formulir->id)->count())->toBe(0)
        ->and(StudentBill::where('payment_type_id', $spp->id)->count())->toBeGreaterThan(0);
});

it('generateInitialAcademicYearBills tidak menghasilkan tagihan pendaftaran untuk siswa', function () {
    [$student, $class, $academicYear] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027', '2026-07-01', '2027-06-30');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);
    makeLevelDefault($spp, SchoolLevel::SMP, required: true);

    $formulir = PaymentType::create([
        'name' => 'Formulir Pendaftaran Init',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $formulir->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $formulir->id,
        'class_level' => 8,
        'amount' => 350000,
        'effective_from' => '2026-01-01',
    ]);

    app(BillGenerationService::class)->generateInitialAcademicYearBills($student, $academicYear);

    expect(StudentBill::where('payment_type_id', $formulir->id)->count())->toBe(0)
        ->and(StudentBill::where('payment_type_id', $spp->id)->count())->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Livewire: Management Component
|--------------------------------------------------------------------------
*/

it('membuat calon siswa juga membuat tagihan pendaftaran otomatis', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    [$type, $rate] = makeProspectiveTypeWithRate(8, 350000);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $class = SchoolClass::factory()->create(['level' => 8]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Budi Santoso')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save');

    $ps = ProspectiveStudent::where('nama_lengkap', 'Budi Santoso')->first();

    expect($ps)->not->toBeNull()
        ->and($ps->bills)->toHaveCount(1)
        ->and($ps->bills->first()->paymentType->name)->toBe('Formulir Pendaftaran Test');
});

it('membuat calon siswa tidak membuat siswa', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    [$type, $rate] = makeProspectiveTypeWithRate(8, 350000);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $class = SchoolClass::factory()->create(['level' => 8]);

    $before = Student::count();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Budi Tanpa Siswa')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save');

    expect(Student::count())->toBe($before);
});

it('membuat calon siswa tidak membuat StudentBill', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    [$type, $rate] = makeProspectiveTypeWithRate(8, 350000);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $class = SchoolClass::factory()->create(['level' => 8]);

    $before = StudentBill::count();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Budi Tanpa StudentBill')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $class->id)
        ->call('save');

    expect(StudentBill::count())->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Livewire: Detail Component
|--------------------------------------------------------------------------
*/

it('halaman detail menampilkan tagihan pendaftaran', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    [$type] = makeProspectiveTypeWithRate(8, 350000);
    $ps = makeProspectiveStudentForLevel(8);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSee(['Tagihan Pendaftaran', 'Formulir Pendaftaran Test', 'Rp 350.000', 'Belum Bayar']);
});

it('halaman detail menampilkan empty state jika belum ada tagihan', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    $ps = makeProspectiveStudentForLevel(8);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSee(['Tagihan Pendaftaran', 'Belum ada tagihan pendaftaran']);
});

/*
|--------------------------------------------------------------------------
| PaymentTypeManagement Audience Selector
|--------------------------------------------------------------------------
*/

it('PaymentTypeManagement bisa menyimpan tipe dengan audience prospective_student', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    Livewire::test(PaymentTypeManagement::class)
        ->call('openModal')
        ->set('name', 'Formulir Pendaftaran UI')
        ->set('audience', 'prospective_student')
        ->set('schoolLevels', ['SMP'])
        ->call('save')
        ->assertHasNoErrors();

    $type = PaymentType::where('name', 'Formulir Pendaftaran UI')->first();

    expect($type)->not->toBeNull()
        ->and($type->audience)->toBe(PaymentTypeAudience::ProspectiveStudent);
});

it('PaymentTypeManagement menampilkan badge calon siswa pada daftar', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    PaymentType::create([
        'name' => 'Formulir Badge',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    Livewire::test(PaymentTypeManagement::class)
        ->assertSee(['Formulir Badge', 'Calon Siswa']);
});

/*
|--------------------------------------------------------------------------
| Management List: Tagihan Indicator
|--------------------------------------------------------------------------
*/

it('daftar calon siswa menampilkan indikator tagihan belum bayar', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    [$type] = makeProspectiveTypeWithRate(8, 350000);
    $ps = makeProspectiveStudentForLevel(8);
    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee(['Tagihan', 'Belum Bayar']);
});

it('daftar calon siswa menampilkan belum ada tagihan jika belum ada tagihan', function () {
    User::factory()->create();
    $this->actingAs(User::first());

    $ps = makeProspectiveStudentForLevel(8);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee(['Tagihan', 'Belum Ada Tagihan']);
});
