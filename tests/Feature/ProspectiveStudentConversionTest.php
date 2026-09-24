<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentConversionService;
use App\Services\StudentCreationService;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Menyiapkan skenario konversi: jenjang SMP level 7, tahun ajaran aktif
 * ${$startYear - 1}/$startYear dan tahun ajaran tujuan $startYear/($startYear + 1),
 * plus jenis pembayaran siswa (SPP bulanan, Uang Buku tahunan, Uang Pangkal
 * sekali bayar) dan Formulir Pendaftaran untuk calon siswa.
 *
 * @return array{
 *     activeYear: AcademicYear,
 *     futureYear: AcademicYear,
 *     schoolClass: SchoolClass,
 *     schoolLevel: SchoolLevel,
 *     spp: PaymentType,
 *     yearly: PaymentType,
 *     oneTime: PaymentType,
 *     formulir: PaymentType,
 *     classLevel: int,
 * }
 */
function prepareConversionSetup(int $startYear = 2027, int $classLevel = 7): array
{
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => ($startYear - 1).'/'.$startYear],
        ['is_active' => true, 'start_date' => ($startYear - 1).'-07-01', 'end_date' => $startYear.'-06-30']
    );
    $activeYear->update(['is_active' => true, 'start_date' => ($startYear - 1).'-07-01', 'end_date' => $startYear.'-06-30']);

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => $startYear.'/'.($startYear + 1)],
        ['is_active' => false, 'start_date' => $startYear.'-07-01', 'end_date' => ($startYear + 1).'-06-30']
    );
    $futureYear->update(['is_active' => false, 'start_date' => $startYear.'-07-01', 'end_date' => ($startYear + 1).'-06-30']);

    $schoolClass = SchoolClass::factory()->create(['level' => $classLevel]);
    $schoolLevel = SchoolLevel::fromClassLevel($classLevel);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, $schoolLevel);
    makeBillRate($spp, $classLevel, 900000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => $startYear.'-01-01',
    ]);

    $yearly = makeBillType('Uang Buku', auto: true, required: true);
    makeLevelDefault($yearly, $schoolLevel);
    makeBillRate($yearly, $classLevel, 500000, [
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => $startYear.'-01-01',
    ]);

    $oneTime = makeBillType('Uang Pangkal', auto: true, required: true);
    makeLevelDefault($oneTime, $schoolLevel);
    makeBillRate($oneTime, $classLevel, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => $startYear.'-01-01',
    ]);

    $formulir = PaymentType::create([
        'name' => 'Formulir Pendaftaran',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    makeLevelDefault($formulir, $schoolLevel, required: false, active: true);
    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $formulir->id,
        'class_level' => $classLevel,
        'amount' => 350000,
        'effective_from' => ($startYear - 1).'-01-01',
        'effective_until' => null,
    ]);

    return compact('activeYear', 'futureYear', 'schoolClass', 'schoolLevel', 'spp', 'yearly', 'oneTime', 'formulir', 'classLevel');
}

/**
 * Membuat calon siswa terdaftar yang siap dikonversi.
 *
 * @param  array<string, mixed>  $catalog
 * @param  array<string, mixed>  $overrides
 */
function createRegisteredProspect(array $catalog, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-20270001',
        'academic_year_id' => $catalog['futureYear']->id,
        'school_class_id' => $catalog['schoolClass']->id,
        'nama_lengkap' => 'Budi Calon Siswa',
        'nama_panggilan' => 'Budi',
        'jenis_kelamin' => 'L',
        'nama_orang_tua' => 'Bapak Ahmad',
        'no_telp_orang_tua' => '081234567890',
        'alamat' => 'Jl. Pesantren No. 1',
        'status' => ProspectiveStudentStatus::Registered,
        'converted_student_id' => null,
        'converted_at' => null,
    ], $overrides));
}

function financialTotalFromPayments(): float
{
    return (float) Payment::query()
        ->where('status', Payment::STATUS_ACTIVE)
        ->sum('total_amount');
}

function financialTotalFromProspectivePayments(): float
{
    return (float) ProspectiveStudentPayment::query()
        ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
        ->sum('total_amount');
}

