<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverSalary;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
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

function jemputanSalaryExportUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanSalaryExportYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function jemputanSalaryExportStudent(
    AcademicYear $year,
    string $name,
    string $className = 'VIII Ekspor',
    int $level = 8,
): array {
    $class = SchoolClass::query()->firstOrCreate(['name' => $className], ['level' => $level]);
    $student = Student::factory()->create([
        'nama_lengkap' => $name,
        'class_id' => $class->id,
        'alamat' => 'Alamat siswa tidak boleh menjadi tujuan',
    ]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return [$student, $class];
}

function jemputanSalaryExportAssign(
    JemputanDriver $driver,
    Student $student,
    AcademicYear $year,
    string $from = '2026-07-01',
    ?string $until = null,
): void {
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

function jemputanSalaryExportBill(
    Student $student,
    PaymentType $type,
    int $month,
    float $amount,
    int $year = 2026,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => $amount,
    ]);
}

function jemputanSalaryExportAllocation(
    Student $student,
    AcademicYear $year,
    JemputanDriver $driver,
    int $month,
    float $amount,
    int $periodYear = 2026,
): JemputanDriverSalaryAllocation {
    return JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'period_year' => $periodYear,
        'period_month' => $month,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

function jemputanSalaryExportPayment(
    Student $student,
    StudentBill $bill,
    Bank $bank,
    string $date,
    float $amount,
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'SAL-EXP-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => auth()->id(),
    ]);

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => $amount,
    ]);

    return $payment;
}

function jemputanSalaryExportPath(TestResponse $response): string
{
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    return $response->baseResponse->getFile()->getPathname();
}

/** @return array<string, list<array<int, mixed>>> */
function jemputanSalaryExportWorkbook(string $path): array
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
function jemputanSalaryExportDataRows(array $rows): array
{
    return array_values(array_filter($rows, fn (array $row): bool => is_numeric($row[0] ?? null)));
}

function jemputanSalaryExportRowStartingWith(array $rows, string $labelPrefix): ?array
{
    return jemputanSalaryExportRowCellStartingWith($rows, 0, $labelPrefix);
}

function jemputanSalaryExportRowCellStartingWith(array $rows, int $column, string $prefix): ?array
{
    foreach ($rows as $row) {
        if (str_starts_with((string) ($row[$column] ?? ''), $prefix)) {
            return $row;
        }
    }

    return null;
}

function jemputanSalaryExportRowByCell(array $rows, int $column, string $value): ?array
{
    foreach ($rows as $row) {
        if ((string) ($row[$column] ?? '') === $value) {
            return $row;
        }
    }

    return null;
}

function jemputanSalaryExportMergedRanges(string $path): array
{
    $worksheetXml = jemputanSalaryExportWorksheetXml($path);
    preg_match_all('/<mergeCell ref="([^"]+)"/', $worksheetXml, $matches);

    return $matches[1] ?? [];
}

function jemputanSalaryExportLastDataRowIndex(array $rows): ?int
{
    $lastIndex = null;

    foreach (array_values($rows) as $index => $row) {
        if (is_numeric($row[0] ?? null)) {
            $lastIndex = $index;
        }
    }

    return $lastIndex;
}

function jemputanSalaryExportHeaderRow(array $rows): ?array
{
    foreach ($rows as $row) {
        if (($row[0] ?? null) === 'No' && ($row[1] ?? null) === 'Nama') {
            return $row;
        }
    }

    return null;
}

function jemputanSalaryExportWorksheetXml(string $path, int $sheetNumber = 1): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $xml = $archive->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
    $archive->close();

    return is_string($xml) ? $xml : '';
}

function jemputanSalaryExportWorkbookXml(string $path): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $xml = $archive->getFromName('xl/workbook.xml');
    $archive->close();

    return is_string($xml) ? $xml : '';
}

function jemputanSalaryExportStylesXml(string $path): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $xml = $archive->getFromName('xl/styles.xml');
    $archive->close();

    return is_string($xml) ? $xml : '';
}

