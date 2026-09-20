<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentImport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use App\Services\StudentContinuationService;
use App\Services\StudentCreationService;
use App\Services\StudentImportService;
use App\Services\StudentImportSpreadsheet;
use App\Services\StudentNisConflictService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function studentImportRow(array $overrides = []): array
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

function studentImportWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA SISWA');
    $writer->addRow(Row::fromValues(StudentImportSpreadsheet::HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

function legacyStudentImportWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-legacy-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA SISWA');
    $writer->addRow(Row::fromValues(StudentImportSpreadsheet::LEGACY_HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

/**
 * @return array{student: Student, source_enrollment: StudentAcademicEnrollment, marker: StudentAcademicEnrollment, import_class: SchoolClass}
 */
function promotedGraduationImportScenario(
    AcademicYear $sourceYear,
    AcademicYear $targetYear,
    int $sourceLevel,
    string $sourceClassName,
    int $targetLevel,
    string $targetClassName,
    string $nis = 'IMP-001',
): array {
    $sourceClass = SchoolClass::query()->firstOrCreate(
        ['name' => $sourceClassName],
        ['level' => $sourceLevel],
    );
    $importClass = SchoolClass::query()->firstOrCreate(
        ['name' => $targetClassName],
        ['level' => $targetLevel],
    );
    $student = Student::factory()->create([
        'nis' => $nis,
        'nama_lengkap' => 'Ahmad Fauzan',
        'class_id' => $sourceClass->id,
        'status' => 'aktif',
    ]);
    $sourceEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $sourceYear->id,
        'school_class_id' => $sourceClass->id,
        'status' => 'active',
    ]);

    createPromotionRule($sourceClass, 'graduate');

    AcademicYear::query()->update(['is_active' => false]);
    $sourceYear->update(['is_active' => true]);
    $targetYear->update([
        'is_active' => false,
        'promotion_processed_at' => null,
    ]);
    $promotionResult = app(ClassPromotionService::class)->processPromotion($sourceYear, $targetYear);
    $targetYear->refresh();

    expect($promotionResult['graduated'])->toBe(1)
        ->and($targetYear->is_active)->toBeTrue()
        ->and($targetYear->promotion_processed_at)->not->toBeNull();

    return [
        'student' => $student->refresh(),
        'source_enrollment' => $sourceEnrollment->refresh(),
        'marker' => StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->firstOrFail(),
        'import_class' => $importClass,
    ];
}

function studentImportSourceYear(): AcademicYear
{
    return AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        [
            'is_active' => false,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'promotion_processed_at' => null,
        ],
    );
}

beforeEach(function () {
    $this->activeYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => true,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->futureYear = AcademicYear::create([
        'year' => '2028/2029',
        'is_active' => false,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);
    $this->class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $this->class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
});

it('parses valid xlsx, ignores empty rows, and resolves exact class names without mutation', function () {
    $path = studentImportWorkbook([
        ['IMP-001', 'Ahmad Fauzan', 'Ahmad', 'VII A', 'L', 'Bandung', '2014-05-02', 'Bapak Fauzan', '081234567890', 'Ibu Fauzan', '+6281234567890', 'Jl. Pendidikan'],
        ['', '', '', '', '', '', '', '', '', '', '', ''],
    ]);

    try {
        $rows = app(StudentImportSpreadsheet::class)->read($path);
        $preview = app(StudentImportService::class)->preview(
            $rows,
            $this->futureYear->id,
            StudentImportService::CONTEXT_PROSPECTIVE,
        );
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['nis'])->toBe('IMP-001')
        ->and($rows[0]['tanggal_lahir'])->toBe('2014-05-02')
        ->and($rows[0]['no_telp_ayah'])->toBe('081234567890')
        ->and($rows[0]['no_telp_ibu'])->toBe('+6281234567890')
        ->and($preview['rows'][0]['class_id'])->toBe($this->class7->id)
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0);
});

it('generates a two-sheet template with naturally sorted current class references', function () {
    expect(StudentImportSpreadsheet::HEADERS)->toBe([
        'NIS',
        'Nama Lengkap',
        'Nama Panggilan',
        'Kelas',
        'Jenis Kelamin',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Nama Ayah',
        'No Telp Ayah',
        'Nama Ibu',
        'No Telp Ibu',
        'Alamat',
        'Tanggal Masuk',
    ]);

    SchoolClass::create(['name' => 'VII E', 'level' => 7]);
    SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);
    SchoolClass::create(['name' => 'A-10', 'level' => -2]);
    SchoolClass::create(['name' => 'A-2', 'level' => -2]);
    $path = app(StudentImportSpreadsheet::class)->createTemplate();
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn (Row $row): array => $row->toArray())
                ->values()
                ->all();
        }
    } finally {
        $reader->close();
        @unlink($path);
    }

    expect(array_keys($sheets))->toBe(['DATA SISWA', 'REFERENSI KELAS', 'PANDUAN'])
        ->and($sheets['DATA SISWA'][0])->toBe(StudentImportSpreadsheet::HEADERS);

    $classNames = collect($sheets['REFERENSI KELAS'])->skip(1)->pluck(2)->values()->all();
    $kbReference = collect($sheets['REFERENSI KELAS'])->first(fn (array $row): bool => ($row[2] ?? null) === 'KB');

    expect($kbReference)->toBe(['TK', 'KB', 'KB'])
        ->and(array_search('KB', $classNames, true))->toBeLessThan(array_search('A-2', $classNames, true))
        ->and(array_search('A-2', $classNames, true))->toBeLessThan(array_search('A-10', $classNames, true));
});