function makePaidProspectivePayment(ProspectiveStudent $prospect, PaymentType $type, int $amount): ProspectiveStudentPayment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = ProspectiveStudentPayment::create([
        'receipt_number' => 'KWT-CS-'.uniqid(),
        'prospective_student_id' => $prospect->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-01',
        'total_amount' => $amount,
        'status' => ProspectiveStudentPayment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);

    $bill = ProspectiveStudentBill::create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => '2027/2028',
        'due_date' => null,
    ]);

    ProspectiveStudentPaymentDetail::create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
    ]);

    return $payment;
}

/*
|--------------------------------------------------------------------------
| 1. Eligibility: hanya calon siswa terdaftar tanpa data konversi
|--------------------------------------------------------------------------
*/
it('converts a registered prospect into a student with mapped profile fields', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect, '2027-0001');

    expect($student->nama_lengkap)->toBe('Budi Calon Siswa')
        ->and($student->nama_panggilan)->toBe('Budi')
        ->and($student->jenis_kelamin)->toBe('L')
        ->and($student->alamat)->toBe('Jl. Pesantren No. 1')
        ->and($student->nama_ayah)->toBe('Bapak Ahmad')
        ->and($student->no_telp_ayah)->toBe('081234567890')
        ->and($student->nis)->toBe('2027-0001')
        ->and($student->class_id)->toBe($catalog['schoolClass']->id)
        ->and($student->status)->toBe(StudentStatus::Active);

    $prospect->refresh();

    expect($prospect->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->converted_student_id)->toBe($student->id)
        ->and($prospect->converted_at)->not->toBeNull();
});

it('does not use registration number as NIS by default', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    expect($student->nis)->toBeNull()
        ->and(Student::where('nis', $prospect->registration_number)->exists())->toBeFalse()
        ->and($prospect->fresh()->registration_number)->toBe('REG-20270001');
});

it('blocks conversion of an already converted prospect', function () {
    $catalog = prepareConversionSetup();
    $student = Student::factory()->create(['nis' => '2027-0001', 'class_id' => $catalog['schoolClass']->id]);
    $prospect = createRegisteredProspect($catalog, [
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ]);

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect))
        ->toThrow(ValidationException::class)
        ->and(Student::count())->toBe(1)
        ->and($prospect->fresh()->converted_student_id)->toBe($student->id);
});

it('blocks conversion of a cancelled prospect', function () {
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'status' => ProspectiveStudentStatus::Cancelled,
    ]);

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect))
        ->toThrow(ValidationException::class)
        ->and($prospect->fresh()->converted_student_id)->toBeNull();
});

it('blocks conversion when target class is missing', function () {
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'school_class_id' => null,
    ]);

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect))
        ->toThrow(ValidationException::class)
        ->and($prospect->fresh()->converted_student_id)->toBeNull();
});

it('converts a prospect when targeting the currently active academic year', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'academic_year_id' => $catalog['activeYear']->id,
    ]);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect, '2026-0001');

    expect($student->fresh())->not->toBeNull()
        ->and($student->nis)->toBe('2026-0001')
        ->and($student->class_id)->toBe($catalog['schoolClass']->id)
        ->and($student->status)->toBe(StudentStatus::Active);

    $prospect->refresh();

    expect($prospect->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->converted_student_id)->toBe($student->id)
        ->and($prospect->converted_at)->not->toBeNull();
});

it('creates an active enrollment and reports aktif status for an active-year target', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'academic_year_id' => $catalog['activeYear']->id,
    ]);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $catalog['activeYear']->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->school_class_id)->toBe($catalog['schoolClass']->id)
        ->and($enrollment->status)->toBe('active')
        ->and($student->academicStatus())->toBe('aktif')
        ->and($student->academic_status_label)->toBe('Aktif');
});