function jemputanSalaryExportCellFillColor(string $path, string $cell): ?string
{
    $sheetXml = jemputanSalaryExportWorksheetXml($path);

    if (preg_match('/<c r="'.preg_quote($cell, '/').'"[^>]*\bs="(\d+)"/', $sheetXml, $matches) !== 1) {
        return null;
    }

    $stylesXml = jemputanSalaryExportStylesXml($path);
    $styles = simplexml_load_string($stylesXml);

    if ($styles === false) {
        return null;
    }

    $styles->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $xfs = $styles->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]');
    $fillId = (int) ($xfs[(int) $matches[1]]['fillId'] ?? 0);
    $fills = $styles->xpath('//*[local-name()="fills"]/*[local-name()="fill"]');
    $colors = $fills[$fillId]->xpath('.//*[local-name()="fgColor"]');

    return $colors === false || $colors === [] ? null : (string) $colors[0]['rgb'];
}

function jemputanSalaryExportZipEntries(string $path): array
{
    $archive = new ZipArchive;
    $entries = [];

    if ($archive->open($path) === true) {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entries[] = (string) $archive->getNameIndex($index);
        }

        $archive->close();
    }

    return $entries;
}

beforeEach(function () {
    $this->actingAs(jemputanSalaryExportUser());
});

it('exports one worksheet with dynamic title period metadata and signature', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Ekspor');
    $driver = JemputanDriver::factory()->create([
        'name' => 'Sopir Export',
        'foundation_deduction_percentage' => 10,
    ]);
    jemputanSalaryExportAssign($driver, $student, $year);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $bill = jemputanSalaryExportBill($student, $type, 7, 500000);
    $bank = Bank::factory()->create(['name' => 'Bank Export', 'account_number' => '987654']);
    jemputanSalaryExportPayment($student, $bill, $bank, '2026-07-15', 500000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $response->assertOk()->assertDownload('Gaji_Sopir_Sopir-Export_2026-07.xlsx');
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = reset($sheets);
        $data = jemputanSalaryExportDataRows($rows)[0];
        $title = jemputanSalaryExportRowStartingWith($rows, 'DATA JEMPUTAN SISWA TP.');
        $driverRow = jemputanSalaryExportRowStartingWith($rows, 'Nama Sopir:');
        $periodRow = jemputanSalaryExportRowStartingWith($rows, 'Periode:');
        $groupRow = jemputanSalaryExportHeaderRow($rows);
        $detailRow = jemputanSalaryExportRowByCell($rows, 5, 'Jumlah');

        expect($sheets)->toHaveCount(1)
            ->and(array_key_first($sheets))->toBe('GAJI_SOPIR')
            ->and($title[0])->toBe('DATA JEMPUTAN SISWA TP. 2026-2027')
            ->and($driverRow[0])->toBe('Nama Sopir: Sopir Export')
            ->and($periodRow[0])->toBe('Periode: Juli 2026')
            ->and($groupRow)->toBe(['No', 'Nama', 'Kelas', 'Tujuan', 'Ket', 'JULI 2026', '', ''])
            ->and($detailRow)->toBe(['', '', '', '', '', 'Jumlah', 'Tanggal', 'Jenis'])
            ->and(collect($rows)->flatten()->all())->not->toContain('Identitas Siswa')
            ->and($data[0])->toBe(1)
            ->and($data[1])->toBe($student->nama_lengkap)
            ->and($data[2])->toBe('VIII Ekspor')
            ->and($data[3])->toBe('')
            ->and($data[4])->toBe('')
            ->and($data[5])->toBe(500000)
            ->and($data[6])->toBe('15/07/2026')
            ->and($data[7])->toBe('Bank Export · 987654')
            ->and(collect($rows)->flatten()->filter(fn ($value): bool => is_string($value))->implode(' '))->toContain('Cikarang, ')
            ->and(collect($rows)->flatten()->all())->toContain('Penerima,')
            ->and(jemputanSalaryExportZipEntries($path))->not->toContain('xl/drawings/drawing1.xml');
    } finally {
        @unlink($path);
    }
});