it('imports a new student through the production creation flow and preserves existing students', function () {
    $existing = Student::factory()->create([
        'nis' => 'MANUAL-001',
        'class_id' => $this->class8->id,
    ]);
    $result = app(StudentImportService::class)->import(
        [studentImportRow()],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-001')->firstOrFail();

    expect($result['new'])->toBe(1)
        ->and($result['continued'])->toBe(0)
        ->and(Student::query()->where('nis', 'IMP-001')->count())->toBe(1)
        ->and($existing->fresh())->not->toBeNull()
        ->and($student->class_id)->toBe($this->class7->id)
        ->and($student->tanggal_lahir->toDateString())->toBe('2014-05-02')
        ->and($student->entry_date?->toDateString())->toBe(now()->toDateString())
        ->and($student->no_telp_ayah)->toBe('081234567890')
        ->and($student->no_telp_ibu)->toBe('+6281234567890')
        ->and($student->foto)->toBeNull()
        ->and($student->enrollments()->where('academic_year_id', $this->activeYear->id)->where('status', 'active')->exists())->toBeTrue();
});

it('accepts legacy workbooks without Tanggal Masuk', function () {
    $path = legacyStudentImportWorkbook([
        ['IMP-LEGACY', 'Siswa Legacy', 'Legacy', 'VII A', 'L', '', '', '', '', '', '', 'Jl. Lama'],
    ]);

    try {
        $rows = app(StudentImportSpreadsheet::class)->read($path);
        $result = app(StudentImportService::class)->import(
            $rows,
            $this->activeYear->id,
            StudentImportService::CONTEXT_ACTIVE,
        );
    } finally {
        @unlink($path);
    }

    $student = Student::query()->where('nis', 'IMP-LEGACY')->sole();

    expect($result['new'])->toBe(1)
        ->and($rows[0]['entry_date'])->toBe('')
        ->and($student->entry_date?->toDateString())->toBe(now()->toDateString());
});

it('imports an optional entry date and prevents premature monthly bills', function () {
    $this->travelTo('2027-08-20');
    $activeYear = AcademicYear::active();

    $monthly = makeBillType('Import Entry Monthly');
    makeLevelDefault($monthly, SchoolLevel::SMP, required: false);
    makeBillRate($monthly, 7, 125000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2027-07-01',
    ]);

    app(StudentImportService::class)->import(
        [studentImportRow(['entry_date' => '2027-10-01'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-001')->sole();

    expect($student->entry_date->toDateString())->toBe('2027-10-01')
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2027-10-01')
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(9)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->whereIn('period_month', [7, 8, 9])->exists())->toBeFalse()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 10)->where('period_year', 2027)->exists())->toBeTrue();
});

it('reports an invalid optional Tanggal Masuk clearly', function () {
    $preview = app(StudentImportService::class)->preview(
        [studentImportRow(['entry_date' => '31/10/2027'])],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Tanggal Masuk harus berformat YYYY-MM-DD.');
});

it('creates a new cross-jenjang student while preserving graduated history, bills, and payments', function () {
    $oldClass = SchoolClass::create(['name' => 'VI A', 'level' => 6]);
    $student = Student::factory()->create([
        'nis' => 'IMP-001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Fauzan',
        'class_id' => $oldClass->id,
        'jenis_kelamin' => 'P',
        'alamat' => 'Alamat Lama',
        'status' => 'lulus',
    ]);
    $oldEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $oldClass->id,
        'status' => 'lulus',
    ]);
    $historicalType = makeBillType('Tagihan Historis');
    $historicalBill = makeYearlyBill($student, $historicalType, 450000, '2027/2028');
    $payment = Payment::create([
        'receipt_number' => 'IMP-HISTORY-001',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2028-05-01',
        'total_amount' => 450000,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);
    $studentCount = Student::query()->count();
    $oldStudentSnapshot = $student->only(['class_id', 'nama_panggilan', 'jenis_kelamin', 'alamat', 'status']);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow(['nama_panggilan' => 'Ahmad Baru', 'jenis_kelamin' => 'L', 'alamat' => 'Alamat Baru'])],
        $this->futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );
    Livewire::test(StudentImport::class)
        ->set('preview', $preview)
        ->set('step', 2)
        ->assertSee('Siswa Baru • Alumni SD')
        ->assertDontSee('Dari SD')
        ->assertDontSee('NIS lama: IMP-001');
    $result = app(StudentImportService::class)->import(
        [studentImportRow(['nama_panggilan' => 'Ahmad Baru', 'jenis_kelamin' => 'L', 'alamat' => 'Alamat Baru'])],
        $this->futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    $newStudent = Student::query()->where('nis', 'IMP-001')->whereKeyNot($student->id)->sole();

    expect($preview['rows'][0]['status'])->toBe('new')
        ->and($preview['rows'][0]['status_label'])->toContain('NIS pernah digunakan di SD')
        ->and($result['new'])->toBe(1)
        ->and($result['continued'])->toBe(0)
        ->and(Student::query()->count())->toBe($studentCount + 1)
        ->and($oldEnrollment->fresh()->status)->toBe('lulus')
        ->and($oldEnrollment->fresh()->school_class_id)->toBe($oldClass->id)
        ->and($student->fresh()->only(array_keys($oldStudentSnapshot)))->toBe($oldStudentSnapshot)
        ->and($student->enrollments()->where('academic_year_id', $this->futureYear->id)->exists())->toBeFalse()
        ->and($newStudent->enrollments()->where('academic_year_id', $this->futureYear->id)->where('school_class_id', $this->class7->id)->where('status', 'active')->exists())->toBeTrue()
        ->and($newStudent->nama_panggilan)->toBe('Ahmad Baru')
        ->and($newStudent->jenis_kelamin)->toBe('L')
        ->and($newStudent->alamat)->toBe('Alamat Baru')
        ->and($newStudent->academicStatus())->toBe('calon_siswa')
        ->and($historicalBill->fresh()->amount)->toBe('450000.00')
        ->and($historicalBill->fresh()->student_id)->toBe($student->id)
        ->and($payment->fresh()->student_id)->toBe($student->id)
        ->and($payment->fresh())->not->toBeNull();
});