it('generates the same bills as a normally created active student', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'academic_year_id' => $catalog['activeYear']->id,
    ]);

    $converted = app(ProspectiveStudentConversionService::class)->convert($prospect, '2026-0001');

    $normal = app(StudentCreationService::class)->create([
        'nis' => '2026-0002',
        'nama_lengkap' => 'Siswa Normal Aktif',
        'nama_panggilan' => 'Normal',
        'class_id' => $catalog['schoolClass']->id,
        'entry_academic_year_id' => $catalog['activeYear']->id,
    ]);

    $billKey = fn (StudentBill $bill): array => [
        'payment_type_id' => $bill->payment_type_id,
        'billing_frequency' => $bill->billing_frequency,
        'amount' => (float) $bill->amount,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
    ];

    expect($converted->bills()->get()->sortBy(fn (StudentBill $bill): string => implode('-', [
        (string) $bill->payment_type_id,
        (string) $bill->period_year,
        (string) $bill->period_month,
        (string) $bill->billing_frequency,
    ]))->map($billKey)->all())
        ->toEqual($normal->bills()->get()->sortBy(fn (StudentBill $bill): string => implode('-', [
            (string) $bill->payment_type_id,
            (string) $bill->period_year,
            (string) $bill->period_month,
            (string) $bill->billing_frequency,
        ]))->map($billKey)->all());
});

it('blocks conversion when the target academic year is in the past', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog, [
        'academic_year_id' => AcademicYear::create([
            'year' => '2025/2026',
            'is_active' => false,
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ])->id,
    ]);

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect))
        ->toThrow(ValidationException::class);
});

it('allows conversion regardless of unpaid prospective bills', function () {
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    ProspectiveStudentBill::create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $catalog['formulir']->id,
        'amount' => 350000,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => '2027/2028',
        'due_date' => null,
    ]);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    expect($student->fresh())->not->toBeNull()
        ->and($prospect->fresh()->status)->toBe(ProspectiveStudentStatus::Converted);
});

/*
|--------------------------------------------------------------------------
| 2. Data siswa: enrollment masa depan + status akademik calon_siswa
|--------------------------------------------------------------------------
*/
it('creates future enrollment and reports calon_siswa academic status', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $catalog['futureYear']->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->school_class_id)->toBe($catalog['schoolClass']->id)
        ->and($enrollment->status)->toBe('active')
        ->and(StudentAcademicEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $catalog['activeYear']->id)->exists())->toBeFalse()
        ->and($student->academicStatus())->toBe('calon_siswa')
        ->and($student->academic_status_label)->toBe('Calon Siswa');
});

/*
|--------------------------------------------------------------------------
| 3. Tagihan awal: Juli + tahunan + sekali bayar pada tahun ajaran tujuan
|--------------------------------------------------------------------------
*/
it('creates July-only monthly bills plus yearly and one-time bills', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    $monthlyBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly)
        ->with('paymentType')
        ->get();

    expect($monthlyBills)->toHaveCount(1)
        ->and($monthlyBills->pluck('period_month')->unique()->all())->toBe([7])
        ->and($monthlyBills->pluck('period_year')->unique()->all())->toBe([2027]);

    expect($student->bills()->where('billing_frequency', BillFrequency::Yearly)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $catalog['yearly']->id)->sole()->academic_year)->toBe('2027/2028')
        ->and((float) $student->bills()->where('payment_type_id', $catalog['yearly']->id)->sole()->amount)->toBe(500000.0);

    expect($student->bills()->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $catalog['oneTime']->id)->sole()->academic_year)->toBe('2027/2028')
        ->and((float) $student->bills()->where('payment_type_id', $catalog['oneTime']->id)->sole()->amount)->toBe(5000000.0);
});

it('never creates bills for the current school year', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    expect($student->bills()->where('period_year', 2026)->whereNotNull('period_year')->count())->toBe(0)
        ->and($student->bills()->where('academic_year', '2026/2027')->count())->toBe(0)
        ->and($student->bills()->where('period_year', 2026)->whereNotNull('period_month')->exists())->toBeFalse();
});

it('keeps Formulir Pendaftaran bills on the prospect without creating student bills', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    ProspectiveStudentBill::create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $catalog['formulir']->id,
        'amount' => 350000,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => '2027/2028',
        'due_date' => null,
    ]);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    expect(StudentBill::where('payment_type_id', $catalog['formulir']->id)->count())->toBe(0)
        ->and($prospect->fresh()->bills()->count())->toBe(1);
});