it('writes full effective amounts for paid partial and unpaid students with editable numeric cells', function () {
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $jemputan = PaymentType::factory()->create(['name' => 'Jemputan']);
    PaymentType::factory()->create(['name' => 'SPP']);
    $bank = Bank::factory()->create(['name' => 'Bank Nominal', 'account_number' => '123456']);

    [$paidStudent] = jemputanSalaryExportStudent($year, 'Siswa Lunas Ekspor', 'VIII Lunas Ekspor');
    [$partialStudent] = jemputanSalaryExportStudent($year, 'Siswa Sebagian Ekspor', 'VIII Sebagian Ekspor');
    [$unpaidStudent] = jemputanSalaryExportStudent($year, 'Siswa Belum Ekspor', 'VIII Belum Ekspor');

    foreach ([$paidStudent, $partialStudent, $unpaidStudent] as $student) {
        jemputanSalaryExportAssign($driver, $student, $year);
    }

    $paidBill = jemputanSalaryExportBill($paidStudent, $jemputan, 7, 500000);
    $partialBill = jemputanSalaryExportBill($partialStudent, $jemputan, 7, 400000);
    jemputanSalaryExportBill($unpaidStudent, $jemputan, 7, 300000);
    jemputanSalaryExportPayment($paidStudent, $paidBill, $bank, '2026-07-10', 500000);
    jemputanSalaryExportPayment($partialStudent, $partialBill, $bank, '2026-07-11', 150000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = reset($sheets);
        $data = collect(jemputanSalaryExportDataRows($rows))->keyBy(fn (array $row): string => (string) $row[1]);
        $totalRow = jemputanSalaryExportRowByCell($rows, 0, 'TOTAL');
        $revenue = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Pendapatan ');
        $deduction = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Potongan Yayasan (10%)');
        $net = jemputanSalaryExportRowByCell($rows, 5, 'Total');
        $revenueRowNumber = 6 + count($data) + 3;
        $deductionRowNumber = $revenueRowNumber + 1;

        expect($data)->toHaveCount(3)
            ->and($data[$paidStudent->nama_lengkap][5])->toBe(500000)
            ->and($data[$partialStudent->nama_lengkap][5])->toBe(400000)
            ->and($data[$unpaidStudent->nama_lengkap][5])->toBe(300000)
            ->and(is_numeric($data[$paidStudent->nama_lengkap][5]))->toBeTrue()
            ->and($totalRow[5])->toBe('=SUM(F6:F8)')
            ->and($totalRow[6])->toBe('')
            ->and($totalRow[7])->toBe('')
            ->and($revenue[7])->toBe('=F9')
            ->and($deduction[7])->toBe('=ROUND(F9*10/100,0)')
            ->and($net[7])->toBe('=H'.$revenueRowNumber.'-H'.$deductionRowNumber);

        $worksheetXml = jemputanSalaryExportWorksheetXml($path);
        expect($worksheetXml)->toContain('<f>SUM(F6:F8)</f>')
            ->and($worksheetXml)->toContain('<f>F9</f>')
            ->and($worksheetXml)->toContain('<f>ROUND(F9*10/100,0)</f>')
            ->and($worksheetXml)->toContain('<f>H'.$revenueRowNumber.'-H'.$deductionRowNumber.'</f>')
            ->and(preg_match('/<c r="F6"[^>]*><v>\d+<\/v><\/c>/', $worksheetXml))->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('places a merged total row directly after the last student row and keeps date and type empty', function () {
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $first = jemputanSalaryExportStudent($year, 'Siswa Total A', 'VIII Total A')[0];
    $second = jemputanSalaryExportStudent($year, 'Siswa Total B', 'VIII Total B')[0];
    jemputanSalaryExportAssign($driver, $first, $year);
    jemputanSalaryExportAssign($driver, $second, $year);
    jemputanSalaryExportBill($first, $type, 7, 500000);
    jemputanSalaryExportBill($second, $type, 7, 250000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = array_values(reset($sheets));
        $lastDataIndex = jemputanSalaryExportLastDataRowIndex($rows);
        $totalRow = $rows[$lastDataIndex + 1];

        expect($lastDataIndex)->not->toBeNull()
            ->and($totalRow[0])->toBe('TOTAL')
            ->and($totalRow[5])->toBe('=SUM(F6:F7)')
            ->and($totalRow[6])->toBe('')
            ->and($totalRow[7])->toBe('')
            ->and(jemputanSalaryExportMergedRanges($path))->toContain('A8:E8');
    } finally {
        @unlink($path);
    }
});

it('places rincian and a blank centered signature block on the lower right below the total', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Tanda Tangan');
    $driver = JemputanDriver::factory()->create([
        'name' => 'Sopir Tanda Tangan',
        'foundation_deduction_percentage' => 10,
    ]);
    jemputanSalaryExportAssign($driver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = array_values(reset($sheets));
        $rincian = jemputanSalaryExportRowByCell($rows, 5, 'Rincian:');
        $revenue = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Pendapatan ');
        $deduction = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Potongan Yayasan (10%)');
        $net = jemputanSalaryExportRowByCell($rows, 5, 'Total');
        $date = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Cikarang, ');
        $receiver = jemputanSalaryExportRowByCell($rows, 5, 'Penerima,');
        $line = jemputanSalaryExportRowByCell($rows, 5, '_____________________');
        $name = jemputanSalaryExportRowByCell($rows, 5, 'Sopir Tanda Tangan');
        $merges = jemputanSalaryExportMergedRanges($path);
        $ordered = collect($rows)
            ->map(fn (array $row): string => (string) ($row[5] ?? ''))
            ->filter()
            ->values();
        $totalPosition = $ordered->search('Total');
        $datePosition = $ordered->search(fn (string $value): bool => str_starts_with($value, 'Cikarang, '));
        $namePosition = $ordered->search('Sopir Tanda Tangan');

        expect($rincian[5])->toBe('Rincian:')
            ->and($revenue[5])->toBe('Pendapatan Juli 2026')
            ->and($revenue[7])->toBe('=F7')
            ->and($deduction[5])->toBe('Potongan Yayasan (10%)')
            ->and($deduction[7])->toBe('=ROUND(F7*10/100,0)')
            ->and($net[5])->toBe('Total')
            ->and($net[7])->toBe('=H10-H11')
            ->and($date[5])->toStartWith('Cikarang, ')
            ->and($receiver[5])->toBe('Penerima,')
            ->and($line[5])->toBe('_____________________')
            ->and($name[5])->toBe('Sopir Tanda Tangan')
            ->and($totalPosition)->not->toBeFalse()
            ->and($datePosition)->toBeGreaterThan($totalPosition)
            ->and($namePosition)->toBeGreaterThan($datePosition)
            ->and($merges)->toContain(
                'A7:E7',
                'F9:H9',
                'F10:G10',
                'F11:G11',
                'F12:G12',
                'F14:H14',
                'F15:H15',
                'F16:H16',
                'F17:H17',
                'F18:H18',
                'F19:H19',
                'F20:H20',
                'F21:H21',
            )
            ->and(jemputanSalaryExportZipEntries($path))->not->toContain('xl/drawings/drawing1.xml');
    } finally {
        @unlink($path);
    }
});

it('links total rincian and net salary formulas to editable student amounts', function () {
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 12.5]);
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $first = jemputanSalaryExportStudent($year, 'Siswa Formula A', 'VIII Formula A')[0];
    $second = jemputanSalaryExportStudent($year, 'Siswa Formula B', 'VIII Formula B')[0];
    jemputanSalaryExportAssign($driver, $first, $year);
    jemputanSalaryExportAssign($driver, $second, $year);
    jemputanSalaryExportBill($first, $type, 7, 400000);
    jemputanSalaryExportBill($second, $type, 7, 600000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = array_values(reset($sheets));
        $total = jemputanSalaryExportRowByCell($rows, 0, 'TOTAL');
        $revenue = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Pendapatan ');
        $deduction = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Potongan Yayasan (12,5%)');
        $net = jemputanSalaryExportRowByCell($rows, 5, 'Total');

        expect($total[5])->toBe('=SUM(F6:F7)')
            ->and($revenue[7])->toBe('=F8')
            ->and($deduction[7])->toBe('=ROUND(F8*12.5/100,0)')
            ->and($net[7])->toBe('=H11-H12')
            ->and($rows[5][5])->toBe(400000)
            ->and($rows[6][5])->toBe(600000);
    } finally {
        @unlink($path);
    }
});

