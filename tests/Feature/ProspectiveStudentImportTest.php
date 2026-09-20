<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentImport;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentImportService;
use App\Services\ProspectiveStudentImportSpreadsheet;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

function prospectiveImportRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'nama_lengkap' => 'Ahmad Fauzan',
        'kelas' => 'VII A',
        'nama_panggilan' => '',
        'jenis_kelamin' => '',
        'nama_orang_tua' => '',
        'no_telp_orang_tua' => '',
        'alamat' => '',
        'notes' => '',
    ], $overrides);
}

function prospectiveImportWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA CALON SISWA');
    $writer->addRow(Row::fromValues(ProspectiveStudentImportSpreadsheet::HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

function prospectiveImportTypeWithRate(int $classLevel, int $amount = 350000, SchoolLevel $schoolLevel = SchoolLevel::SMP): array
{
    $type = PaymentType::create([
        'name' => 'Formulir Pendaftaran Import Test',
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

beforeEach(function () {
    $this->activeYear = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
    $this->futureYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $this->actingAs(User::factory()->create());
});

it('halaman import calon siswa dapat dirender', function () {
    $this->get('/calon-siswa/import')
        ->assertOk()
        ->assertSee('Import Calon Siswa')
        ->assertSee('Download Template')
        ->assertSee('Tahun Ajaran Tujuan');
});

it('template import dapat diunduh', function () {
    Livewire::test(ProspectiveStudentImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-calon-siswa.xlsx');
});

it('template import memiliki sheet DATA CALON SISWA, REFERENSI KELAS, dan PANDUAN', function () {
    $path = app(ProspectiveStudentImportSpreadsheet::class)->createTemplate();

    try {
        $reader = new Reader;
        $reader->open($path);

        try {
            $sheetNames = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetNames[] = $sheet->getName();
            }
        } finally {
            $reader->close();
        }
    } finally {
        @unlink($path);
    }

    expect($sheetNames)->toBe(['DATA CALON SISWA', 'REFERENSI KELAS', 'PANDUAN']);
});

it('header template import calon siswa sesuai spesifikasi', function () {
    expect(ProspectiveStudentImportSpreadsheet::HEADERS)->toBe([
        'Nama Lengkap',
        'Kelas Tujuan',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Nama Orang Tua',
        'No Telp Orang Tua',
        'Alamat',
        'Catatan',
    ]);
});

it('tahun ajaran tujuan wajib dipilih untuk preview import', function () {
    $path = prospectiveImportWorkbook([
        ['Ahmad Fauzan', 'VII A'],
    ]);
    $upload = UploadedFile::fake()->createWithContent('calon.xlsx', file_get_contents($path));

    try {
        Livewire::test(ProspectiveStudentImport::class)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertHasErrors(['academicYearId']);
    } finally {
        @unlink($path);
    }
});

it('menolak import ke tahun ajaran aktif (harus lebih baru dari tahun aktif)', function () {
    $rows = app(ProspectiveStudentImportSpreadsheet::class)->read(prospectiveImportWorkbook([
        ['Ahmad Fauzan', 'VII A'],
    ]));

    expect(fn () => app(ProspectiveStudentImportService::class)->preview($rows, $this->activeYear->id))
        ->toThrow(ValidationException::class);
});

it('menerima import ke tahun ajaran yang lebih baru dari tahun aktif', function () {
    $rows = app(ProspectiveStudentImportSpreadsheet::class)->read(prospectiveImportWorkbook([
        ['Ahmad Fauzan', 'VII A'],
    ]));

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['academic_year'])->toBe('2027/2028');
});

it('nama lengkap wajib diisi pada tiap baris', function () {
    $rows = [prospectiveImportRow(['nama_lengkap' => ''])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['errors'])->toContain('Nama Lengkap wajib diisi.');
});

it('kelas tujuan wajib diisi pada tiap baris', function () {
    $rows = [prospectiveImportRow(['kelas' => ''])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['errors'])->toContain('Kelas Tujuan wajib diisi.');
});

it('kelas yang tidak dikenal ditandai bermasalah', function () {
    $rows = [prospectiveImportRow(['kelas' => 'VII Z'])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['errors'])->toContain('Kelas VII Z tidak ditemukan.');
});

it('biodata opsional terimpor dengan benar', function () {
    $rows = [
        prospectiveImportRow([
            'nama_lengkap' => 'Ahmad Fauzan',
            'nama_panggilan' => 'Ahmad',
            'jenis_kelamin' => 'L',
            'nama_orang_tua' => 'Bapak Fauzan',
            'no_telp_orang_tua' => '081234567890',
            'alamat' => 'Jl. Pendidikan No. 1',
            'notes' => 'Keringanan biaya',
        ]),
    ];

    $result = app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);
    $prospect = ProspectiveStudent::query()->sole();

    expect($result['total'])->toBe(1)
        ->and($prospect->nama_lengkap)->toBe('Ahmad Fauzan')
        ->and($prospect->nama_panggilan)->toBe('Ahmad')
        ->and($prospect->jenis_kelamin)->toBe('L')
        ->and($prospect->nama_orang_tua)->toBe('Bapak Fauzan')
        ->and($prospect->no_telp_orang_tua)->toBe('081234567890')
        ->and($prospect->alamat)->toBe('Jl. Pendidikan No. 1')
        ->and($prospect->notes)->toBe('Keringanan biaya');
});

it('menerima jenis kelamin L', function () {
    $rows = [prospectiveImportRow(['jenis_kelamin' => 'L'])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeFalse();
});

it('menerima jenis kelamin P', function () {
    $rows = [prospectiveImportRow(['jenis_kelamin' => 'P'])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeFalse();
});

it('menolak jenis kelamin selain L atau P', function () {
    $rows = [prospectiveImportRow(['jenis_kelamin' => 'X'])];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['errors'])->toContain('Jenis Kelamin harus L atau P.');
});

it('duplikat nama dan kelas hanya memunculkan peringatan', function () {
    $rows = [
        prospectiveImportRow(['row_number' => 2]),
        prospectiveImportRow(['row_number' => 3, 'nama_lengkap' => 'Ahmad Fauzan', 'kelas' => 'VII A']),
    ];

    $preview = app(ProspectiveStudentImportService::class)->preview($rows, $this->futureYear->id);

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($preview['rows'][0]['warnings'])->toContain('Nama dan kelas yang sama ditemukan lebih dari sekali.');

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    expect(ProspectiveStudent::query()->count())->toBe(2);
});

it('menghasilkan nomor pendaftaran dengan generator yang ada', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $prospect = ProspectiveStudent::query()->sole();

    expect($prospect->registration_number)->toBe('REG-2027-000001')
        ->and($prospect->registration_number)->toMatch('/^REG-2027-\d{6}$/');
});

it('nomor pendaftaran yang dihasilkan berurutan dan unik', function () {
    $rows = [
        prospectiveImportRow(),
        prospectiveImportRow(['row_number' => 3, 'nama_lengkap' => 'Budi Santoso']),
    ];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $numbers = ProspectiveStudent::query()->orderBy('id')->pluck('registration_number')->all();

    expect($numbers)->toBe(['REG-2027-000001', 'REG-2027-000002'])
        ->and(count($numbers))->toBe(count(array_unique($numbers)));
});

it('calon siswa impor berstatus registered', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    expect(ProspectiveStudent::query()->sole()->status)->toBe(ProspectiveStudentStatus::Registered);
});

it('calon siswa terimpor ke tahun ajaran tujuan yang dipilih', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $prospect = ProspectiveStudent::query()->sole();

    expect($prospect->academic_year_id)->toBe($this->futureYear->id);
});

it('calon siswa terimpor ke kelas tujuan yang terisi pada file', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $prospect = ProspectiveStudent::query()->sole();

    expect($prospect->school_class_id)->toBe($this->class7->id);
});