it('leaves a Grade 6 graduation marker untouched and creates a new Grade 7 student', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario(
        $sourceYear,
        $this->activeYear,
        6,
        'VI A',
        7,
        'VII A',
    );
    $student = $scenario['student'];
    $marker = $scenario['marker'];
    $historicalType = makeBillType('Tagihan Historis Marker');
    $historicalBill = makeYearlyBill($student, $historicalType, 450000, $sourceYear->year);
    $payment = Payment::create([
        'receipt_number' => 'MARKER-HISTORY-001',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2027-05-01',
        'total_amount' => 250000,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);
    $detail = PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $historicalBill->id,
        'payment_type_id' => $historicalType->id,
        'academic_year' => $sourceYear->year,
        'amount' => 250000,
    ]);
    $studentCount = Student::query()->count();
    $billSnapshot = $historicalBill->refresh()->only(['student_id', 'payment_type_id', 'amount', 'academic_year']);
    $paymentSnapshot = $payment->refresh()->only(['student_id', 'total_amount', 'status']);
    $detailSnapshot = $detail->refresh()->only(['payment_id', 'bill_id', 'payment_type_id', 'academic_year', 'amount']);
    $row = studentImportRow(['kelas' => 'VII A']);

    $preview = app(StudentImportService::class)->preview(
        [$row],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($marker->status)->toBe('lulus')
        ->and($marker->school_class_id)->toBe($scenario['source_enrollment']->school_class_id)
        ->and($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($preview['rows'][0]['status_label'])->toContain('NIS pernah digunakan di SD');

    $result = app(StudentImportService::class)->import(
        [$row],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $newStudent = Student::query()->where('nis', 'IMP-001')->whereKeyNot($student->id)->sole();
    $targetEnrollments = StudentAcademicEnrollment::query()
        ->where('student_id', $newStudent->id)
        ->where('academic_year_id', $this->activeYear->id)
        ->get();

    expect($result['new'])->toBe(1)
        ->and($result['continued'])->toBe(0)
        ->and(Student::query()->count())->toBe($studentCount + 1)
        ->and($scenario['source_enrollment']->fresh()->status)->toBe('active')
        ->and($scenario['source_enrollment']->fresh()->school_class_id)->toBe($scenario['source_enrollment']->school_class_id)
        ->and($marker->fresh())->not->toBeNull()
        ->and($targetEnrollments)->toHaveCount(1)
        ->and($targetEnrollments->first()->school_class_id)->toBe($scenario['import_class']->id)
        ->and($targetEnrollments->first()->status)->toBe('active')
        ->and($student->fresh()->class_id)->toBe($scenario['source_enrollment']->school_class_id)
        ->and($student->fresh()->status->value)->toBe('lulus')
        ->and($historicalBill->fresh()->only(array_keys($billSnapshot)))->toBe($billSnapshot)
        ->and($payment->fresh()->only(array_keys($paymentSnapshot)))->toBe($paymentSnapshot)
        ->and($detail->fresh()->only(array_keys($detailSnapshot)))->toBe($detailSnapshot);
});

it('does not reuse an arbitrary target-year lulus enrollment', function () {
    $sourceYear = studentImportSourceYear();
    $class6 = SchoolClass::create(['name' => 'VI A', 'level' => 6]);
    $student = Student::factory()->create([
        'nis' => 'IMP-001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'class_id' => $class6->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $sourceYear->id,
        'school_class_id' => $class6->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class6->id,
        'status' => 'lulus',
    ]);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow()],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new');
});