it('exports each driver allocated amount when the multi-driver allocation is valid', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Multi Valid Ekspor', 'VIII Multi Valid Ekspor');
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir Valid A', 'foundation_deduction_percentage' => 10]);
    $secondDriver = JemputanDriver::factory()->create(['name' => 'Sopir Valid B', 'foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($firstDriver, $student, $year);
    jemputanSalaryExportAssign($secondDriver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);
    jemputanSalaryExportAllocation($student, $year, $firstDriver, 7, 450000);
    jemputanSalaryExportAllocation($student, $year, $secondDriver, 7, 250000);

    $expectedAmounts = [$firstDriver->id => 450000, $secondDriver->id => 250000];

    foreach ([$firstDriver, $secondDriver] as $driver) {
        $response = $this->get(route('jemputan.salary.export', [
            'academic_year_id' => $year->id,
            'driver_id' => $driver->id,
            'month' => '2026-07',
        ]));
        $path = jemputanSalaryExportPath($response);

        try {
            $sheets = jemputanSalaryExportWorkbook($path);
            $rows = reset($sheets);
            $data = jemputanSalaryExportDataRows($rows)[0];
            $flat = collect($rows)->flatten()->filter(fn ($value): bool => is_string($value))->implode(' ');

            expect($data[3])->toBe('')
                ->and($data[4])->toBe('MULTI SOPIR - ALOKASI')
                ->and($data[5])->toBe($expectedAmounts[$driver->id])
                ->and(is_numeric($data[5]))->toBeTrue()
                ->and($flat)->toContain('Catatan: Nominal siswa Multi-Sopir sudah mengikuti alokasi yang tersimpan.')
                ->and($flat)->not->toContain('belum memiliki alokasi valid')
                ->and(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'F6')))->toEndWith('FFF2CC');
        } finally {
            @unlink($path);
        }
    }
});

