<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-08-15');

    AcademicYear::query()->update(['is_active' => false]);
    $this->historicalYear = AcademicYear::firstOrCreate(
        ['year' => '2022/2023'],
        ['is_active' => false, 'start_date' => '2022-07-01', 'end_date' => '2023-06-30'],
    );
    $this->historicalYear->update(['is_active' => false]);
    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $this->activeYear->update(['is_active' => true]);
    $this->historicalClass = SchoolClass::factory()->create(['level' => 8]);
});

function createAlumniWorkflowStudent(
    string $status,
    SchoolClass $schoolClass,
    AcademicYear $academicYear,
    ?string $nis = null,
): Student {
    $nis ??= 'ALUMNI-'.uniqid();

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', $nis)
        ->set('nama_lengkap', 'Siswa Alumni '.$status)
        ->set('class_id', (string) $schoolClass->id)
        ->set('entry_academic_year_id', (string) $academicYear->id)
        ->set('status', $status)
        ->call('save')
        ->assertHasNoErrors();

    return Student::query()->where('nis', $nis)->sole();
}

function makeAlumniWorkflowFixture(
    string $status,
    SchoolClass $schoolClass,
    AcademicYear $academicYear,
): Student {
    $student = Student::factory()->create([
        'class_id' => $schoolClass->id,
        'status' => $status,
    ]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

it('creates a terminal student with one historical active enrollment and no billing side effects', function (string $status) {
    $type = makeBillType('Default Alumni '.$status, auto: true, required: true);
    makeBillRate($type, 8, 500_000, ['billing_frequency' => BillFrequency::Yearly]);
    makeLevelDefault($type, SchoolLevel::SMP);

    $student = createAlumniWorkflowStudent(
        $status,
        $this->historicalClass,
        $this->historicalYear,
        'CREATE-'.$status,
    );
    $enrollment = $student->enrollments()->sole();

    expect($student->status)->toBe(StudentStatus::from($status))
        ->and($student->class_id)->toBe($this->historicalClass->id)
        ->and($enrollment->academic_year_id)->toBe($this->historicalYear->id)
        ->and($enrollment->school_class_id)->toBe($this->historicalClass->id)
        ->and($enrollment->status)->toBe('active')
        ->and($student->bills()->count())->toBe(0)
        ->and($student->paymentSettings()->count())->toBe(0);
})->with([
    'lulus' => StudentStatus::Graduated->value,
    'pindah' => StudentStatus::Transferred->value,
]);

it('preserves normal active student creation behavior', function () {
    $type = makeBillType('Uang Pangkal Active Create', auto: true, required: true);
    makeBillRate($type, 8, 1_250_000, ['billing_frequency' => BillFrequency::OneTime]);
    makeLevelDefault($type, SchoolLevel::SMP);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'ACTIVE-CREATE')
        ->set('nama_lengkap', 'Siswa Aktif Baru')
        ->set('class_id', (string) $this->historicalClass->id)
        ->set('entry_academic_year_id', (string) $this->activeYear->id)
        ->set('status', StudentStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'ACTIVE-CREATE')->sole();

    expect($student->status)->toBe(StudentStatus::Active)
        ->and($student->enrollments()->where('academic_year_id', $this->activeYear->id)->where('status', 'active')->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $type->id)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $type->id)->exists())->toBeTrue();
});

it('requires a historical academic year for terminal student creation', function (string $status) {
    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'MISSING-YEAR-'.$status)
        ->set('nama_lengkap', 'Siswa Tanpa Tahun')
        ->set('class_id', (string) $this->historicalClass->id)
        ->set('status', $status)
        ->call('save')
        ->assertHasErrors(['entry_academic_year_id']);

    expect(Student::query()->where('nis', 'MISSING-YEAR-'.$status)->exists())->toBeFalse();
})->with([
    'lulus' => StudentStatus::Graduated->value,
    'pindah' => StudentStatus::Transferred->value,
]);