it('aligns payment settings to the start of the target academic year', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);
    $settings = $student->paymentSettings()->get();

    expect($settings)->not->toBeEmpty();

    foreach ($settings as $setting) {
        expect($setting->started_at->toDateString())->toBe('2027-07-01');
    }
});

/*
|--------------------------------------------------------------------------
| 4. Riwayat calon siswa dipertahankan (tanpa duplikasi keuangan)
|--------------------------------------------------------------------------
*/
it('preserves prospective bills, payments and financial totals', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    $payment = makePaidProspectivePayment($prospect, $catalog['formulir'], 350000);
    $bill = ProspectiveStudentBill::where('prospective_student_id', $prospect->id)->sole();

    $beforeProspect = financialTotalFromProspectivePayments();
    $beforeStudent = financialTotalFromPayments();

    app(ProspectiveStudentConversionService::class)->convert($prospect);

    expect($prospect->fresh()->bills()->count())->toBe(1)
        ->and($prospect->fresh()->payments()->count())->toBe(1)
        ->and($payment->fresh()->receipt_number)->not->toBeNull()
        ->and((float) $payment->fresh()->total_amount)->toBe(350000.0)
        ->and($bill->fresh()->prospective_student_id)->toBe($prospect->id)
        ->and(financialTotalFromProspectivePayments())->toBe($beforeProspect)
        ->and(financialTotalFromPayments())->toBe($beforeStudent)
        ->and(financialTotalFromProspectivePayments() + financialTotalFromPayments())
        ->toBe($beforeProspect + $beforeStudent);
});

/*
|--------------------------------------------------------------------------
| 5. NIS opsional + deteksi konflik lewat StudentNisConflictService
|--------------------------------------------------------------------------
*/
it('rejects an NIS that conflicts in the target academic year', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $existing = Student::factory()->create(['nis' => '2027-0001', 'class_id' => $catalog['schoolClass']->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $existing->id,
        'academic_year_id' => $catalog['futureYear']->id,
        'school_class_id' => $catalog['schoolClass']->id,
        'status' => 'active',
    ]);

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect, '2027-0001'))
        ->toThrow(ValidationException::class)
        ->and(Student::where('nis', '2027-0001')->count())->toBe(1)
        ->and($prospect->fresh()->converted_student_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 6. Idempotensi / konversi ganda
|--------------------------------------------------------------------------
*/
it('prevents duplicate conversion through the locked recheck', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    app(ProspectiveStudentConversionService::class)->convert($prospect, '2027-0001');

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect, '2027-0001'))
        ->toThrow(ValidationException::class);

    expect(Student::where('nis', '2027-0001')->count())->toBe(1)
        ->and(StudentAcademicEnrollment::count())->toBe(1)
        ->and(StudentBill::count())->toBe(3)
        ->and($prospect->fresh()->converted_student_id)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 7. Rollback penuh saat gagal setelah pembuatan siswa
|--------------------------------------------------------------------------
*/
it('rolls back the whole conversion when a downstream step fails', function () {
    $this->travelTo('2026-09-15');

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $this->mock(StudentCreationService::class, function ($mock): void {
        $mock->shouldReceive('create')->andReturnUsing(function (array $data) {
            Student::create(Arr::except($data, ['entry_academic_year_id']));

            throw new RuntimeException('simulated downstream failure');
        });
    });

    expect(fn () => app(ProspectiveStudentConversionService::class)->convert($prospect))
        ->toThrow(RuntimeException::class);

    expect(Student::where('nama_lengkap', 'Budi Calon Siswa')->count())->toBe(0)
        ->and(StudentAcademicEnrollment::count())->toBe(0)
        ->and(StudentBill::count())->toBe(0)
        ->and($prospect->fresh()->status)->toBe(ProspectiveStudentStatus::Registered)
        ->and($prospect->fresh()->converted_student_id)->toBeNull()
        ->and($prospect->fresh()->converted_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 8. Livewire: tombol dan modal konfirmasi "Jadikan Siswa"
|--------------------------------------------------------------------------
*/
it('shows the convert button and modal on the prospective detail page', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    Livewire::actingAs($user)
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $prospect])
        ->assertSee('Jadikan Siswa')
        ->call('openConvertModal')
        ->assertSet('isConvertModalOpen', true)
        ->assertSee('No. Pendaftaran')
        ->assertSee($prospect->registration_number)
        ->assertSee('2027/2028')
        ->assertSee($catalog['schoolClass']->name)
        ->call('closeConvertModal')
        ->assertSet('isConvertModalOpen', false);
});