it('marks missing multi-driver allocations with a fallback warning and soft yellow nominal', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Multi Ekspor', 'VIII Multi Ekspor');
    $drivers = JemputanDriver::factory()->count(2)->create(['foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($drivers[0], $student, $year);
    jemputanSalaryExportAssign($drivers[1], $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    foreach ($drivers as $driver) {
        $response = $this->get(route('jemputan.salary.export', [
            'academic_year_id' => $year->id,
            'driver_id' => $driver->id,
            'month' => '2026-07',
        ]));
        $path = jemputanSalaryExportPath($response);

        try {
            $sheets = jemputanSalaryExportWorkbook($path);
            $rows = reset($sheets);
            $data = jemputanSalaryExportDataRows($rows)[0];
            $flat = collect($rows)->flatten()->filter(fn ($value): bool => is_string($value))->implode(' ');

            expect($data[3])->toBe('')
                ->and($data[4])->toBe('MULTI SOPIR - BELUM DIALOKASIKAN')
                ->and($data[5])->toBe(700000)
                ->and($flat)->toContain('Catatan: Masih ada siswa Multi-Sopir yang belum memiliki alokasi valid. Nominal merupakan angka sementara dan harus diperiksa sebelum laporan digunakan.')
                ->and($flat)->not->toContain('Sesuaikan nominal sebelum laporan digunakan')
                ->and(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'F6')))->toEndWith('FFF2CC');
        } finally {
            @unlink($path);
        }
    }
});

it('marks stale or orphan multi-driver allocations with a safe fallback warning', function (string $scenario) {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Multi '.$scenario, 'VIII Multi '.$scenario);
    $firstDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $secondDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($firstDriver, $student, $year);
    jemputanSalaryExportAssign($secondDriver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 700000);

    if ($scenario === 'stale') {
        jemputanSalaryExportAllocation($student, $year, $firstDriver, 7, 350000);
        jemputanSalaryExportAllocation($student, $year, $secondDriver, 7, 250000);
    } else {
        $nonMemberDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
        jemputanSalaryExportAllocation($student, $year, $firstDriver, 7, 450000);
        jemputanSalaryExportAllocation($student, $year, $nonMemberDriver, 7, 250000);
    }

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $firstDriver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = reset($sheets);
        $data = jemputanSalaryExportDataRows($rows)[0];
        $flat = collect($rows)->flatten()->filter(fn ($value): bool => is_string($value))->implode(' ');

        expect($data[4])->toBe('MULTI SOPIR - PERLU DIPERBAIKI')
            ->and($data[5])->toBe(700000)
            ->and($flat)->toContain('Catatan: Masih ada siswa Multi-Sopir yang belum memiliki alokasi valid. Nominal merupakan angka sementara dan harus diperiksa sebelum laporan digunakan.')
            ->and(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'F6')))->toEndWith('FFF2CC');
    } finally {
        @unlink($path);
    }
})->with(['stale', 'orphan']);