it('does not let old target-year bills block a new cross-jenjang student', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario($sourceYear, $this->activeYear, 6, 'VI A', 7, 'VII A');
    makeYearlyBill($scenario['student'], makeBillType('Target Marker Bill'), 500000, $this->activeYear->year);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow()],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($scenario['marker']->fresh())->not->toBeNull();
});

it('does not let old target-year payment details block a new cross-jenjang student', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario($sourceYear, $this->activeYear, 6, 'VI A', 7, 'VII A');
    $payment = Payment::create([
        'receipt_number' => 'MARKER-TARGET-PAYMENT',
        'student_id' => $scenario['student']->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2027-08-01',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'payment_type_id' => makeBillType('Target Marker Payment')->id,
        'academic_year' => $this->activeYear->year,
        'amount' => 100000,
    ]);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow()],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($scenario['marker']->fresh())->not->toBeNull();
});

it('does not use graduation-marker class transition rules during import', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario($sourceYear, $this->activeYear, 6, 'VI A', 7, 'VII A');
    SchoolClass::create(['name' => 'VIII Z', 'level' => 8]);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow(['kelas' => 'VIII Z'])],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($scenario['marker']->fresh())->not->toBeNull();
});

it('revalidates marker financial safety inside continuation execution', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario($sourceYear, $this->activeYear, 6, 'VI A', 7, 'VII A');
    makeYearlyBill($scenario['student'], makeBillType('Execution Conflict Bill'), 500000, $this->activeYear->year);

    expect(fn () => app(StudentContinuationService::class)->continue(
        $scenario['student'],
        [
            'nama_lengkap' => 'Ahmad Fauzan',
            'nama_panggilan' => 'Ahmad',
            'class_id' => $scenario['import_class']->id,
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Pendidikan',
        ],
        $this->activeYear,
        false,
    ))->toThrow(DomainException::class, 'membutuhkan pemeriksaan manual');

    expect($scenario['source_enrollment']->fresh()->status)->toBe('active')
        ->and($scenario['marker']->fresh())->not->toBeNull()
        ->and($scenario['student']->fresh()->status->value)->toBe('lulus');
});

