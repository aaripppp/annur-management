<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentCreationService;
use App\Services\StudentImportService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * @return array{0: AcademicYear, 1: SchoolClass}
 */
function billingStartContext(): array
{
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $class7 = SchoolClass::firstOrCreate(['name' => 'VII A'], ['level' => 7]);

    return [$activeYear, $class7];
}

function billingImportRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'nis' => 'IMP-001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Ahmad',
        'kelas' => 'VII A',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2014-05-02',
        'nama_ayah' => 'Bapak Fauzan',
        'no_telp_ayah' => '081234567890',
        'nama_ibu' => 'Ibu Fauzan',
        'no_telp_ibu' => '+6281234567890',
        'alamat' => 'Jl. Pendidikan',
        'entry_date' => '',
    ], $overrides);
}

function billingStartStudentData(SchoolClass $schoolClass, AcademicYear $academicYear, string $nis): array
{
    return [
        ...Student::factory()->raw([
            'nis' => $nis,
            'class_id' => $schoolClass->id,
        ]),
        'entry_academic_year_id' => $academicYear->id,
    ];
}

it('starts monthly bills at a provided July entry date even when the current month is September', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('SPP Manual Juli');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 150000, ['billing_frequency' => BillFrequency::Monthly]);

    $data = billingStartStudentData($schoolClass, $activeYear, 'START-JULY-01');
    $data['entry_date'] = '2026-07-01';
    $student = app(StudentCreationService::class)->create($data);

    expect($student->entry_date?->toDateString())->toBe('2026-07-01')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(12)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 9)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-07-01');
});

it('does not clamp a provided entry date to the configured billbook start month', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    BillbookPeriod::setStartMonth(Carbon::parse('2026-11-01'));
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('SPP Tanpa Clamp');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 150000, ['billing_frequency' => BillFrequency::Monthly]);

    $data = billingStartStudentData($schoolClass, $activeYear, 'START-NO-CLAMP');
    $data['entry_date'] = '2026-07-01';
    $student = app(StudentCreationService::class)->create($data);

    $firstBill = $student->bills()
        ->where('payment_type_id', $monthly->id)
        ->orderBy('period_year')
        ->orderBy('period_month')
        ->first();

    expect($firstBill->period_month)->toBe(7)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(12);
});

it('floors monthly billing to June for an entry date before June', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('SPP Floor Juni');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 150000, ['billing_frequency' => BillFrequency::Monthly]);

    $data = billingStartStudentData($schoolClass, $activeYear, 'START-FEB-2026');
    $data['entry_date'] = '2026-02-10';
    $student = app(StudentCreationService::class)->create($data);

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 6)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->whereIn('period_month', [2, 3, 4, 5])->exists())->toBeFalse()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-06-01');
});

it('starts monthly billing in June when the entry date is exactly June', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('SPP Entry Juni');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 150000, ['billing_frequency' => BillFrequency::Monthly]);

    $data = billingStartStudentData($schoolClass, $activeYear, 'START-JUNE-2026');
    $data['entry_date'] = '2026-06-15';
    $student = app(StudentCreationService::class)->create($data);

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 6)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-06-01');
});

it('defaults an empty manual entry date to today and bills from the current month', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('SPP Default Hari Ini');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 150000, ['billing_frequency' => BillFrequency::Monthly]);

    $student = app(StudentCreationService::class)->create(billingStartStudentData($schoolClass, $activeYear, 'START-EMPTY'));

    expect($student->entry_date?->toDateString())->toBe('2026-09-15')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(10)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 9)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-09-01');
});

it('creates July-based billing through the manual Livewire form when entry is July', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $class = SchoolClass::factory()->create(['name' => 'X A Livewire', 'level' => 10]);
    $monthly = makeBillType('SPP Livewire Juli');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 120000, ['billing_frequency' => BillFrequency::Monthly]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'LIVEWIRE-07')
        ->set('nama_lengkap', 'Siswa Livewire Juli')
        ->set('nama_panggilan', 'Liv')
        ->set('class_id', $class->id)
        ->set('entry_date', '2026-07-01')
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Livewire')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'LIVEWIRE-07')->sole();

    expect($student->entry_date?->toDateString())->toBe('2026-07-01')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(12)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2026)->exists())->toBeTrue();
});

it('defaults an empty Livewire entry date to today and bills from the current month', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $class = SchoolClass::factory()->create(['name' => 'X B Livewire', 'level' => 10]);
    $monthly = makeBillType('SPP Livewire Default');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 120000, ['billing_frequency' => BillFrequency::Monthly]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'LIVEWIRE-EMPTY')
        ->set('nama_lengkap', 'Siswa Livewire Default')
        ->set('nama_panggilan', 'Def')
        ->set('class_id', $class->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Default')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'LIVEWIRE-EMPTY')->sole();

    expect($student->entry_date?->toDateString())->toBe('2026-09-15')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(10)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 9)->where('period_year', 2026)->exists())->toBeTrue();
});

it('imports a July entry date and starts monthly billing in July', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $monthly = makeBillType('SPP Import Juli');
    makeLevelDefault($monthly, SchoolLevel::SMP, required: false);
    makeBillRate($monthly, 7, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    app(StudentImportService::class)->import(
        [billingImportRow(['nis' => 'IMP-JULY-01', 'entry_date' => '2026-07-01'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-JULY-01')->sole();

    expect($student->entry_date?->toDateString())->toBe('2026-07-01')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(12)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-07-01');
});

it('imports an entry date before June and starts monthly billing in June', function () {
    $this->travelTo('2026-09-15');

    [$activeYear] = billingStartContext();
    $monthly = makeBillType('SPP Import Floor');
    makeLevelDefault($monthly, SchoolLevel::SMP, required: false);
    makeBillRate($monthly, 7, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    app(StudentImportService::class)->import(
        [billingImportRow(['nis' => 'IMP-FEB-01', 'entry_date' => '2026-02-10'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-FEB-01')->sole();

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 6)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-06-01');
});

it('imports an empty entry date, stores today, and bills from the current month', function () {
    $this->travelTo('2026-10-15');

    [$activeYear] = billingStartContext();
    $monthly = makeBillType('SPP Import Default');
    makeLevelDefault($monthly, SchoolLevel::SMP, required: false);
    makeBillRate($monthly, 7, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    app(StudentImportService::class)->import(
        [billingImportRow(['nis' => 'IMP-EMPTY-01'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-EMPTY-01')->sole();

    expect($student->entry_date?->toDateString())->toBe('2026-10-15')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(9)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 10)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-10-01');
});

it('keeps manual and import billing parity for the same entry date', function () {
    $this->travelTo('2026-09-15');

    [$activeYear, $class7] = billingStartContext();
    $monthly = makeBillType('SPP Paritas');
    makeLevelDefault($monthly, SchoolLevel::SMP, required: false);
    makeBillRate($monthly, 7, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    $manualData = billingStartStudentData($class7, $activeYear, 'PARITY-MANUAL');
    $manualData['entry_date'] = '2026-07-01';
    $manualStudent = app(StudentCreationService::class)->create($manualData);

    app(StudentImportService::class)->import(
        [billingImportRow(['nis' => 'PARITY-IMPORT', 'entry_date' => '2026-07-01'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );
    $importedStudent = Student::query()->where('nis', 'PARITY-IMPORT')->sole();

    expect($manualStudent->bills()->where('payment_type_id', $monthly->id)->count())
        ->toBe($importedStudent->bills()->where('payment_type_id', $monthly->id)->count());
});
