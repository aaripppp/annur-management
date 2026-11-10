<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverRecap;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function jemputanExportUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanExportYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function jemputanExportStudent(AcademicYear $year, string $name, string $className = 'VIII Export', int $level = 8): array
{
    $class = SchoolClass::query()->firstOrCreate(['name' => $className], ['level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return [$student, $class];
}

function jemputanExportAssign(JemputanDriver $driver, Student $student, AcademicYear $year, string $from = '2026-07-01', ?string $until = null): void
{
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

function jemputanExportBill(Student $student, PaymentType $type, int $month, float $amount, int $year = 2026): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => $amount,
    ]);
}

/** @param array<int, float> $allocations */
function jemputanExportPayment(
    Student $student,
    Bank $bank,
    string $date,
    array $allocations,
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'JEX-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => array_sum($allocations),
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => auth()->id(),
    ]);

    foreach ($allocations as $billId => $amount) {
        $bill = StudentBill::query()->findOrFail($billId);
        PaymentDetail::query()->create([
            'payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'payment_type_id' => $bill->payment_type_id,
            'period_month' => $bill->period_month,
            'period_year' => $bill->period_year,
            'amount' => $amount,
        ]);
    }

    return $payment;
}

function jemputanExportPath(TestResponse $response): string
{
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    return $response->baseResponse->getFile()->getPathname();
}

/** @return array<string, list<array<int, mixed>>> */
function jemputanExportWorkbook(string $path): array
{
    $reader = new Reader;
    $sheets = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

/** @return list<array<int, mixed>> */
function jemputanExportDataRows(array $rows): array
{
    return array_values(array_filter($rows, fn (array $row): bool => is_numeric($row[0] ?? null)));
}

function jemputanExportWorksheetXml(string $path, int $sheetNumber = 1): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $xml = $archive->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
    $archive->close();

    return is_string($xml) ? $xml : '';
}

beforeEach(function () {
    $this->actingAs(jemputanExportUser());
});

it('exports one sanitized and unique worksheet per year-relevant driver including inactive history', function () {
    $year = jemputanExportYear();
    [$firstStudent] = jemputanExportStudent($year, 'Siswa Sopir Pertama');
    [$secondStudent] = jemputanExportStudent($year, 'Siswa Sopir Kedua', 'VIII B');
    [$thirdStudent] = jemputanExportStudent($year, 'Siswa Sopir Ketiga', 'VIII C');
    $first = JemputanDriver::factory()->create(['name' => 'Pak/Duplikat:*?']);
    $second = JemputanDriver::factory()->inactive()->create(['name' => 'Pak/Duplikat:*?']);
    $third = JemputanDriver::factory()->create(['name' => 'Nama Sopir Yang Sangat Panjang Melebihi Tiga Puluh Satu Karakter']);
    JemputanDriver::factory()->create(['name' => 'Tanpa Penugasan']);
    jemputanExportAssign($first, $firstStudent, $year);
    jemputanExportAssign($second, $secondStudent, $year);
    jemputanExportAssign($third, $thirdStudent, $year);

    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'all',
        'academic_year_id' => $year->id,
        'driver_id' => $first->id,
    ]));
    $path = jemputanExportPath($response);

    try {
        $response->assertOk()->assertDownload('Rekap_Jemputan_2026-2027.xlsx');
        $sheets = jemputanExportWorkbook($path);
        $sheetNames = array_keys($sheets);

        expect($sheetNames)->toHaveCount(3)
            ->and(mb_strlen($sheetNames[0]))->toBeLessThanOrEqual(31)
            ->and($sheetNames[1])->toBe('PAK-DUPLIKAT-')
            ->and($sheetNames[2])->toBe('PAK-DUPLIKAT- (2)')
            ->and(implode('', $sheetNames))->not->toContain('TANPA PENUGASAN')
            ->and($sheets[$sheetNames[1]][1][0])->toBe('REKAP JEMPUTAN')
            ->and($sheets[$sheetNames[2]][2][0])->toContain('Pak/Duplikat:*?');
    } finally {
        @unlink($path);
    }
});

it('exports one filtered driver sheet and respects historical year level and class', function () {
    $year = jemputanExportYear();
    [$matchingStudent, $matchingClass] = jemputanExportStudent($year, 'Siswa Cocok', 'VIII Cocok', 8);
    [$otherStudent] = jemputanExportStudent($year, 'Siswa Beda Kelas', 'VIII Lain', 8);
    [$smaStudent] = jemputanExportStudent($year, 'Siswa SMA', 'XI Export', 11);
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Filter']);
    jemputanExportAssign($driver, $matchingStudent, $year);
    jemputanExportAssign($driver, $otherStudent, $year);
    jemputanExportAssign($driver, $smaStudent, $year);

    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'school_level' => 'SMP',
        'class_id' => $matchingClass->id,
    ]));
    $path = jemputanExportPath($response);

    try {
        $sheets = jemputanExportWorkbook($path);
        $rows = jemputanExportDataRows(reset($sheets));

        expect($sheets)->toHaveCount(1)
            ->and($rows)->toHaveCount(1)
            ->and($rows[0][1])->toBe('SISWA COCOK')
            ->and($rows[0][2])->toBe('VIII Cocok');
    } finally {
        @unlink($path);
    }
});