it('membangkitkan tagihan pendaftaran untuk calon siswa hasil impor', function () {
    [$type] = prospectiveImportTypeWithRate(7, 350000);
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $prospect = ProspectiveStudent::query()->sole();

    expect($prospect->bills)->toHaveCount(1)
        ->and($prospect->bills->first()->payment_type_id)->toBe($type->id);
});

it('menggunakan tarif pendaftaran yang benar untuk jenjang dan kelas tujuan', function () {
    prospectiveImportTypeWithRate(7, 300000);
    prospectiveImportTypeWithRate(8, 350000);
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    $bill = ProspectiveStudent::query()->sole()->bills->first();

    expect($bill->amount)->toBe('300000.00')
        ->and($bill->academic_year)->toBe('2027/2028')
        ->and($bill->billing_frequency)->toBe(BillFrequency::OneTime);
});

it('import calon siswa tidak membuat data Student', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    expect(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0);
});

it('import calon siswa tidak membuat StudentBill', function () {
    $rows = [prospectiveImportRow()];

    app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id);

    expect(StudentBill::query()->count())->toBe(0);
});

it('satu baris bermasalah membatalkan seluruh import', function () {
    $rows = [
        prospectiveImportRow(),
        prospectiveImportRow(['row_number' => 3, 'nama_lengkap' => 'Budi Santoso', 'kelas' => 'VII Z']),
    ];

    expect(fn () => app(ProspectiveStudentImportService::class)->import($rows, $this->futureYear->id))
        ->toThrow(ValidationException::class);

    expect(ProspectiveStudent::query()->count())->toBe(0)
        ->and(ProspectiveStudentBill::query()->count())->toBe(0);
});

