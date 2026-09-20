<?php

use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentBulkUpdate;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\ProspectiveStudentBulkUpdateSpreadsheet;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

function prospectiveBulkRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'id_sistem' => 1,
        'no_pendaftaran' => 'REG-2027-000001',
        'tahun_ajaran' => '2027/2028',
        'kelas' => 'VII A',
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => '',
        'jenis_kelamin' => 'L',
        'nama_orang_tua' => '',
        'no_telp_orang_tua' => '',
        'alamat' => '',
        'notes' => '',
    ], $overrides);
}

function prospectiveBulkWorkbook(array $valueRows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(Row::fromValues(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS));

    foreach ($valueRows as $valueRow) {
        $writer->addRow(Row::fromValues($valueRow));
    }

    $writer->close();

    return $path;
}

function makeBulkProspect(array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-2027-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Fauzan',
        'jenis_kelamin' => 'L',
        'nama_orang_tua' => 'Bapak Fauzan',
        'no_telp_orang_tua' => '081234567890',
        'alamat' => 'Jl. Pendidikan No. 1',
        'notes' => null,
    ], $overrides));
}

beforeEach(function () {
    $this->activeYear = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
    $this->academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $this->actingAs(User::factory()->create());
});

it('halaman update calon siswa dapat dirender', function () {
    $this->get('/calon-siswa/update')
        ->assertOk()
        ->assertSee('Update Data Calon Siswa')
        ->assertSee('Download File Calon Siswa')
        ->assertSee('Preview Perubahan');
});

it('file update data calon siswa dapat diunduh', function () {
    Livewire::test(ProspectiveStudentBulkUpdate::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('update-data-calon-siswa.xlsx');
});

it('file update memuat sheet DATA CALON SISWA dan PANDUAN', function () {
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, null, null);

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

    expect($sheetNames)->toBe([ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME, 'PANDUAN']);
});

it('header file update data calon siswa sesuai spesifikasi', function () {
    expect(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)->toBe([
        'ID Sistem',
        'No. Pendaftaran',
        'Tahun Ajaran Tujuan',
        'Kelas Tujuan',
        'Nama Lengkap',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Nama Orang Tua',
        'No Telp Orang Tua',
        'Alamat',
        'Catatan',
    ]);
});

it('ekspor menyertakan calon siswa dengan ID Sistem', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, null, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id_sistem'])->toBe($prospect->id)
        ->and($rows[0]['no_pendaftaran'])->toBe($prospect->registration_number)
        ->and($rows[0]['tahun_ajaran'])->toBe('2027/2028')
        ->and($rows[0]['kelas'])->toBe('VII A')
        ->and($rows[0]['nama_lengkap'])->toBe('Ahmad Fauzan')
        ->and($rows[0]['no_telp_orang_tua'])->toBe('081234567890');
});

it('ekspor mengikuti filter tahun ajaran tujuan', function () {
    makeBulkProspect(['academic_year_id' => $this->academicYear->id, 'school_class_id' => $this->class7->id]);
    $otherYear = AcademicYear::create([
        'year' => '2028/2029',
        'is_active' => false,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);
    makeBulkProspect(['academic_year_id' => $otherYear->id, 'school_class_id' => $this->class7->id]);

    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($this->academicYear->id, null, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['tahun_ajaran'])->toBe('2027/2028');
});

it('ekspor mengikuti filter jenjang', function () {
    makeBulkProspect(['academic_year_id' => $this->academicYear->id, 'school_class_id' => $this->class7->id]);
    $classSd = SchoolClass::create(['name' => 'I A', 'level' => 5]);
    $sdProspect = makeBulkProspect([
        'nama_lengkap' => 'Ani Susanti',
        'registration_number' => 'REG-2027-000001',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $classSd->id,
    ]);

    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, SchoolLevel::SD, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id_sistem'])->toBe($sdProspect->id)
        ->and($rows[0]['kelas'])->toBe('I A');
});

it('ekspor mengikuti filter kelas tujuan', function () {
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    makeBulkProspect(['academic_year_id' => $this->academicYear->id, 'school_class_id' => $this->class7->id]);
    $target = makeBulkProspect([
        'registration_number' => 'REG-2027-000002',
        'nama_lengkap' => 'Budi Santoso',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $class8->id,
    ]);

    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, null, $class8->id);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id_sistem'])->toBe($target->id)
        ->and($rows[0]['kelas'])->toBe('VIII A');
});