it('writes July through June headers numeric allocations and latest active metadata only', function (string $mode) {
    $year = jemputanExportYear();
    [$student] = jemputanExportStudent($year, 'Siswa Akurat');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Akurat']);
    jemputanExportAssign($driver, $student, $year);
    $jemputan = PaymentType::factory()->create(['name' => 'Jemputan']);
    $adm = PaymentType::factory()->create(['name' => 'Adm Jemputan']);
    $spp = PaymentType::factory()->create(['name' => 'SPP']);
    $jemputanBill = jemputanExportBill($student, $jemputan, 7, 500000);
    $admBill = jemputanExportBill($student, $adm, 7, 50000);
    $sppBill = jemputanExportBill($student, $spp, 7, 900000);
    $oldBank = Bank::factory()->create(['name' => 'Bank Lama Export', 'account_number' => '111111']);
    $newBank = Bank::factory()->create(['name' => 'Bank Baru Export', 'account_number' => '9876543210']);
    jemputanExportPayment($student, $oldBank, '2026-07-10', [
        $jemputanBill->id => 100000,
        $admBill->id => 50000,
        $sppBill->id => 900000,
    ]);
    jemputanExportPayment($student, $newBank, '2026-07-20', [$jemputanBill->id => 150000]);
    jemputanExportPayment($student, $newBank, '2026-07-25', [$jemputanBill->id => 500000], Payment::STATUS_CANCELLED);

    $parameters = [
        'mode' => $mode,
        'academic_year_id' => $year->id,
    ];

    if ($mode === 'filtered') {
        $parameters['driver_id'] = $driver->id;
    }

    $response = $this->get(route('jemputan.recap.export', $parameters));
    $path = jemputanExportPath($response);

    try {
        $sheets = jemputanExportWorkbook($path);
        $sheetRows = reset($sheets);
        $data = jemputanExportDataRows($sheetRows)[0];
        $months = array_values(array_filter($sheetRows[4], fn ($value): bool => is_string($value) && $value !== ''));

        expect($months)->toBe(['NO.', 'NAMA SISWA', 'KELAS', 'TARIF JEMPUTAN', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER', 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI'])
            ->and($data[3])->toBe(500000)
            ->and($data[4])->toBe(250000)
            ->and($data[4])->toBeInt()
            ->and($data[5])->toBe('20/07/2026')
            ->and($data[6])->toBe('Bank Baru Export · 9876543210')
            ->and($data[7])->toBe(0);

        $worksheetXml = jemputanExportWorksheetXml($path);
        expect($worksheetXml)->toContain('state="frozen"')
            ->and($worksheetXml)->toContain('topLeftCell="E7"')
            ->and($worksheetXml)->toContain('mergeCell ref="E5:G5"');
    } finally {
        @unlink($path);
    }
})->with(['filtered', 'all']);

it('exports a moved student only on the current driver sheet', function () {
    $year = jemputanExportYear();
    [$student] = jemputanExportStudent($year, 'Siswa Pindah Export');
    $oldDriver = JemputanDriver::factory()->create(['name' => 'A Sopir Lama']);
    $newDriver = JemputanDriver::factory()->create(['name' => 'B Sopir Baru']);
    jemputanExportAssign($oldDriver, $student, $year, '2026-07-01', '2026-09-30');
    jemputanExportAssign($newDriver, $student, $year, '2026-10-01');
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $july = jemputanExportBill($student, $type, 7, 500000);
    $june = jemputanExportBill($student, $type, 6, 600000, 2027);
    $bank = Bank::factory()->create();
    jemputanExportPayment($student, $bank, '2026-07-01', [$july->id => 500000]);
    jemputanExportPayment($student, $bank, '2027-06-02', [$june->id => 600000]);

    $response = $this->get(route('jemputan.recap.export', ['mode' => 'all', 'academic_year_id' => $year->id]));
    $path = jemputanExportPath($response);

    try {
        $sheets = jemputanExportWorkbook($path);
        $newRow = jemputanExportDataRows($sheets['B SOPIR BARU'])[0];

        expect($sheets)->not->toHaveKey('A SOPIR LAMA')
            ->and($newRow[4])->toBe(500000)
            ->and($newRow[37])->toBe(600000);
    } finally {
        @unlink($path);
    }
});

it('streams every service page with sequential unique students', function () {
    $year = jemputanExportYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Banyak']);

    foreach (range(1, 201) as $index) {
        [$student] = jemputanExportStudent($year, 'Siswa Chunk '.str_pad((string) $index, 3, '0', STR_PAD_LEFT));
        jemputanExportAssign($driver, $student, $year);
    }

    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
    ]));
    $path = jemputanExportPath($response);

    try {
        $sheets = jemputanExportWorkbook($path);
        $rows = jemputanExportDataRows(reset($sheets));

        expect($rows)->toHaveCount(201)
            ->and(array_column($rows, 0))->toBe(range(1, 201))
            ->and(array_unique(array_column($rows, 1)))->toHaveCount(201);
    } finally {
        @unlink($path);
    }
});