it('rollback seluruh baris ketika terjadi kegagalan runtime di tengah import', function () {
    prospectiveImportTypeWithRate(7, 350000);

    $realService = app(ProspectiveStudentBillGenerationService::class);
    $mockBilling = Mockery::mock(ProspectiveStudentBillGenerationService::class);
    $calls = 0;
    $mockBilling->shouldReceive('generateFor')->twice()->andReturnUsing(
        function ($prospect) use ($realService, &$calls) {
            $calls++;

            if ($calls === 2) {
                throw new RuntimeException('Simulasi kegagalan baris kedua.');
            }

            return $realService->generateFor($prospect);
        }
    );
    $service = new ProspectiveStudentImportService(
        app(ProspectiveStudentRegistrationNumberGenerator::class),
        $mockBilling,
    );

    expect(fn () => $service->import([
        prospectiveImportRow(),
        prospectiveImportRow(['row_number' => 3, 'nama_lengkap' => 'Budi Santoso']),
    ], $this->futureYear->id))->toThrow(RuntimeException::class);

    expect(ProspectiveStudent::query()->count())->toBe(0)
        ->and(ProspectiveStudentBill::query()->count())->toBe(0);
});

it('alur Livewire preview menampilkan pratinjau calon siswa', function () {
    $path = prospectiveImportWorkbook([
        ['Ahmad Fauzan', 'VII A', 'Ahmad', 'L', 'Bapak Fauzan', '081234567890', 'Jl. Pendidikan', ''],
    ]);
    $upload = UploadedFile::fake()->createWithContent('calon.xlsx', file_get_contents($path));

    try {
        Livewire::test(ProspectiveStudentImport::class)
            ->set('academicYearId', (string) $this->futureYear->id)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('Calon Siswa Baru')
            ->assertSee('Semua data valid');

        expect(ProspectiveStudent::query()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('alur Livewire confirm mengimpor dan menampilkan halaman selesai', function () {
    $path = prospectiveImportWorkbook([
        ['Ahmad Fauzan', 'VII A', 'Ahmad', 'L', 'Bapak Fauzan', '081234567890', 'Jl. Pendidikan', ''],
    ]);
    $upload = UploadedFile::fake()->createWithContent('calon.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(ProspectiveStudentImport::class)
            ->set('academicYearId', (string) $this->futureYear->id)
            ->set('file', $upload)
            ->call('previewImport')
            ->assertHasNoErrors();

        $component
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Import Berhasil')
            ->assertSee('1 calon siswa diproses');

        $prospect = ProspectiveStudent::query()->sole();

        expect($prospect->registration_number)->toBe('REG-2027-000001')
            ->and($prospect->nama_lengkap)->toBe('Ahmad Fauzan');
    } finally {
        @unlink($path);
    }
});