it('calon siswa yang sudah dikonversi tidak ikut diekspor', function () {
    makeBulkProspect([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Converted,
        'converted_at' => now(),
    ]);
    $active = makeBulkProspect([
        'registration_number' => 'REG-2027-000002',
        'nama_lengkap' => 'Budi Santoso',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, null, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id_sistem'])->toBe($active->id);
});

it('pencocokan baris hanya melalui ID Sistem', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $originalNumber = $prospect->registration_number;

    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'no_pendaftaran' => 'BERUBAH-123',
            'tahun_ajaran' => '1999/2000',
            'kelas' => 'IX Z (tidak dikenal)',
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
    ];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['prospective_student_id'])->toBe($prospect->id);

    app(ProspectiveStudentBulkUpdateService::class)->apply($rows);

    $prospect->refresh();

    expect($prospect->nama_lengkap)->toBe('Ahmad Fauzan Baru')
        ->and($prospect->registration_number)->toBe($originalNumber)
        ->and($prospect->school_class_id)->toBe($this->class7->id)
        ->and($prospect->academic_year_id)->toBe($this->academicYear->id);
});

it('ID Sistem kosong ditandai GAGAL', function () {
    $rows = [prospectiveBulkRow(['id_sistem' => null])];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem tidak valid atau kosong.');
});

it('ID Sistem bukan angka ditandai GAGAL', function () {
    $rows = [prospectiveBulkRow(['id_sistem' => 'abc'])];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem harus berupa angka.');
});

it('ID Sistem tidak ditemukan ditandai GAGAL', function () {
    $rows = [prospectiveBulkRow(['id_sistem' => 999999])];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem tidak ditemukan.');
});

it('ID Sistem duplikat pada file ditandai GAGAL', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    $rows = [
        prospectiveBulkRow(['id_sistem' => $prospect->id]),
        prospectiveBulkRow(['row_number' => 3, 'id_sistem' => $prospect->id, 'nama_lengkap' => 'Budi Santoso']),
    ];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(2)
        ->and($preview['rows'][0]['errors'])->toContain("ID Sistem {$prospect->id} muncul lebih dari satu kali di file.")
        ->and($preview['rows'][1]['errors'])->toContain("ID Sistem {$prospect->id} muncul lebih dari satu kali di file.");
});

it('calon siswa yang sudah dikonversi tidak dapat diubah', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Converted,
        'converted_at' => now(),
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Nama Baru'])];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Calon siswa yang sudah dikonversi tidak dapat diubah melalui bulk update.');

    expect($service->apply($rows))->toBe(0)
        ->and($prospect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan');
});

it('calon siswa yang dibatalkan tidak dapat diubah', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Cancelled,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Nama Baru'])];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Calon siswa yang dibatalkan tidak dapat diubah melalui bulk update.')
        ->and($service->apply($rows))->toBe(0)
        ->and($prospect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan');
});