it('rejects direct continuation across school units without mutating history or finances', function () {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario($sourceYear, $this->activeYear, -1, 'B-1', 1, 'I A');
    $historicalBill = makeYearlyBill($scenario['student'], makeBillType('TKB Historis'), 450000, $sourceYear->year);
    $before = [
        'student' => $scenario['student']->only(['class_id', 'status']),
        'source' => $scenario['source_enrollment']->only(['school_class_id', 'status']),
        'marker' => $scenario['marker']->only(['school_class_id', 'status']),
        'bills' => StudentBill::query()->orderBy('id')->get()->toArray(),
        'payments' => Payment::query()->orderBy('id')->get()->toArray(),
    ];

    expect(fn () => app(StudentContinuationService::class)->continue(
        $scenario['student'],
        [
            'nama_lengkap' => 'Ahmad Fauzan',
            'class_id' => $scenario['import_class']->id,
        ],
        $this->activeYear,
        false,
    ))->toThrow(DomainException::class, 'harus didaftarkan sebagai siswa baru saat berpindah jenjang');

    expect($scenario['student']->fresh()->only(['class_id', 'status']))->toBe($before['student'])
        ->and($scenario['source_enrollment']->fresh()->only(['school_class_id', 'status']))->toBe($before['source'])
        ->and($scenario['marker']->fresh()->only(['school_class_id', 'status']))->toBe($before['marker'])
        ->and($historicalBill->fresh())->not->toBeNull()
        ->and(StudentBill::query()->orderBy('id')->get()->toArray())->toBe($before['bills'])
        ->and(Payment::query()->orderBy('id')->get()->toArray())->toBe($before['payments']);
});

it('creates new students across jenjang instead of continuing graduation markers', function (
    int $sourceLevel,
    string $sourceClass,
    int $targetLevel,
    string $targetClass,
) {
    $sourceYear = studentImportSourceYear();
    $scenario = promotedGraduationImportScenario(
        $sourceYear,
        $this->activeYear,
        $sourceLevel,
        $sourceClass,
        $targetLevel,
        $targetClass,
    );
    $row = studentImportRow(['kelas' => $targetClass]);

    $preview = app(StudentImportService::class)->preview(
        [$row],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );
    app(StudentImportService::class)->import(
        [$row],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $newStudent = Student::query()->where('nis', 'IMP-001')->whereKeyNot($scenario['student']->id)->sole();
    $targetEnrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $newStudent->id)
        ->where('academic_year_id', $this->activeYear->id)
        ->sole();

    expect($preview['rows'][0]['status'])->toBe('new')
        ->and($scenario['source_enrollment']->fresh()->status)->toBe('active')
        ->and($scenario['marker']->fresh())->not->toBeNull()
        ->and($scenario['student']->fresh()->class_id)->not->toBe($scenario['import_class']->id)
        ->and($targetEnrollment->school_class_id)->toBe($scenario['import_class']->id)
        ->and($targetEnrollment->status)->toBe('active');
})->with([
    'TKB to Grade 1' => [-1, 'B-1', 1, 'I A'],
    'Grade 9 to Grade 10' => [9, 'IX A', 10, 'X-A'],
]);