it('uses stored terminal status before an active historical enrollment', function (string $status, string $label) {
    $student = makeAlumniWorkflowFixture($status, $this->historicalClass, $this->historicalYear);

    expect($student->academicStatus())->toBe($status)
        ->and($student->academicStatusLabel)->toBe($label);
})->with([
    'lulus' => [StudentStatus::Graduated->value, 'Lulus'],
    'pindah' => [StudentStatus::Transferred->value, 'Pindah'],
]);

it('keeps existing active academic status derivation', function () {
    $student = makeAlumniWorkflowFixture(StudentStatus::Active->value, $this->historicalClass, $this->activeYear);

    expect($student->academicStatus())->toBe(StudentStatus::Active->value)
        ->and($student->academicStatusLabel)->toBe('Aktif');
});

it('stores explicit monthly historical debt without using the current rate frequency', function () {
    $student = makeAlumniWorkflowFixture(StudentStatus::Graduated->value, $this->historicalClass, $this->historicalYear);
    $type = makeBillType('Historis Eksplisit');
    makeBillRate($type, 8, 999_999, ['billing_frequency' => BillFrequency::OneTime]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addFrequency', BillFrequency::Monthly->value)
        ->set('addMonth', '8')
        ->set('addYear', '2022')
        ->set('addAmount', '432100')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = $student->bills()->sole();

    expect($bill->billing_frequency)->toBe(BillFrequency::Monthly->value)
        ->and($bill->period_month)->toBe(8)
        ->and($bill->period_year)->toBe(2022)
        ->and($bill->academic_year)->toBeNull()
        ->and((float) $bill->amount)->toBe(432_100.0);
});

it('stores yearly and one-time historical academic-year context', function (string $status, string $frequency) {
    $student = makeAlumniWorkflowFixture($status, $this->historicalClass, $this->historicalYear);
    $type = makeBillType('Historis '.$status.' '.$frequency);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addFrequency', $frequency)
        ->set('addAcademicYear', $this->historicalYear->year)
        ->set('addAmount', '765432')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = $student->bills()->sole();

    expect($bill->billing_frequency)->toBe($frequency)
        ->and($bill->academic_year)->toBe('2022/2023')
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and((float) $bill->amount)->toBe(765_432.0);
})->with([
    'lulus yearly' => [StudentStatus::Graduated->value, BillFrequency::Yearly->value],
    'pindah one-time' => [StudentStatus::Transferred->value, BillFrequency::OneTime->value],
]);

it('rejects a non-student payment type and an unknown academic year', function () {
    $student = makeAlumniWorkflowFixture(StudentStatus::Graduated->value, $this->historicalClass, $this->historicalYear);
    $prospectiveType = PaymentType::factory()->create([
        'audience' => PaymentTypeAudience::ProspectiveStudent,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $prospectiveType->id)
        ->set('addFrequency', BillFrequency::Yearly->value)
        ->set('addAcademicYear', '1999/2000')
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPaymentTypeId']);

    $studentType = makeBillType('Tahun Historis Validasi');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $studentType->id)
        ->set('addFrequency', BillFrequency::Yearly->value)
        ->set('addAcademicYear', '1999/2000')
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasErrors(['addAcademicYear']);

    expect($student->bills()->count())->toBe(0);
});

it('allows inactive student payment types only for terminal students', function () {
    $inactiveType = makeBillType('Jenis Historis Nonaktif');
    $inactiveType->update(['is_active' => false]);
    $alumni = makeAlumniWorkflowFixture(StudentStatus::Graduated->value, $this->historicalClass, $this->historicalYear);
    $active = makeAlumniWorkflowFixture(StudentStatus::Active->value, $this->historicalClass, $this->activeYear);

    Livewire::test(StudentDetail::class, ['student' => $alumni])
        ->call('openAddBill')
        ->assertSee('Jenis Historis Nonaktif (Nonaktif)')
        ->set('addPaymentTypeId', (string) $inactiveType->id)
        ->set('addFrequency', BillFrequency::OneTime->value)
        ->set('addAcademicYear', '2022/2023')
        ->set('addAmount', '125000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $active])
        ->call('openAddBill')
        ->assertDontSee('Jenis Historis Nonaktif (Nonaktif)')
        ->set('addPaymentTypeId', (string) $inactiveType->id)
        ->set('addFrequency', BillFrequency::OneTime->value)
        ->set('addAmount', '125000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPaymentTypeId']);
});

it('keeps monthly yearly and one-time duplicate protection', function (string $frequency) {
    $student = makeAlumniWorkflowFixture(StudentStatus::Graduated->value, $this->historicalClass, $this->historicalYear);
    $type = makeBillType('Duplikat Historis '.$frequency);

    $addBill = function () use ($student, $type, $frequency) {
        $component = Livewire::test(StudentDetail::class, ['student' => $student])
            ->call('openAddBill')
            ->set('addPaymentTypeId', (string) $type->id)
            ->set('addFrequency', $frequency)
            ->set('addAmount', '250000');

        if ($frequency === BillFrequency::Monthly->value) {
            $component->set('addMonth', '9')->set('addYear', '2022');
        } else {
            $component->set('addAcademicYear', '2022/2023');
        }

        return $component->call('saveAddBill');
    };

    $addBill()->assertHasNoErrors();
    $addBill()->assertHasErrors(['addPeriod']);

    expect($student->bills()->where('payment_type_id', $type->id)->count())->toBe(1);
})->with([
    'monthly' => BillFrequency::Monthly->value,
    'yearly' => BillFrequency::Yearly->value,
    'one-time' => BillFrequency::OneTime->value,
]);

it('keeps a historical bill outstanding payable visible in billbook and target arrears', function () {
    $student = makeAlumniWorkflowFixture(StudentStatus::Transferred->value, $this->historicalClass, $this->historicalYear);
    $type = makeBillType('SPP Historis Kompatibel');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addFrequency', BillFrequency::Monthly->value)
        ->set('addMonth', '8')
        ->set('addYear', '2022')
        ->set('addAmount', '350000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = $student->bills()->sole();
    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        8,
        2022,
        '2022/2023',
    );

    expect($bill->remaining_amount)->toBe(350_000.0)
        ->and(collect($report['rows'])->pluck('payment_type_name'))->toContain($type->name);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2022/2023')
        ->assertSee($type->name)
        ->assertSee('Agustus 2022');

    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->assertSee($type->name)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save')
        ->assertHasNoErrors();

    expect(Payment::query()->where('student_id', $student->id)->exists())->toBeTrue()
        ->and($bill->fresh()->remaining_amount)->toBe(0.0);
});

it('shows create statuses pindah filtering and historical debt labels without adding status editing', function () {
    $transferred = makeAlumniWorkflowFixture(StudentStatus::Transferred->value, $this->historicalClass, $this->historicalYear);
    $active = makeAlumniWorkflowFixture(StudentStatus::Active->value, $this->historicalClass, $this->activeYear);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->assertSeeHtml('<option value="aktif">Aktif</option>')
        ->assertSeeHtml('<option value="lulus">Lulus</option>')
        ->assertSeeHtml('<option value="pindah">Pindah</option>');

    Livewire::test(StudentManagement::class)
        ->set('filterStatus', StudentStatus::Transferred->value)
        ->assertSee($transferred->nama_lengkap)
        ->assertDontSee($active->nama_lengkap)
        ->assertSee('Pindah');

    Livewire::test(StudentManagement::class)
        ->call('edit', $transferred->id)
        ->assertDontSeeHtml('id="status"');

    Livewire::test(StudentDetail::class, ['student' => $transferred])
        ->assertSee('Tambah Tagihan Lama');

    Livewire::test(StudentDetail::class, ['student' => $active])
        ->assertSee('Tambah Tagihan');
});