it('biodata yang diubah pada file diperbarui dengan diff yang benar', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'no_pendaftaran' => 'REG-2027-000001',
            'tahun_ajaran' => '2027/2028',
            'kelas' => 'VII A',
            'nama_lengkap' => 'Ahmad Fauzan Baru',
            'nama_panggilan' => 'Ahmad',
            'jenis_kelamin' => 'P',
            'nama_orang_tua' => 'Bapak Fauzan Baru',
            'no_telp_orang_tua' => '089999999999',
            'alamat' => 'Jl. Baru No. 2',
            'notes' => 'catatan baru',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['changes'][0])->toMatchArray([
            'field' => 'nama_lengkap',
            'label' => 'Nama Lengkap',
            'old' => 'Ahmad Fauzan',
            'new' => 'Ahmad Fauzan Baru',
        ]);

    $service->apply($rows);

    $prospect->refresh();

    expect($prospect->nama_lengkap)->toBe('Ahmad Fauzan Baru')
        ->and($prospect->nama_panggilan)->toBe('Ahmad')
        ->and($prospect->jenis_kelamin)->toBe('P')
        ->and($prospect->nama_orang_tua)->toBe('Bapak Fauzan Baru')
        ->and($prospect->no_telp_orang_tua)->toBe('089999999999')
        ->and($prospect->alamat)->toBe('Jl. Baru No. 2')
        ->and($prospect->notes)->toBe('catatan baru');
});

it('sel kosong mempertahankan nilai lama', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'no_pendaftaran' => '',
            'tahun_ajaran' => '',
            'kelas' => '',
            'nama_lengkap' => '',
            'nama_panggilan' => '',
            'jenis_kelamin' => '',
            'nama_orang_tua' => '',
            'no_telp_orang_tua' => '',
            'alamat' => '',
            'notes' => '',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['unchanged'])->toBe(1)
        ->and($preview['rows'][0]['status'])->toBe(ProspectiveStudentBulkUpdateService::STATUS_UNCHANGED);

    expect($service->apply($rows))->toBe(0)
        ->and($prospect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan');
});

it('baris tanpa perubahan berstatus TANPA PERUBAHAN', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'nama_lengkap' => 'Ahmad Fauzan',
            'nama_panggilan' => 'Fauzan',
            'jenis_kelamin' => 'L',
            'nama_orang_tua' => 'Bapak Fauzan',
            'no_telp_orang_tua' => '081234567890',
            'alamat' => 'Jl. Pendidikan No. 1',
            'notes' => '',
        ]),
    ];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['unchanged'])->toBe(1)
        ->and($preview['rows'][0]['changes'])->toBe([])
        ->and($preview['rows'][0]['status_label'])->toBe('TANPA PERUBAHAN');
});

it('jenis kelamin selain L atau P ditolak', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'jenis_kelamin' => 'X'])];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Nilai Jenis Kelamin tidak valid (harus L atau P).')
        ->and($prospect->fresh()->jenis_kelamin)->toBe('L');
});

it('teks lebih panjang dari batas ditolak', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_orang_tua' => str_repeat('X', 256)])];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Nama Orang Tua terlalu panjang (maksimal 255 karakter).');
});

it('perubahan No. Pendaftaran di file diabaikan', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $original = $prospect->registration_number;
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'no_pendaftaran' => 'REG-9999-999999',
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['rows'][0]['warnings'])->toContain('Perubahan No. Pendaftaran diabaikan.');

    $service->apply($rows);

    expect($prospect->fresh()->registration_number)->toBe($original);
});

it('perubahan Tahun Ajaran Tujuan di file diabaikan', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'tahun_ajaran' => '1999/2000',
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['rows'][0]['warnings'])->toContain('Perubahan Tahun Ajaran Tujuan diabaikan.');

    $service->apply($rows);

    expect($prospect->fresh()->academic_year_id)->toBe($this->academicYear->id);
});

it('perubahan Kelas Tujuan di file diabaikan', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'kelas' => 'IX Z',
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['rows'][0]['warnings'])->toContain('Perubahan Kelas Tujuan diabaikan. Gunakan form Edit Calon Siswa.');

    $service->apply($rows);

    expect($prospect->fresh()->school_class_id)->toBe($this->class7->id);
});