it('shows the convert button and modal on the prospective payment workspace', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    Livewire::actingAs($user)
        ->test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $prospect])
        ->assertSee('Jadikan Siswa')
        ->call('openConvertModal')
        ->assertSet('isConvertModalOpen', true);
});

it('hides the convert button once the prospect is converted', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    Livewire::actingAs($user)
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $prospect->fresh()->load('convertedStudent')])
        ->assertDontSee('Jadikan Siswa')
        ->assertSee('Calon siswa ini telah dikonversi menjadi siswa.')
        ->assertSee($student->nama_lengkap);
});

it('converts a prospect through the Livewire detail page and redirects to the student', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    Livewire::actingAs($user)
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $prospect])
        ->call('openConvertModal')
        ->set('convertNis', '2027-0001')
        ->call('convertToStudent')
        ->assertHasNoErrors()
        ->assertRedirect(route('siswa.show', ['student' => Student::where('nis', '2027-0001')->sole()->id]));

    expect(ProspectiveStudent::find($prospect->id)->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and(Student::where('nis', '2027-0001')->count())->toBe(1);
});

it('converts a prospect through the livewire payment workspace without an NIS', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    Livewire::actingAs($user)
        ->test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $prospect])
        ->call('openConvertModal')
        ->call('convertToStudent')
        ->assertHasNoErrors()
        ->assertRedirect(route('siswa.show', ['student' => Student::where('nama_lengkap', 'Budi Calon Siswa')->sole()->id]));

    $prospect->refresh();

    expect($prospect->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->converted_student_id)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 9. Pencarian: calon siswa hilang dari pencarian calon, siswa muncul
|--------------------------------------------------------------------------
*/
it('removes a converted prospect from prospective search but keeps it in student search', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $searchText = 'Budi Calon';
    $workspaceUrl = route('pembayaran.prospective.workspace', $prospect);

    Livewire::actingAs($user)->test(PaymentIndex::class)
        ->set('studentSearch', $searchText)
        ->assertSee($workspaceUrl, false);

    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    Livewire::actingAs($user)->test(PaymentIndex::class)
        ->set('studentSearch', $searchText)
        ->assertDontSee($workspaceUrl, false)
        ->assertSee('student-result-'.$student->id)
        ->assertSee('Budi Calon Siswa');
});

/*
|--------------------------------------------------------------------------
| 10. Smoke halaman
|--------------------------------------------------------------------------
*/
it('renders the prospective pages for a registered prospect', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $this->actingAs($user)
        ->get(route('calon-siswa.show', $prospect))
        ->assertOk()
        ->assertSee('Jadikan Siswa');

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $prospect))
        ->assertOk()
        ->assertSee('Jadikan Siswa');
});

it('renders the converted banner with a student link after conversion', function () {
    $user = User::factory()->create();
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    $student = app(ProspectiveStudentConversionService::class)->convert($prospect);

    $this->actingAs($user)
        ->get(route('calon-siswa.show', $prospect->fresh()))
        ->assertOk()
        ->assertDontSee('Jadikan Siswa')
        ->assertSee('Dikonversi')
        ->assertSee('Budi Calon Siswa')
        ->assertSee(route('siswa.show', $student), false);
});

/*
|--------------------------------------------------------------------------
| 11. Aksi "Jadikan Siswa" pada daftar calon siswa
|--------------------------------------------------------------------------
*/
it('shows the Jadikan Siswa action icon for a registered prospect in the list', function () {
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('title="Jadikan Siswa"', false)
        ->assertSeeHtml('>school</span>')
        ->assertSee('title="Lihat Detail"', false)
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Hapus"', false)
        ->assertDontSee('Konversi Siswa');
});