it('includes a year-relevant driver sheet even when optional filters produce zero rows', function () {
    $year = jemputanExportYear();
    [$student] = jemputanExportStudent($year, 'Siswa SMP Kosong');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Kosong Filter']);
    jemputanExportAssign($driver, $student, $year);

    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'all',
        'academic_year_id' => $year->id,
        'school_level' => 'SMA',
    ]));
    $path = jemputanExportPath($response);

    try {
        $sheets = jemputanExportWorkbook($path);
        expect($sheets)->toHaveKey('SOPIR KOSONG FILTER')
            ->and(jemputanExportDataRows($sheets['SOPIR KOSONG FILTER']))->toBeEmpty()
            ->and(collect($sheets['SOPIR KOSONG FILTER'])->flatten()->all())->toContain('Tidak ada siswa yang sesuai dengan filter.');
    } finally {
        @unlink($path);
    }
});

it('validates mode driver academic year level and historical class combinations', function () {
    $year = jemputanExportYear();
    [, $class] = jemputanExportStudent($year, 'Siswa Validasi', 'VIII Validasi', 8);
    $driver = JemputanDriver::factory()->create();

    $this->get(route('jemputan.recap.export', ['mode' => 'filtered', 'academic_year_id' => $year->id]))
        ->assertSessionHasErrors('driver_id');

    $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'school_level' => 'SMA',
        'class_id' => $class->id,
    ]))->assertSessionHasErrors('class_id');

    $this->get(route('jemputan.recap.export', ['mode' => 'wrong', 'academic_year_id' => 999999]))
        ->assertSessionHasErrors(['mode', 'academic_year_id']);
});

it('uses current Livewire filters and requires a driver only in filtered mode', function () {
    $year = jemputanExportYear();
    [$student, $class] = jemputanExportStudent($year, 'Siswa Livewire');
    $driver = JemputanDriver::factory()->create();
    jemputanExportAssign($driver, $student, $year);

    Livewire::test(JemputanDriverRecap::class)
        ->assertSet('exportMode', 'all')
        ->assertSee('Semua Sopir')
        ->assertSee('Berdasarkan Filter')
        ->set('exportMode', 'filtered')
        ->call('exportExcel')
        ->assertHasErrors('selectedDriverId')
        ->set('selectedDriverId', (string) $driver->id)
        ->set('selectedSchoolLevel', 'SMP')
        ->set('selectedSchoolClassId', (string) $class->id)
        ->call('exportExcel')
        ->assertRedirect(route('jemputan.recap.export', [
            'mode' => 'filtered',
            'academic_year_id' => $year->id,
            'driver_id' => $driver->id,
            'school_level' => 'SMP',
            'class_id' => $class->id,
        ]));
});

it('allows active Admin and Superadmin exports', function (string $role) {
    auth()->logout();
    $this->actingAs(jemputanExportUser($role));
    $year = jemputanExportYear();
    [$student] = jemputanExportStudent($year, 'Siswa Otorisasi');
    $driver = JemputanDriver::factory()->create();
    jemputanExportAssign($driver, $student, $year);

    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
    ]));
    $path = jemputanExportPath($response);

    try {
        $response->assertOk();
    } finally {
        @unlink($path);
    }
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies guest TU Unit and inactive users at the export endpoint', function () {
    auth()->logout();
    $parameters = ['mode' => 'all', 'academic_year_id' => jemputanExportYear()->id];

    $this->get(route('jemputan.recap.export', $parameters))->assertRedirect(route('login'));
    $this->actingAs(jemputanExportUser(User::ROLE_TU_UNIT))
        ->get(route('jemputan.recap.export', $parameters))
        ->assertRedirect(route('tu.pending'));
    $this->actingAs(jemputanExportUser(User::ROLE_ADMIN, false))
        ->get(route('jemputan.recap.export', $parameters))
        ->assertRedirect(route('login'));
});

it('performs no financial writes while generating a workbook', function () {
    $year = jemputanExportYear();
    [$student] = jemputanExportStudent($year, 'Siswa Read Only Export');
    $driver = JemputanDriver::factory()->create();
    jemputanExportAssign($driver, $student, $year);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
    ]));
    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1
    );
    DB::disableQueryLog();
    $path = jemputanExportPath($response);

    try {
        expect($writes)->toBeEmpty();
    } finally {
        @unlink($path);
    }
});

it('keeps one-chunk export queries constant as students grow', function (int $studentCount) {
    $year = jemputanExportYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Query']);

    foreach (range(1, $studentCount) as $index) {
        [$student] = jemputanExportStudent($year, 'Siswa Query '.$index);
        jemputanExportAssign($driver, $student, $year);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get(route('jemputan.recap.export', [
        'mode' => 'filtered',
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
    ]));
    $selectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::disableQueryLog();
    $path = jemputanExportPath($response);
    @unlink($path);

    expect($selectCount)->toBe(10);
})->with([1, 10, 25, 50]);