it('apply hanya memproses baris berstatus SIAP UPDATE', function () {
    $readyProspect = makeBulkProspect([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $unchangedProspect = makeBulkProspect([
        'registration_number' => 'REG-2027-000002',
        'nama_lengkap' => 'Budi Santoso',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'row_number' => 2,
            'id_sistem' => $readyProspect->id,
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
        prospectiveBulkRow([
            'row_number' => 3,
            'id_sistem' => 999999,
            'nama_lengkap' => 'Tidak Ada',
        ]),
        prospectiveBulkRow([
            'row_number' => 4,
            'id_sistem' => $unchangedProspect->id,
            'nama_lengkap' => 'Budi Santoso',
        ]),
    ];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['summary']['unchanged'])->toBe(1)
        ->and($preview['summary']['blocked'])->toBe(1);

    expect($service->apply($rows))->toBe(1);

    expect($readyProspect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan Baru')
        ->and($unchangedProspect->fresh()->nama_lengkap)->toBe('Budi Santoso');
});

it('update tidak melepas tagihan pendaftaran yang sudah ada', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $bill = ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $prospect->id,
        'amount' => 350000,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Ahmad Fauzan Baru'])];

    app(ProspectiveStudentBulkUpdateService::class)->apply($rows);

    expect($prospect->fresh()->bills)->toHaveCount(1)
        ->and($bill->fresh()->amount)->toBe('350000.00')
        ->and($bill->fresh()->prospective_student_id)->toBe($prospect->id);
});

it('update tidak membuat data Student atau StudentBill', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Ahmad Fauzan Baru'])];

    app(ProspectiveStudentBulkUpdateService::class)->apply($rows);

    expect($prospect->fresh()->converted_student_id)->toBeNull()
        ->and(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0);
});

it('membaca file dengan header tidak sesuai menolak dengan error Runtime', function () {
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-bad-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(Row::fromValues(['Kolom A', 'Kolom B', 'Kolom C', 'Kolom D', 'Kolom E', 'Kolom F', 'Kolom G', 'Kolom H', 'Kolom I', 'Kolom J', 'Kolom K']));
    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'Header file tidak sesuai template');
    } finally {
        @unlink($path);
    }
});

it('alur Livewire preview menampilkan pratinjau update', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $valueRow = [
        $prospect->id,
        $prospect->registration_number,
        '2027/2028',
        'VII A',
        'Ahmad Fauzan Baru',
        'Ahmad',
        'L',
        'Bapak Fauzan Baru',
        '081234567891',
        'Jl. Baru',
        'catatan baru',
    ];
    $path = prospectiveBulkWorkbook([$valueRow]);
    $upload = UploadedFile::fake()->createWithContent('update-calon.xlsx', file_get_contents($path));

    try {
        Livewire::test(ProspectiveStudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('File siap diperiksa')
            ->assertSee('SIAP UPDATE')
            ->assertSee('Total Baris')
            ->assertSet('summary.ready', 1);

        expect($prospect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan');
    } finally {
        @unlink($path);
    }
});

it('alur Livewire confirm mengupdate dan menampilkan halaman selesai', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $valueRow = [
        $prospect->id,
        $prospect->registration_number,
        '2027/2028',
        'VII A',
        'Ahmad Fauzan Baru',
        'Ahmad',
        'L',
        'Bapak Fauzan Baru',
        '081234567891',
        'Jl. Baru',
        'catatan baru',
    ];
    $path = prospectiveBulkWorkbook([$valueRow]);
    $upload = UploadedFile::fake()->createWithContent('update-calon.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(ProspectiveStudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->assertHasNoErrors();

        $component
            ->call('confirmUpdate')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Update Selesai')
            ->assertSee('1 calon siswa diperbarui');

        expect($prospect->fresh()->nama_lengkap)->toBe('Ahmad Fauzan Baru')
            ->and($prospect->fresh()->nama_panggilan)->toBe('Ahmad');
    } finally {
        @unlink($path);
    }
});