it('does not use name matching to merge a graduated NIS', function () {
    $student = Student::factory()->create([
        'nis' => 'IMP-001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'class_id' => $this->class7->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $this->class7->id,
        'status' => 'lulus',
    ]);

    $preview = app(StudentImportService::class)->preview(
        [studentImportRow(['nama_lengkap' => 'Siti Aisyah'])],
        $this->futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and(Student::query()->count())->toBe(1)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(1);
});

it('rejects active NIS, duplicate file NIS, and an existing target-year enrollment', function () {
    $activeStudent = Student::factory()->create([
        'nis' => 'ACTIVE-001',
        'nama_lengkap' => 'Siswa Aktif',
        'class_id' => $this->class7->id,
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $activeStudent->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $this->class7->id,
        'status' => 'active',
    ]);
    $targetStudent = Student::factory()->create([
        'nis' => 'TARGET-001',
        'nama_lengkap' => 'Siswa Target',
        'class_id' => $this->class7->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $targetStudent->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $this->class7->id,
        'status' => 'active',
    ]);

    $preview = app(StudentImportService::class)->preview([
        studentImportRow(['row_number' => 2, 'nis' => 'ACTIVE-001', 'nama_lengkap' => 'Siswa Aktif']),
        studentImportRow(['row_number' => 3, 'nis' => 'DUP-001']),
        studentImportRow(['row_number' => 4, 'nis' => 'DUP-001']),
        studentImportRow(['row_number' => 5, 'nis' => 'TARGET-001', 'nama_lengkap' => 'Siswa Target']),
    ], $this->futureYear->id, StudentImportService::CONTEXT_PROSPECTIVE);

    expect($preview['summary']['errors'])->toBe(4)
        ->and($preview['rows'][0]['status_label'])->toContain('sudah terdaftar pada jenjang SMP')
        ->and($preview['rows'][1]['status_label'])->toContain('lebih dari sekali')
        ->and($preview['rows'][2]['status_label'])->toContain('lebih dari sekali')
        ->and($preview['rows'][3]['status_label'])->toContain('sudah terdaftar');
});

it('creates July monthly components with yearly and one-time bills for calon siswa and supports later generation', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    $osis = makeBillType('OSIS', auto: true, required: true);
    $book = makeBillType('Uang Buku', auto: true, required: true);
    $activity = makeBillType('Uang Kegiatan', auto: true, required: true);
    $entry = makeBillType('Uang Pangkal', auto: true, required: true);

    foreach ([$spp, $ekskul, $osis] as $type) {
        makeBillRate($type, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);
        makeLevelDefault($type, SchoolLevel::SMP);
    }
    foreach ([$book, $activity] as $type) {
        makeBillRate($type, 7, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    }
    makeBillRate($entry, 7, 2500000, ['billing_frequency' => BillFrequency::OneTime]);

    app(StudentImportService::class)->import(
        [studentImportRow()],
        $this->futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-001')->firstOrFail();

    expect($student->bills()->where('billing_frequency', BillFrequency::Yearly)->count())->toBe(2)
        ->and($student->bills()->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1)
        ->and($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(3)
        ->and($student->bills()->where('payment_type_id', $spp->id)->where('period_month', 7)->where('period_year', 2028)->count())->toBe(1)
        ->and($student->bills()->whereIn('payment_type_id', [$ekskul->id, $osis->id])->where('period_month', 7)->where('period_year', 2028)->count())->toBe(2);

    $generation = app(BillGenerationService::class)->generateMonthlyForAcademicYear($this->futureYear);

    expect($generation['created'])->toBeGreaterThan(0)
        ->and($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBeGreaterThan(0);
});

it('creates a fresh one-time bill for a new cross-jenjang student', function () {
    $oldClass = SchoolClass::create(['name' => 'VI A', 'level' => 6]);
    $entry = makeBillType('Uang Pangkal', auto: true, required: true);
    makeBillRate($entry, 7, 2500000, ['billing_frequency' => BillFrequency::OneTime]);
    $student = Student::factory()->create([
        'nis' => 'IMP-001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'class_id' => $oldClass->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $oldClass->id,
        'status' => 'lulus',
    ]);
    makeActiveSetting($student, $entry);
    $existingBill = makeOneTimeBill($student, $entry, 2000000, '2027/2028');

    app(StudentImportService::class)->import(
        [studentImportRow()],
        $this->futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    $newStudent = Student::query()->where('nis', 'IMP-001')->whereKeyNot($student->id)->sole();

    expect($student->bills()->where('payment_type_id', $entry->id)->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1)
        ->and($existingBill->fresh()->amount)->toBe('2000000.00')
        ->and($existingBill->fresh()->student_id)->toBe($student->id)
        ->and($newStudent->bills()->where('payment_type_id', $entry->id)->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1);
});

it('blocks the whole import when any preview row is invalid', function () {
    $rows = [
        studentImportRow(),
        studentImportRow(['row_number' => 3, 'nis' => 'IMP-002', 'kelas' => 'VII Z']),
    ];

    expect(fn () => app(StudentImportService::class)->import(
        $rows,
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    ))->toThrow(ValidationException::class);

    expect(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0);
});

it('accepts blank optional biodata and rejects a non-empty invalid birth date', function () {
    $blankBiodata = studentImportRow([
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'nama_ayah' => '',
        'no_telp_ayah' => '',
        'nama_ibu' => '',
        'no_telp_ibu' => '',
    ]);

    $validPreview = app(StudentImportService::class)->preview(
        [$blankBiodata],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );
    $invalidPreview = app(StudentImportService::class)->preview(
        [studentImportRow(['tanggal_lahir' => '02/05/2014'])],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($validPreview['has_errors'])->toBeFalse()
        ->and($invalidPreview['has_errors'])->toBeTrue()
        ->and($invalidPreview['rows'][0]['status_label'])->toContain('YYYY-MM-DD');

    app(StudentImportService::class)->import(
        [$blankBiodata],
        $this->activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-001')->firstOrFail();

    expect($student->tempat_lahir)->toBeNull()
        ->and($student->tanggal_lahir)->toBeNull()
        ->and($student->nama_ayah)->toBeNull()
        ->and($student->no_telp_ibu)->toBeNull();
});

it('rolls back every imported row when a later creation fails unexpectedly', function () {
    $realCreationService = app(StudentCreationService::class);
    $mockCreationService = Mockery::mock(StudentCreationService::class);
    $calls = 0;
    $mockCreationService->shouldReceive('create')->twice()->andReturnUsing(
        function (array $data) use ($realCreationService, &$calls): Student {
            $calls++;

            if ($calls === 2) {
                throw new RuntimeException('Simulasi kegagalan row kedua.');
            }

            return $realCreationService->create($data);
        }
    );
    $service = new StudentImportService(
        $mockCreationService,
        app(StudentNisConflictService::class),
    );

    expect(fn () => $service->import([
        studentImportRow(),
        studentImportRow(['row_number' => 3, 'nis' => 'IMP-002']),
    ], $this->activeYear->id, StudentImportService::CONTEXT_ACTIVE))->toThrow(RuntimeException::class);

    expect(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0);
});

it('downloads the xlsx template and previews an uploaded workbook through Livewire', function () {
    Livewire::test(StudentImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-siswa.xlsx');

    $path = studentImportWorkbook([
        ['IMP-001', 'Ahmad Fauzan', 'Ahmad', 'VII A', 'L', 'Bandung', '2014-05-02', 'Bapak Fauzan', '081234567890', 'Ibu Fauzan', '+6281234567890', 'Jl. Pendidikan'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('siswa.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(StudentImport::class)
            ->set('academicYearId', (string) $this->futureYear->id)
            ->set('importContext', StudentImportService::CONTEXT_PROSPECTIVE)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('Siswa Baru')
            ->assertSee('Semua data valid');

        expect(Student::query()->count())->toBe(0);

        $component
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Import Berhasil')
            ->assertSee('1 siswa diproses');

        expect(Student::query()->where('nis', 'IMP-001')->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('numbers preview rows from one without changing original xlsx row numbers', function () {
    $path = studentImportWorkbook([
        ['IMP-001', 'Ahmad Fauzan', 'Ahmad', 'VII A', 'L', 'Bandung', '2014-05-02', '', '', '', '', 'Jl. Pendidikan'],
        ['IMP-002', 'Budi Santoso', 'Budi', 'VII Z', 'L', '', '', '', '', '', '', 'Jl. Sekolah'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('siswa.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(StudentImport::class)
            ->set('academicYearId', (string) $this->futureYear->id)
            ->set('importContext', StudentImportService::CONTEXT_PROSPECTIVE)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertSet('step', 2)
            ->assertSeeHtml('data-preview-position="1">1</td>')
            ->assertSeeHtml('data-preview-position="2">2</td>')
            ->assertDontSee('Belum pernah terdaftar sebelumnya')
            ->assertSee('Bermasalah')
            ->assertSee('Kelas VII Z tidak ditemukan.');

        expect(collect($component->get('preview.rows'))->pluck('row_number')->all())->toBe([2, 3]);
    } finally {
        @unlink($path);
    }
});