it('exports every filtered student beyond the active pagination page', function () {
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);

    foreach (range(1, 201) as $index) {
        [$student] = jemputanSalaryExportStudent($year, 'Siswa Ekspor '.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'Kelas Ekspor');
        jemputanSalaryExportAssign($driver, $student, $year);
    }

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = reset($sheets);
        $data = jemputanSalaryExportDataRows($rows);
        $totalRow = jemputanSalaryExportRowByCell($rows, 0, 'TOTAL');
        $revenue = jemputanSalaryExportRowCellStartingWith($rows, 5, 'Pendapatan ');

        expect($data)->toHaveCount(201)
            ->and(array_column($data, 0))->toBe(range(1, 201))
            ->and(array_unique(array_column($data, 1)))->toHaveCount(201)
            ->and($totalRow[5])->toBe('=SUM(F6:F206)')
            ->and($revenue[7])->toBe('=F207');
    } finally {
        @unlink($path);
    }
});

it('honors the selected driver month and academic year filters', function () {
    $year = jemputanSalaryExportYear();
    $jemputan = PaymentType::factory()->create(['name' => 'Jemputan']);
    $selectedDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $otherDriver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    [$julyStudent] = jemputanSalaryExportStudent($year, 'Siswa Juli Ekspor', 'VIII Juli Ekspor');
    [$augustStudent] = jemputanSalaryExportStudent($year, 'Siswa Agustus Ekspor', 'VIII Agustus Ekspor');
    [$otherStudent] = jemputanSalaryExportStudent($year, 'Siswa Sopir Lain', 'VIII Lain Ekspor');
    jemputanSalaryExportAssign($selectedDriver, $julyStudent, $year);
    jemputanSalaryExportAssign($selectedDriver, $augustStudent, $year);
    jemputanSalaryExportAssign($otherDriver, $otherStudent, $year);
    jemputanSalaryExportBill($julyStudent, $jemputan, 7, 100000);
    jemputanSalaryExportBill($augustStudent, $jemputan, 8, 250000);
    jemputanSalaryExportBill($otherStudent, $jemputan, 8, 900000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $selectedDriver->id,
        'month' => '2026-08',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $sheets = jemputanSalaryExportWorkbook($path);
        $rows = reset($sheets);
        $data = collect(jemputanSalaryExportDataRows($rows))->keyBy(fn (array $row): string => (string) $row[1]);

        expect($data)->toHaveCount(2)
            ->and($data->keys()->all())->not->toContain($otherStudent->nama_lengkap)
            ->and(jemputanSalaryExportRowStartingWith($rows, 'Periode:')[0])->toBe('Periode: Agustus 2026')
            ->and($data[$augustStudent->nama_lengkap][5])->toBe(250000)
            ->and($data[$julyStudent->nama_lengkap][5])->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('rejects a null foundation percentage safely from the endpoint and Livewire', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Tanpa Persentase Ekspor');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => null]);
    jemputanSalaryExportAssign($driver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]))->assertSessionHasErrors('export');

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->set('selectedMonth', '2026-07')
        ->call('exportExcel')
        ->assertHasErrors('export')
        ->assertSee('Master Sopir');
});

it('redirects Livewire export using the active filters', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Filter Ekspor');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($driver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    Livewire::test(JemputanDriverSalary::class)
        ->set('selectedDriverId', (string) $driver->id)
        ->set('selectedMonth', '2026-07')
        ->call('exportExcel')
        ->assertRedirect(route('jemputan.salary.export', [
            'academic_year_id' => $year->id,
            'driver_id' => $driver->id,
            'month' => '2026-07',
        ]));
});

it('validates the export month against the academic year', function () {
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);

    $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2027-07',
    ]))->assertSessionHasErrors('month');

    $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-13',
    ]))->assertSessionHasErrors('month');
});