it('does not show the Jadikan Siswa action icon for a converted prospect', function () {
    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);
    app(ProspectiveStudentConversionService::class)->convert($prospect);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertDontSee('title="Jadikan Siswa"', false)
        ->assertDontSeeHtml('>school</span>')
        ->assertSee('title="Lihat Detail"', false);
});

it('does not show the Jadikan Siswa action icon for a cancelled prospect', function () {
    $catalog = prepareConversionSetup();
    createRegisteredProspect($catalog, [
        'status' => ProspectiveStudentStatus::Cancelled,
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertDontSee('title="Jadikan Siswa"', false)
        ->assertDontSeeHtml('>school</span>')
        ->assertSee('title="Lihat Detail"', false)
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Hapus"', false);
});

it('renders the action icons in View, Edit, Jadikan Siswa, Delete order', function () {
    $catalog = prepareConversionSetup();
    createRegisteredProspect($catalog);

    $html = Livewire::test(ProspectiveStudentManagement::class)->html();

    $positions = [];
    foreach (['title="Lihat Detail"', 'title="Edit"', 'title="Jadikan Siswa"', 'title="Hapus"'] as $token) {
        $pos = strpos($html, $token);
        expect($pos)->not->toBeFalse();
        $positions[] = $pos;
    }

    expect($positions[0])->toBeLessThan($positions[1])
        ->and($positions[1])->toBeLessThan($positions[2])
        ->and($positions[2])->toBeLessThan($positions[3]);
});

it('opens the conversion modal for the correct prospect from the list', function () {
    $catalog = prepareConversionSetup();
    $prospectA = createRegisteredProspect($catalog, ['nama_lengkap' => 'Calon Pertama', 'registration_number' => 'REG-2027-000011']);
    $prospectB = createRegisteredProspect($catalog, ['nama_lengkap' => 'Calon Kedua', 'registration_number' => 'REG-2027-000012']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $prospectB->id)
        ->assertSet('isConvertModalOpen', true)
        ->assertSet('prospectiveStudent.id', $prospectB->id)
        ->assertSee('Calon Kedua')
        ->assertSee($catalog['schoolClass']->name);
});

it('converts a prospect from the list, stays on the page, refreshes the row, and stays idempotent', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();

    $catalog = prepareConversionSetup();
    $prospect = createRegisteredProspect($catalog);

    $component = Livewire::actingAs($user)
        ->test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $prospect->id)
        ->assertSet('isConvertModalOpen', true)
        ->set('convertNis', '2027-L0009')
        ->call('convertToStudent')
        ->assertHasNoErrors()
        ->assertSet('isConvertModalOpen', false)
        ->assertSet('prospectiveStudent', null)
        ->assertSee("Calon siswa {$prospect->nama_lengkap} berhasil dijadikan siswa.")
        ->assertDontSee('title="Jadikan Siswa"', false)
        ->assertDontSeeHtml('>school</span>')
        ->assertSee('title="Lihat Detail"', false);

    expect($component->effects)->not->toHaveKey('redirect');

    $student = Student::where('nis', '2027-L0009')->sole();
    $prospect->refresh();

    expect($prospect->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->converted_student_id)->toBe($student->id)
        ->and($prospect->converted_at)->not->toBeNull()
        ->and(Student::where('nis', '2027-L0009')->count())->toBe(1)
        ->and(StudentAcademicEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $catalog['futureYear']->id)->count())->toBe(1)
        ->and($student->bills()->count())->toBe(3);

    $this->actingAs($user)->get(route('siswa.show', $student))->assertOk();

    Livewire::actingAs($user)
        ->test(ProspectiveStudentManagement::class)
        ->call('convertProspect', $prospect->id)
        ->assertSet('isConvertModalOpen', false);

    expect(Student::where('nis', '2027-L0009')->count())->toBe(1)
        ->and($prospect->fresh()->converted_student_id)->toBe($student->id);
});