it('allows active Admin and Superadmin salary exports', function (string $role) {
    auth()->logout();
    $this->actingAs(jemputanSalaryExportUser($role));
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Otorisasi Ekspor');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($driver, $student, $year);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $response->assertOk();
    } finally {
        @unlink($path);
    }
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies guests TU Unit and inactive users at the salary export endpoint', function () {
    auth()->logout();
    $year = jemputanSalaryExportYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    $parameters = [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ];

    $this->get(route('jemputan.salary.export', $parameters))->assertRedirect(route('login'));
    $this->actingAs(jemputanSalaryExportUser(User::ROLE_TU_UNIT))
        ->get(route('jemputan.salary.export', $parameters))
        ->assertRedirect(route('tu.pending'));
    $this->actingAs(jemputanSalaryExportUser(User::ROLE_ADMIN, false))
        ->get(route('jemputan.salary.export', $parameters))
        ->assertRedirect(route('login'));
});

it('uses soft identity and period colors unlimited print titles and a safe initial view', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Gaya Ekspor');
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Gaya', 'foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($driver, $student, $year);
    jemputanSalaryExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 7, 500000);

    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $path = jemputanSalaryExportPath($response);

    try {
        $stylesXml = jemputanSalaryExportStylesXml($path);
        $worksheetXml = jemputanSalaryExportWorksheetXml($path);
        $workbookXml = jemputanSalaryExportWorkbookXml($path);

        expect(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'A4')))->toEndWith('D9EAF7')
            ->and(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'F4')))->toEndWith('FFF2CC')
            ->and(strtoupper((string) jemputanSalaryExportCellFillColor($path, 'B6')))->toEndWith('FFFFFF')
            ->and($stylesXml)->toContain('&quot;Rp&quot; #,##0')
            ->and($worksheetXml)->toContain('topLeftCell="A1"')
            ->and($worksheetXml)->not->toContain('state="frozen"')
            ->and($worksheetXml)->not->toContain('<pane')
            ->and($worksheetXml)->toContain('orientation="portrait"')
            ->and($worksheetXml)->toContain('paperSize="9"')
            ->and($worksheetXml)->toContain('fitToWidth="1"')
            ->and($worksheetXml)->toContain('fitToHeight="0"')
            ->and($workbookXml)->toContain('_xlnm.Print_Titles')
            ->and($workbookXml)->toContain('GAJI_SOPIR!4:5');
    } finally {
        @unlink($path);
    }
});

it('performs no financial writes during salary export', function () {
    $year = jemputanSalaryExportYear();
    [$student] = jemputanSalaryExportStudent($year, 'Siswa Read Only Ekspor');
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => 10]);
    jemputanSalaryExportAssign($driver, $student, $year);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get(route('jemputan.salary.export', [
        'academic_year_id' => $year->id,
        'driver_id' => $driver->id,
        'month' => '2026-07',
    ]));
    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1
    );
    DB::disableQueryLog();
    $path = jemputanSalaryExportPath($response);

    try {
        expect($writes)->toBeEmpty();
    } finally {
        @unlink($path);
    }
});

it('keeps export queries bounded as the student count grows', function () {
    $year = jemputanSalaryExportYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $selectCounts = [];

    foreach ([1, 10, 25] as $studentCount) {
        $driver = JemputanDriver::factory()->create(['name' => 'Sopir Query '.$studentCount, 'foundation_deduction_percentage' => 10]);

        foreach (range(1, $studentCount) as $index) {
            [$student] = jemputanSalaryExportStudent($year, 'Siswa Query Ekspor '.$studentCount.'-'.$index, 'Kelas Query Ekspor');
            jemputanSalaryExportAssign($driver, $student, $year);
            jemputanSalaryExportBill($student, $type, 7, 100000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('jemputan.salary.export', [
            'academic_year_id' => $year->id,
            'driver_id' => $driver->id,
            'month' => '2026-07',
        ]));
        $selectCounts[] = collect(DB::getQueryLog())->pluck('query')->filter(
            fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
        )->count();
        DB::disableQueryLog();
        $path = jemputanSalaryExportPath($response);
        @unlink($path);
    }

    expect(array_unique($selectCounts))->toHaveCount(1);
});
