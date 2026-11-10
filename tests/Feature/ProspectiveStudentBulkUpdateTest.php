<?php

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentBulkUpdate;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\PpdbStudentDataSynchronizer;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\ProspectiveStudentBulkUpdateSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function prospectiveBulkRow(array $overrides = []): array
{
    $row = array_merge([
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

    $prospect = ctype_digit((string) ($row['id_sistem'] ?? ''))
        ? ProspectiveStudent::query()->with(['academicYear', 'schoolClass'])->find((int) $row['id_sistem'])
        : null;

    if ($prospect !== null) {
        if (! array_key_exists('no_pendaftaran', $overrides)) {
            $row['no_pendaftaran'] = $prospect->registration_number;
        }

        if (! array_key_exists('tahun_ajaran', $overrides)) {
            $row['tahun_ajaran'] = $prospect->academicYear?->year ?? '';
        }

        if (! array_key_exists('kelas', $overrides)) {
            $row['kelas'] = $prospect->schoolClass?->name ?? '-';
        }

        if (! array_key_exists('snapshot_token', $overrides)) {
            $row['snapshot_token'] = app(ProspectiveStudentBulkUpdateService::class)->snapshotToken($prospect);
        }
    } else {
        $row['snapshot_token'] = $overrides['snapshot_token'] ?? '';
    }

    return $row;
}

function prospectiveBulkWorkbook(array $valueRows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(Row::fromValues(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS));

    foreach ($valueRows as $valueRow) {
        if (array_is_list($valueRow) && count($valueRow) === 11) {
            [$id, $registrationNumber, $academicYear, $class, $name, $nickname, $gender, $parentName, $parentPhone, $address, $notes] = $valueRow;
            $prospect = ProspectiveStudent::query()->find((int) $valueRow[0]);
            $updates = array_fill_keys(array_column(ProspectiveStudentBulkUpdateSpreadsheet::UPDATE_COLUMNS, 'key'), '');
            $updates = array_replace($updates, [
                'nama_lengkap' => $name,
                'nama_panggilan' => $nickname,
                'jenis_kelamin' => $gender,
                'nama_orang_tua' => $parentName,
                'no_telp_orang_tua' => $parentPhone,
                'alamat' => $address,
                'notes' => $notes,
            ]);
            $valueRow = [
                $id,
                $registrationNumber,
                $academicYear,
                $class,
                $prospect === null ? '' : app(ProspectiveStudentBulkUpdateService::class)->snapshotToken($prospect),
                ...array_map(fn (array $column): mixed => $updates[$column['key']], ProspectiveStudentBulkUpdateSpreadsheet::UPDATE_COLUMNS),
            ];
        }

        $writer->addRow(Row::fromValues($valueRow));
    }

    $writer->close();

    return $path;
}

/** @return list<int> */
function prospectiveBulkExportIds(?int $academicYearId = null, ?SchoolLevel $schoolLevel = null, ?int $classId = null): array
{
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($academicYearId, $schoolLevel, $classId);

    try {
        return collect(app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->pluck('id_sistem')
            ->all();
    } finally {
        @unlink($path);
    }
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

/** @return array{0: ProspectiveStudent, 1: Student} */
function makeBulkConvertedProspect(array $prospectOverrides = [], array $studentOverrides = []): array
{
    $student = Student::factory()->create(array_merge([
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Fauzan',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Pendidikan No. 1',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2014-01-02',
        'nama_ayah' => 'Bapak Fauzan',
        'no_telp_ayah' => '081234567890',
        'nama_ibu' => 'Ibu Fauzan',
        'no_telp_ibu' => '081234567891',
    ], $studentOverrides));
    $prospect = makeBulkProspect(array_merge([
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ], $prospectOverrides));

    return [$prospect, $student];
}

function makeBulkParent(ProspectiveStudent $prospect, string $role, array $overrides = []): PpdbParent
{
    return PpdbParent::query()->create(array_merge([
        'prospective_student_id' => $prospect->id,
        'role' => $role,
        'status_hidup' => $role === 'guardian' ? null : 'hidup',
        'nama' => strtoupper($role).' LAMA',
        'nik' => '0011223344556677',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '1980-01-02',
        'pendidikan_terakhir' => 'SMA',
        'email' => $role.'@example.test',
        'no_telp' => '081234567890',
        'occupation' => 'Wiraswasta',
        'income_range' => ParentIncomeRange::ThreeToFourMillion,
    ], $overrides));
}

beforeEach(function () {
    config(['cache.ppdb_bulk_preview_store' => 'array']);
    Cache::store('array')->clear();
    $this->activeYear = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
    $this->academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $this->actingAs(User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
    ]));
});

it('halaman update calon siswa dapat dirender', function () {
    $this->get('/calon-siswa/update')
        ->assertOk()
        ->assertSee('Update Data Calon Siswa')
        ->assertSee('Download File Calon Siswa')
        ->assertSee('Field yang terkait siswa aktif disinkronkan otomatis.')
        ->assertSee('Preview Perubahan');
});

it('mengizinkan admin aktif dan superadmin aktif membuka bulk update', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    $this->get(route('calon-siswa.update'))->assertOk();
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('menolak admin nonaktif dan role yang tidak didukung', function (string $role, bool $active) {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => $active]);
    $user->setAttribute('role', $role);
    $this->actingAs($user);

    $this->get(route('calon-siswa.update'))->assertForbidden();
})->with([
    'admin nonaktif' => [User::ROLE_ADMIN, false],
    'role tidak didukung' => ['auditor', true],
]);

it('menolak tamu membuka bulk update', function () {
    auth()->logout();

    $this->get(route('calon-siswa.update'))->assertRedirect(route('login'));
});

it('memeriksa ulang otorisasi pada setiap action setelah akun dinonaktifkan', function (string $action) {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($user);
    $component = Livewire::test(ProspectiveStudentBulkUpdate::class);
    $user->update(['is_active' => false]);

    if ($action === 'updatedFilterLevel') {
        $component->set('filterLevel', SchoolLevel::SMP->value)->assertForbidden();
    } else {
        $component->call($action)->assertForbidden();
    }
})->with([
    'download' => 'downloadTemplate',
    'preview' => 'previewUpdate',
    'confirm' => 'confirmUpdate',
    'reset' => 'backToSetup',
    'filter reset' => 'updatedFilterLevel',
]);

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

it('panduan menjelaskan kategori clear conditional stale dan all-or-nothing', function () {
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create(null, null, null);

    try {
        $reader = new Reader;
        $reader->open($path);
        $guide = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'PANDUAN') {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $guide[] = implode(' ', array_map(fn (mixed $value): string => (string) $value, $row->toArray()));
            }
        }

        $reader->close();
        $text = implode(' ', $guide);
    } finally {
        @unlink($path);
    }

    expect($text)->toContain(
        '#CLEAR#',
        PpdbApplicantCategory::ANNUR_FAMILY->value,
        'more_than_1_km',
        'Ayah, Ibu, dan Wali',
        'alias legacy Ayah',
        ParentIncomeRange::NoIncome->value,
        'Maksimum 500 baris',
        'stale',
        'all-or-nothing',
    );
});

it('header file update data calon siswa sesuai spesifikasi', function () {
    expect(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)->toHaveCount(74)
        ->and(array_slice(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, 0, 6))->toBe([
            'ID Sistem',
            'No. Pendaftaran',
            'Tahun Ajaran Tujuan',
            'Kelas Tujuan',
            'Token Snapshot',
            'Kategori Pendaftar',
        ])
        ->and(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)->toContain(
            'Nama Lengkap',
            'NISN',
            'Jumlah Saudara',
            'Moda Transportasi Lainnya',
            'Keterangan Kebutuhan Khusus',
            'Ayah - Status Hidup',
            'Ibu - Nama',
            'Wali - Penghasilan',
        );
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
        ->and($rows[0]['nama_lengkap'])->toBe('AHMAD FAUZAN')
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

it('urutan export mengikuti default listing calon siswa yaitu created at terbaru', function () {
    $oldest = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $middle = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $newest = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    DB::table('prospective_students')->where('id', $oldest->id)->update(['created_at' => '2026-01-01 08:00:00']);
    DB::table('prospective_students')->where('id', $middle->id)->update(['created_at' => '2026-02-01 08:00:00']);
    DB::table('prospective_students')->where('id', $newest->id)->update(['created_at' => '2026-03-01 08:00:00']);
    $listingIds = ProspectiveStudent::query()
        ->whereIn('id', [$oldest->id, $middle->id, $newest->id])
        ->latest()
        ->orderByDesc('id')
        ->pluck('id')
        ->all();

    expect(prospectiveBulkExportIds($this->academicYear->id))->toBe($listingIds)
        ->and($listingIds)->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('mengurutkan applicant dalam tahun ajaran yang sama dari data terbaru', function () {
    $first = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $second = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    DB::table('prospective_students')->where('id', $first->id)->update(['created_at' => '2026-04-01 08:00:00']);
    DB::table('prospective_students')->where('id', $second->id)->update(['created_at' => '2026-04-02 08:00:00']);

    expect(prospectiveBulkExportIds($this->academicYear->id))->toBe([$second->id, $first->id]);
});

it('menggunakan id terbaru sebagai tie breaker ketika created at sama', function () {
    $first = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $second = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    DB::table('prospective_students')
        ->whereIn('id', [$first->id, $second->id])
        ->update(['created_at' => '2026-05-01 08:00:00']);

    expect(prospectiveBulkExportIds($this->academicYear->id))->toBe([$second->id, $first->id]);
});

it('mempertahankan urutan terbaru pada campuran registered dan converted', function () {
    $registered = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);
    [$converted] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    DB::table('prospective_students')->where('id', $registered->id)->update(['created_at' => '2026-06-01 08:00:00']);
    DB::table('prospective_students')->where('id', $converted->id)->update(['created_at' => '2026-06-02 08:00:00']);

    expect(prospectiveBulkExportIds($this->academicYear->id))->toBe([$converted->id, $registered->id]);
});

it('calon siswa terdaftar dan dikonversi diekspor sementara yang dibatalkan tidak', function () {
    [$converted] = makeBulkConvertedProspect([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Ahmad Fauzan',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    makeBulkProspect([
        'registration_number' => 'REG-2027-000003',
        'nama_lengkap' => 'Calon Dibatalkan',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Cancelled,
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

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('id_sistem')->all())->toContain($converted->id, $active->id);
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
            'nama_lengkap' => 'Ahmad Fauzan Baru',
        ]),
    ];

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['prospective_student_id'])->toBe($prospect->id);

    app(ProspectiveStudentBulkUpdateService::class)->apply($rows);

    $prospect->refresh();

    expect($prospect->nama_lengkap)->toBe('AHMAD FAUZAN BARU')
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

it('No. Pendaftaran kosong diblokir', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([
        prospectiveBulkRow(['id_sistem' => $prospect->id, 'no_pendaftaran' => '']),
    ]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('No. Pendaftaran wajib tersedia sebagai pemeriksaan konsistensi.');
});

it('calon siswa yang sudah dikonversi dapat dipreview dan diubah bersama siswa terkait', function () {
    [$prospect, $student] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Nama Baru'])];
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $preview = $service->preview($rows);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['summary']['blocked'])->toBe(0)
        ->and($service->apply($rows))->toBe(1)
        ->and($prospect->fresh()->nama_lengkap)->toBe('NAMA BARU')
        ->and($student->fresh()->nama_lengkap)->toBe('NAMA BARU');
});

it('menyinkronkan hanya mapping biodata yang sudah ditetapkan ke siswa hasil konversi', function () {
    [$prospect, $student] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ], [
        'nis' => '0012345',
        'foto' => 'students/original.jpg',
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2014-01-02',
        'asal_sekolah' => 'Sekolah Lama',
    ]);
    makeBulkParent($prospect, 'father', ['nama' => 'Bapak Fauzan', 'no_telp' => '081234567890']);
    makeBulkParent($prospect, 'mother', ['nama' => 'Ibu Fauzan', 'no_telp' => '081234567891']);
    $unrelatedBefore = collect($student->getRawOriginal())->only(['nis', 'class_id', 'status', 'foto'])->all();
    $row = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nama_lengkap' => 'Nama Sinkron',
        'nama_panggilan' => 'Sinkron',
        'jenis_kelamin' => 'P',
        'alamat' => 'Alamat Sinkron',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-02-03',
        'asal_sekolah' => 'Sekolah PPDB Baru',
        'father_nama' => 'Ayah Sinkron',
        'father_no_telp' => '081111111111',
        'mother_nama' => 'Ibu Sinkron',
        'mother_no_telp' => '082222222222',
    ]);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([$row]))->toBe(1);

    $student->refresh();
    expect($student->nama_lengkap)->toBe('NAMA SINKRON')
        ->and($student->nama_panggilan)->toBe('SINKRON')
        ->and($student->jenis_kelamin)->toBe('P')
        ->and($student->alamat)->toBe('Alamat Sinkron')
        ->and($student->tempat_lahir)->toBe('Jakarta')
        ->and($student->tanggal_lahir->format('Y-m-d'))->toBe('2015-02-03')
        ->and($student->nama_ayah)->toBe('AYAH SINKRON')
        ->and($student->no_telp_ayah)->toBe('081111111111')
        ->and($student->nama_ibu)->toBe('IBU SINKRON')
        ->and($student->no_telp_ibu)->toBe('082222222222')
        ->and(collect($student->getRawOriginal())->only(['nis', 'class_id', 'status', 'foto'])->all())->toEqual($unrelatedBefore)
        ->and($prospect->fresh()->ppdbProfile->asal_sekolah)->toBe('Sekolah PPDB Baru');
});

it('perubahan field PPDB-only tidak menulis siswa hasil konversi', function () {
    [$prospect, $student] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $studentWrites = 0;
    DB::listen(function ($query) use (&$studentWrites): void {
        if (str_starts_with(strtolower(trim($query->sql)), 'update `students`')) {
            $studentWrites++;
        }
    });

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow(['id_sistem' => $prospect->id, 'asal_sekolah' => 'Sekolah PPDB Saja']),
    ]))->toBe(1)
        ->and($studentWrites)->toBe(0)
        ->and($student->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('calon siswa terdaftar tidak membuat atau memperbarui siswa', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);
    $studentCount = Student::query()->count();

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Registered Baru']),
    ]))->toBe(1)
        ->and(Student::query()->count())->toBe($studentCount)
        ->and($prospect->fresh()->converted_student_id)->toBeNull();
});

it('converted tanpa linked student diblokir tanpa fallback PPDB-only', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => null,
        'converted_at' => now(),
    ]);
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Tidak Boleh']);
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $preview = $service->preview([$row]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Relasi siswa hasil konversi tidak valid atau siswa tidak ditemukan.')
        ->and(fn () => $service->apply([$row]))->toThrow(ValidationException::class)
        ->and($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('perubahan link konversi setelah download diblokir oleh snapshot', function () {
    [$prospect] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Tidak Boleh']);
    $otherStudent = Student::factory()->create();
    $prospect->update(['converted_student_id' => $otherStudent->id]);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([$row]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and(implode(' ', $preview['rows'][0]['errors']))->toContain('token snapshot tidak valid');
});

it('perubahan siswa terkait setelah download diblokir oleh snapshot', function () {
    [$prospect, $student] = makeBulkConvertedProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'alamat' => 'Alamat dari Excel']);
    $student->update(['nama_lengkap' => 'Diubah Admin Lain']);
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $preview = $service->preview([$row]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and(implode(' ', $preview['rows'][0]['errors']))->toContain('token snapshot tidak valid')
        ->and(fn () => $service->apply([$row]))->toThrow(ValidationException::class)
        ->and($prospect->fresh()->alamat)->toBe('Jl. Pendidikan No. 1');
});

it('snapshot siswa mencakup seluruh field overlap yang disinkronkan', function () {
    $student = Student::factory()->create();
    $values = app(PpdbStudentDataSynchronizer::class)->snapshotValues($student);

    expect(array_keys($values))->toBe([
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'alamat',
        'tempat_lahir',
        'tanggal_lahir',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
    ]);
});

it('konflik siswa converted menggulung seluruh file termasuk baris valid', function () {
    [$firstProspect, $firstStudent] = makeBulkConvertedProspect([
        'registration_number' => 'REG-CONVERTED-001',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    [$secondProspect, $secondStudent] = makeBulkConvertedProspect([
        'registration_number' => 'REG-CONVERTED-002',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow(['row_number' => 2, 'id_sistem' => $firstProspect->id, 'nama_lengkap' => 'Baris Pertama']),
        prospectiveBulkRow(['row_number' => 3, 'id_sistem' => $secondProspect->id, 'nama_lengkap' => 'Baris Kedua']),
    ];
    $secondStudent->update(['alamat' => 'Diubah Bersamaan']);

    expect(fn () => app(ProspectiveStudentBulkUpdateService::class)->apply($rows))
        ->toThrow(ValidationException::class)
        ->and($firstProspect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN')
        ->and($firstStudent->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN')
        ->and($secondProspect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('status tidak dikenal tetap diblokir', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    DB::table('prospective_students')->where('id', $prospect->id)->update(['status' => 'unknown_status']);
    $prospect = ProspectiveStudent::query()->findOrFail($prospect->id);
    $row = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'snapshot_token' => app(ProspectiveStudentBulkUpdateService::class)->snapshotToken($prospect),
        'nama_lengkap' => 'Tidak Boleh',
    ]);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([$row]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Hanya calon siswa berstatus Terdaftar atau Dikonversi yang dapat diubah melalui bulk update.');
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
        ->and($preview['rows'][0]['errors'])->toContain('Hanya calon siswa berstatus Terdaftar atau Dikonversi yang dapat diubah melalui bulk update.');
    expect(fn () => $service->apply($rows))->toThrow(ValidationException::class);
    expect($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('biodata yang diubah pada file diperbarui dengan diff yang benar', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
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
            'old' => 'AHMAD FAUZAN',
            'new' => 'AHMAD FAUZAN BARU',
        ]);

    $service->apply($rows);

    $prospect->refresh();

    expect($prospect->nama_lengkap)->toBe('AHMAD FAUZAN BARU')
        ->and($prospect->nama_panggilan)->toBe('AHMAD')
        ->and($prospect->jenis_kelamin)->toBe('P')
        ->and($prospect->nama_orang_tua)->toBe('BAPAK FAUZAN BARU')
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
        ->and($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
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

it('perubahan No. Pendaftaran di file diblokir', function () {
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

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('No. Pendaftaran tidak cocok dengan ID Sistem.');

    expect(fn () => $service->apply($rows))->toThrow(ValidationException::class);

    expect($prospect->fresh()->registration_number)->toBe($original)
        ->and($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('perubahan Tahun Ajaran Tujuan di file diblokir', function () {
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

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Tahun Ajaran Tujuan tidak cocok dengan data calon siswa saat ini.');

    expect(fn () => $service->apply($rows))->toThrow(ValidationException::class);

    expect($prospect->fresh()->academic_year_id)->toBe($this->academicYear->id)
        ->and($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('perubahan Kelas Tujuan di file diblokir', function () {
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

    expect($preview['summary']['blocked'])->toBe(1)
        ->and($preview['rows'][0]['errors'])->toContain('Kelas Tujuan tidak cocok dengan data calon siswa saat ini.');

    expect(fn () => $service->apply($rows))->toThrow(ValidationException::class);

    expect($prospect->fresh()->school_class_id)->toBe($this->class7->id)
        ->and($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('apply membatalkan seluruh file ketika satu baris diblokir', function () {
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

    expect(fn () => $service->apply($rows))->toThrow(ValidationException::class);

    expect($readyProspect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN')
        ->and($unchangedProspect->fresh()->nama_lengkap)->toBe('BUDI SANTOSO');
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

it('menolak header duplikat dan kolom tambahan', function (string $case) {
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-header-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $headers = ProspectiveStudentBulkUpdateSpreadsheet::HEADERS;

    if ($case === 'duplicate') {
        $headers[1] = $headers[0];
    } else {
        $headers[] = 'Kolom Tambahan';
    }

    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value), $headers)));
    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($path);
    }
})->with(['duplicate', 'extra']);

it('menolak file yang memiliki lebih dari 500 baris data', function () {
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-large-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value), ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)));

    for ($index = 1; $index <= 501; $index++) {
        $writer->addRow(new Row(array_map(
            fn (mixed $value): StringCell => new StringCell((string) $value),
            [$index, 'REG-'.$index, '2027/2028', 'VII A', str_repeat('a', 64), 'Nama '.$index, '', 'L', '', '', '', ''],
        )));
    }

    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'batas maksimal 500 baris data');
    } finally {
        @unlink($path);
    }
});

it('menolak export yang memiliki lebih dari 500 calon siswa', function () {
    ProspectiveStudent::factory()->count(501)->create([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);

    expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($this->academicYear->id, null, null))
        ->toThrow(RuntimeException::class, 'Persempit filter');
});

it('menolak formula pada kolom readonly dan editable', function (string $formulaHeader) {
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-formula-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value), ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)));
    $values = array_fill(0, count(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS), '');
    array_splice($values, 0, 5, ['1', 'REG-1', '2027/2028', 'VII A', str_repeat('a', 64)]);
    $cells = array_map(fn (mixed $value): StringCell => new StringCell((string) $value), $values);
    $formulaIndex = array_search($formulaHeader, ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true);
    $cells[$formulaIndex] = new FormulaCell('=1+1');
    $writer->addRow(new Row($cells));
    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'Formula tidak diizinkan');
    } finally {
        @unlink($path);
    }
})->with([
    'readonly registration number' => 'No. Pendaftaran',
    'editable name' => 'Nama Lengkap',
    'parent field' => 'Ayah - Nama',
]);

it('menolak identifier numeric agar leading zero tidak hilang', function (string $header, int $numericValue) {
    $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-numeric-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value), ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)));
    $values = array_fill(0, count(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS), '');
    array_splice($values, 0, 5, ['1', '000123', '2027/2028', 'VII A', str_repeat('a', 64)]);
    $cells = array_map(fn (mixed $value): StringCell => new StringCell((string) $value), $values);
    $cells[array_search($header, ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true)] = new NumericCell($numericValue);
    $writer->addRow(new Row($cells));
    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'harus berformat teks');
    } finally {
        @unlink($path);
    }
})->with([
    'registration number' => ['No. Pendaftaran', 123],
    'student NIK' => ['NIK Siswa', 1234567890123456],
    'NISN' => ['NISN', 1234567890],
    'family card' => ['Nomor KK', 1234567890123456],
    'postal code' => ['Kode Pos', 1234],
]);

it('mengekspor formula-like text dan identifier bernol depan sebagai string aman', function () {
    $prospect = makeBulkProspect([
        'registration_number' => '0000123456',
        'nama_lengkap' => '=1+1',
        'no_telp_orang_tua' => '081234567890',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($this->academicYear->id, null, null);

    try {
        $reader = new Reader;
        $reader->open($path);
        $dataRow = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME) {
                continue;
            }
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 2) {
                    $dataRow = $row;
                    break 2;
                }
            }
        }
        $reader->close();
        $archive = new ZipArchive;
        $archive->open($path);
        $worksheetXml = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();

        expect($dataRow)->not->toBeNull()
            ->and($dataRow->cells[1])->toBeInstanceOf(StringCell::class)
            ->and($dataRow->cells[1]->getValue())->toBe('0000123456')
            ->and($dataRow->cells[array_search('Nama Lengkap', ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true)]->getValue())->toBe('=1+1')
            ->and($dataRow->cells[array_search('No Telp Orang Tua', ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true)])->toBeInstanceOf(StringCell::class)
            ->and($dataRow->cells[array_search('No Telp Orang Tua', ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true)]->getValue())->toBe('081234567890')
            ->and($worksheetXml)->not->toContain('<f>')
            ->and($worksheetXml)->toContain('=1+1')
            ->and($prospect->registration_number)->toBe('0000123456');
    } finally {
        @unlink($path);
    }
});

it('menolak marker clear untuk field wajib dan mengizinkannya pada field nullable', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $required = $service->preview([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nama_lengkap' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
    ])]);
    $nullable = $service->preview([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'alamat' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
    ])]);

    expect($required['rows'][0]['errors'][0])->toContain('tidak dapat digunakan')
        ->and($nullable['summary']['ready'])->toBe(1)
        ->and($nullable['rows'][0]['changes'][0]['explicit_clear'])->toBeTrue()
        ->and($service->apply([prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'alamat' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
        ])]))->toBe(1)
        ->and($prospect->fresh()->alamat)->toBeNull();
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

        expect($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
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

        expect($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN BARU')
            ->and($prospect->fresh()->nama_panggilan)->toBe('AHMAD');
    } finally {
        @unlink($path);
    }
});

it('snapshot tetap valid ketika data tidak berubah', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'nama_lengkap' => 'Nama Snapshot Baru']);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->preview([$row])['summary']['ready'])->toBe(1)
        ->and($service->apply([$row]))->toBe(1)
        ->and($prospect->fresh()->nama_lengkap)->toBe('NAMA SNAPSHOT BARU');
});

it('snapshot stale membatalkan seluruh update', function () {
    $first = makeBulkProspect([
        'registration_number' => 'REG-STALE-001',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $second = makeBulkProspect([
        'registration_number' => 'REG-STALE-002',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow(['row_number' => 2, 'id_sistem' => $first->id, 'nama_lengkap' => 'Perubahan Pertama']),
        prospectiveBulkRow(['row_number' => 3, 'id_sistem' => $second->id, 'nama_lengkap' => 'Perubahan Kedua']),
    ];
    $first->update(['alamat' => 'Diubah Admin Lain']);

    expect(fn () => app(ProspectiveStudentBulkUpdateService::class)->apply($rows))
        ->toThrow(ValidationException::class);

    expect($first->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN')
        ->and($first->fresh()->alamat)->toBe('Diubah Admin Lain')
        ->and($second->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
});

it('payload preview palsu ditolak dan tidak menulis data', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $path = prospectiveBulkWorkbook([[
        $prospect->id, $prospect->registration_number, '2027/2028', 'VII A',
        'Nama Aman', '', 'L', '', '', '', '',
    ]]);
    $upload = UploadedFile::fake()->createWithContent('update-calon.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(ProspectiveStudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate');
        $token = $component->get('confirmationToken');
        $key = 'ppdb-bulk-update-preview:'.auth()->id().':'.hash('sha256', $token);
        Cache::store('array')->put($key, 'payload-palsu', now()->addMinute());

        $component->call('confirmUpdate')->assertHasErrors('update');

        expect($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
    } finally {
        @unlink($path);
    }
});

it('token konfirmasi hanya dapat digunakan sekali', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $path = prospectiveBulkWorkbook([[
        $prospect->id, $prospect->registration_number, '2027/2028', 'VII A',
        'Nama Sekali', '', 'L', '', '', '', '',
    ]]);
    $upload = UploadedFile::fake()->createWithContent('update-calon.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(ProspectiveStudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->call('confirmUpdate')
            ->assertHasNoErrors();

        $component->call('confirmUpdate')->assertHasErrors('update');

        expect($prospect->fresh()->nama_lengkap)->toBe('NAMA SEKALI');
    } finally {
        @unlink($path);
    }
});

it('reset menghapus token dan confirm setelah reset ditolak', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $path = prospectiveBulkWorkbook([[
        $prospect->id, $prospect->registration_number, '2027/2028', 'VII A',
        'Nama Tidak Jadi', '', 'L', '', '', '', '',
    ]]);
    $upload = UploadedFile::fake()->createWithContent('update-calon.xlsx', file_get_contents($path));

    try {
        Livewire::test(ProspectiveStudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->call('backToSetup')
            ->assertSet('confirmationToken', '')
            ->call('confirmUpdate')
            ->assertHasErrors('update');

        expect($prospect->fresh()->nama_lengkap)->toBe('AHMAD FAUZAN');
    } finally {
        @unlink($path);
    }
});

it('baris tanpa perubahan tidak menghasilkan statement update', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_contains(strtolower($query->sql), 'update `prospective_students`')) {
            $updates[] = $query->sql;
        }
    });

    $count = app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'nama_lengkap' => $prospect->nama_lengkap,
            'nama_panggilan' => $prospect->nama_panggilan,
            'jenis_kelamin' => $prospect->jenis_kelamin,
            'nama_orang_tua' => $prospect->nama_orang_tua,
            'no_telp_orang_tua' => $prospect->no_telp_orang_tua,
            'alamat' => $prospect->alamat,
        ]),
    ]);

    expect($count)->toBe(0)->and($updates)->toBe([]);
});

it('memperbarui kategori menggunakan nilai enum aktual dan menolak nilai invalid', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    $validRow = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
    ]);

    expect($service->apply([$validRow]))->toBe(1)
        ->and($prospect->fresh()->applicant_category)->toBe(PpdbApplicantCategory::NON_ANNUR_FAMILY);

    $invalid = $service->preview([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'applicant_category' => 'kategori_buatan',
    ])]);

    expect($invalid['summary']['blocked'])->toBe(1)
        ->and($invalid['rows'][0]['errors'])->toContain('Nilai Kategori Pendaftar tidak valid.');
});

it('memperbarui seluruh field identitas alamat dan kesehatan pada satu profile save', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $profileValues = [
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2014-05-10',
        'asal_sekolah' => 'SD Annur',
        'nik_siswa' => '0012345678901234',
        'nisn' => '0012345678',
        'nomor_kk' => '0098765432101234',
        'nomor_akta_kelahiran' => 'AKTA/001/2014',
        'anak_ke' => '2',
        'jumlah_saudara' => '3',
        'hobi' => 'Membaca',
        'cita_cita' => 'Dokter',
        'status_anak' => 'Kandung',
        'agama' => 'Islam',
        'bahasa_sehari_hari' => 'Indonesia',
        'rt' => '007',
        'rw' => '003',
        'kelurahan' => 'Cibiru',
        'kecamatan' => 'Cileunyi',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40625',
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => '2,5',
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => 'Perahu',
        'tinggi_badan' => '140.5',
        'berat_badan' => '35.25',
        'lingkar_kepala' => '52',
        'golongan_darah' => 'ab',
        'riwayat_penyakit' => 'Tidak ada',
        'berkebutuhan_khusus' => '1',
        'keterangan_kebutuhan_khusus' => 'Pendampingan belajar',
    ];
    $profileWrites = 0;
    DB::listen(function ($query) use (&$profileWrites): void {
        if (str_contains(strtolower($query->sql), 'ppdb_profiles')) {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') || str_starts_with(strtolower(trim($query->sql)), 'update')) {
                $profileWrites++;
            }
        }
    });
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, ...$profileValues]);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([$row]);
    $profileChangeFields = collect($preview['rows'][0]['changes'])
        ->pluck('field')
        ->intersect(ProspectiveStudentBulkUpdateService::PROFILE_FIELDS)
        ->values()
        ->all();

    expect($profileChangeFields)->toBe(array_keys($profileValues))
        ->and(app(ProspectiveStudentBulkUpdateService::class)->apply([$row]))->toBe(1);

    $profile = $prospect->fresh()->ppdbProfile;
    expect($profile)->toBeInstanceOf(PpdbProfile::class)
        ->and($profile->tempat_lahir)->toBe('Bandung')
        ->and($profile->tanggal_lahir->format('Y-m-d'))->toBe('2014-05-10')
        ->and($profile->nik_siswa)->toBe('0012345678901234')
        ->and($profile->nisn)->toBe('0012345678')
        ->and($profile->nomor_kk)->toBe('0098765432101234')
        ->and($profile->anak_ke)->toBe(2)
        ->and($profile->jumlah_saudara)->toBe(3)
        ->and($profile->jarak_tempuh_detail)->toBe('2.50')
        ->and($profile->moda_transportasi_lainnya)->toBe('Perahu')
        ->and($profile->rt)->toBe('007')
        ->and($profile->kode_pos)->toBe('40625')
        ->and($profile->tinggi_badan)->toBe('140.50')
        ->and($profile->golongan_darah)->toBe('AB')
        ->and($profile->berkebutuhan_khusus)->toBeTrue()
        ->and($profile->keterangan_kebutuhan_khusus)->toBe('Pendampingan belajar')
        ->and($profileWrites)->toBe(1);
});

it('membuat profile hanya ketika ada perubahan profile yang berlaku', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($prospect->ppdbProfile)->toBeNull()
        ->and($service->apply([prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'nama_lengkap' => 'Nama Core Saja',
        ])]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile)->toBeNull();

    $profileRow = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'asal_sekolah' => 'Sekolah Lama',
    ]);

    expect($service->apply([$profileRow]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile?->asal_sekolah)->toBe('Sekolah Lama');
});

it('tidak memaksa field registrasi publik pada record historis nullable', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => null,
        'berkebutuhan_khusus' => true,
        'keterangan_kebutuhan_khusus' => null,
    ]);

    $row = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nama_panggilan' => 'Nama Historis',
    ]);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([$row]);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['summary']['blocked'])->toBe(0)
        ->and(app(ProspectiveStudentBulkUpdateService::class)->apply([$row]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile->moda_transportasi_lainnya)->toBeNull();
});

it('blank mempertahankan profile dan clear mengosongkan field nullable', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'asal_sekolah' => 'Sekolah Lama',
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'asal_sekolah' => '',
    ])]))->toBe(0)
        ->and($prospect->fresh()->ppdbProfile->asal_sekolah)->toBe('Sekolah Lama');

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'asal_sekolah' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
    ])]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile->asal_sekolah)->toBeNull();
});

it('mempertahankan nol depan NISN NIK dan KK serta menolak NISN TK', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nisn' => '0012345678',
        'nik_siswa' => '0012345678901234',
        'nomor_kk' => '0098765432101234',
    ])]))->toBe(1);

    $profile = $prospect->fresh()->ppdbProfile;
    expect($profile->nisn)->toBe('0012345678')
        ->and($profile->nik_siswa)->toBe('0012345678901234')
        ->and($profile->nomor_kk)->toBe('0098765432101234');

    $tkClass = SchoolClass::factory()->create(['level' => -1, 'name' => 'TK B']);
    $tkProspect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $tkClass->id,
    ]);
    $blocked = $service->preview([prospectiveBulkRow([
        'id_sistem' => $tkProspect->id,
        'nisn' => '0012345678',
    ])]);

    expect($blocked['summary']['blocked'])->toBe(1)
        ->and($blocked['rows'][0]['errors'])->toContain('NISN harus tetap kosong untuk calon siswa jenjang TK.');
});

it('membersihkan NISN lama pada TK secara terlihat ketika baris diperbarui', function () {
    $tkClass = SchoolClass::factory()->create(['level' => -1, 'name' => 'TK A']);
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $tkClass->id,
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'nisn' => '0012345678',
    ]);
    $row = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nama_panggilan' => 'Nama TK',
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $preview = $service->preview([$row]);
    $nisnChange = collect($preview['rows'][0]['changes'])->firstWhere('field', 'nisn');

    expect($nisnChange['automatic'])->toBeTrue()
        ->and($nisnChange['new'])->toBeNull()
        ->and($service->apply([$row]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile->nisn)->toBeNull();
});

it('menormalisasi jarak koma dan menerapkan conditional clearing secara terlihat', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => '3.50',
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => 'Perahu',
        'berkebutuhan_khusus' => true,
        'keterangan_kebutuhan_khusus' => 'Pendampingan',
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $distanceRow = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'jarak_tempuh_detail' => '2,75',
    ]);

    expect($service->apply([$distanceRow]))->toBe(1)
        ->and($prospect->fresh()->ppdbProfile->jarak_tempuh_detail)->toBe('2.75');

    $clearRow = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'jarak_tempuh_kategori' => 'less_than_1_km',
        'moda_transportasi' => 'motor',
        'berkebutuhan_khusus' => '0',
    ]);
    $preview = $service->preview([$clearRow]);
    $automaticFields = collect($preview['rows'][0]['changes'])
        ->where('automatic', true)
        ->pluck('field')
        ->all();

    expect($automaticFields)->toContain(
        'jarak_tempuh_detail',
        'moda_transportasi_lainnya',
        'keterangan_kebutuhan_khusus',
    )
        ->and($service->apply([$clearRow]))->toBe(1);

    $profile = $prospect->fresh()->ppdbProfile;
    expect($profile->jarak_tempuh_detail)->toBeNull()
        ->and($profile->moda_transportasi_lainnya)->toBeNull()
        ->and($profile->keterangan_kebutuhan_khusus)->toBeNull()
        ->and($profile->berkebutuhan_khusus)->toBeFalse();
});

it('memvalidasi detail kondisional pada keadaan efektif', function (array $values, string $message) {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([
        prospectiveBulkRow(['id_sistem' => $prospect->id, ...$values]),
    ]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and(implode(' ', $preview['rows'][0]['errors']))->toContain($message);
})->with([
    'distance detail required' => [['jarak_tempuh_kategori' => 'more_than_1_km'], 'Detail Jarak Tempuh wajib'],
    'transport detail required' => [['moda_transportasi' => 'lainnya'], 'Moda Transportasi Lainnya wajib'],
    'special needs detail required' => [['berkebutuhan_khusus' => '1'], 'Keterangan Kebutuhan Khusus wajib'],
]);

it('menolak konflik snapshot profile dan menggulung seluruh file', function () {
    $first = makeBulkProspect([
        'registration_number' => 'REG-PROFILE-001',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $second = makeBulkProspect([
        'registration_number' => 'REG-PROFILE-002',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $firstProfile = PpdbProfile::query()->create([
        'prospective_student_id' => $first->id,
        'asal_sekolah' => 'Sekolah Awal',
    ]);
    $rows = [
        prospectiveBulkRow(['row_number' => 2, 'id_sistem' => $first->id, 'asal_sekolah' => 'Sekolah Excel']),
        prospectiveBulkRow(['row_number' => 3, 'id_sistem' => $second->id, 'asal_sekolah' => 'Sekolah Kedua']),
    ];
    $firstProfile->update(['asal_sekolah' => 'Diubah Admin Lain']);

    expect(fn () => app(ProspectiveStudentBulkUpdateService::class)->apply($rows))
        ->toThrow(ValidationException::class);

    expect($first->fresh()->ppdbProfile->asal_sekolah)->toBe('Diubah Admin Lain')
        ->and($second->fresh()->ppdbProfile)->toBeNull();
});

it('tidak mengubah parent atau relasi lain saat profile diperbarui', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    PpdbParent::query()->create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'nama' => 'AYAH TETAP',
        'nik' => '0011223344556677',
    ]);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow(['id_sistem' => $prospect->id, 'tempat_lahir' => 'Bandung']),
    ]))->toBe(1)
        ->and($prospect->ppdbParents()->count())->toBe(1)
        ->and($prospect->ppdbParents()->value('nama'))->toBe('AYAH TETAP')
        ->and($prospect->ppdbParents()->value('nik'))->toBe('0011223344556677');
});

it('mengekspor identifier profile sebagai teks eksplisit', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospect->id,
        'nik_siswa' => '0012345678901234',
        'nisn' => '0012345678',
        'nomor_kk' => '0098765432101234',
        'rt' => '007',
        'kode_pos' => '40625',
    ]);
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($this->academicYear->id, null, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows[0]['nik_siswa'])->toBe('0012345678901234')
        ->and($rows[0]['nisn'])->toBe('0012345678')
        ->and($rows[0]['nomor_kk'])->toBe('0098765432101234')
        ->and($rows[0]['rt'])->toBe('007')
        ->and($rows[0]['kode_pos'])->toBe('40625');
});

it('mengekspor seluruh role parent berdasarkan role bukan urutan koleksi', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    makeBulkParent($prospect, 'guardian', ['nama' => 'Wali Export', 'nik' => '0000000000000003']);
    makeBulkParent($prospect, 'mother', ['nama' => 'Ibu Export', 'nik' => '0000000000000002', 'status_hidup' => 'wafat']);
    makeBulkParent($prospect, 'father', ['nama' => 'Ayah Export', 'nik' => '0000000000000001', 'status_hidup' => 'hidup']);
    $path = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->create($this->academicYear->id, null, null);

    try {
        $rows = app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['father_nama'])->toBe('AYAH EXPORT')
        ->and($rows[0]['father_nik'])->toBe('0000000000000001')
        ->and($rows[0]['father_status_hidup'])->toBe('hidup')
        ->and($rows[0]['father_tanggal_lahir'])->toBe('1980-01-02')
        ->and($rows[0]['father_email'])->toBe('father@example.test')
        ->and($rows[0]['father_income_range'])->toBe(ParentIncomeRange::ThreeToFourMillion->value)
        ->and($rows[0]['mother_nama'])->toBe('IBU EXPORT')
        ->and($rows[0]['mother_nik'])->toBe('0000000000000002')
        ->and($rows[0]['mother_status_hidup'])->toBe('wafat')
        ->and($rows[0]['mother_pendidikan_terakhir'])->toBe('SMA')
        ->and($rows[0]['mother_no_telp'])->toBe('081234567890')
        ->and($rows[0]['guardian_nama'])->toBe('WALI EXPORT')
        ->and($rows[0]['guardian_nik'])->toBe('0000000000000003')
        ->and($rows[0]['guardian_occupation'])->toBe('Wiraswasta')
        ->and($rows[0]['guardian_income_range'])->toBe(ParentIncomeRange::ThreeToFourMillion->value)
        ->and(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)->not->toContain('Wali - Status Hidup');
});

it('mengimpor setiap role parent secara independen dan hanya satu save per role', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'nama_orang_tua' => null,
        'no_telp_orang_tua' => null,
    ]);
    $parentWrites = 0;
    DB::listen(function ($query) use (&$parentWrites): void {
        $sql = strtolower(trim($query->sql));
        if (str_contains($sql, 'ppdb_parents') && (str_starts_with($sql, 'insert') || str_starts_with($sql, 'update'))) {
            $parentWrites++;
        }
    });
    $row = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'father_status_hidup' => 'hidup',
        'father_nama' => 'ayah baru',
        'father_nik' => '0000000000000001',
        'father_tempat_lahir' => 'Bandung',
        'father_tanggal_lahir' => '1980-01-02',
        'father_pendidikan_terakhir' => 'SMA',
        'father_email' => 'ayah@example.test',
        'father_no_telp' => '081111111111',
        'father_occupation' => 'Guru',
        'father_income_range' => ParentIncomeRange::FiveToSevenMillion->value,
        'mother_status_hidup' => 'wafat',
        'mother_nama' => 'ibu baru',
        'mother_nik' => '0000000000000002',
        'mother_tempat_lahir' => 'Garut',
        'mother_tanggal_lahir' => '1982-03-04',
        'mother_pendidikan_terakhir' => 'S1',
        'mother_email' => 'ibu@example.test',
        'mother_no_telp' => '082222222222',
        'mother_occupation' => 'Dokter',
        'mother_income_range' => ParentIncomeRange::NoIncome->value,
        'guardian_nama' => 'wali baru',
        'guardian_nik' => '0000000000000003',
        'guardian_tempat_lahir' => 'Sumedang',
        'guardian_tanggal_lahir' => '1978-05-06',
        'guardian_pendidikan_terakhir' => 'D3',
        'guardian_email' => 'wali@example.test',
        'guardian_no_telp' => '083333333333',
        'guardian_occupation' => 'Pedagang',
        'guardian_income_range' => ParentIncomeRange::BelowOneMillion->value,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $previewSections = collect($service->preview([$row])['rows'][0]['changes'])
        ->pluck('section_label')
        ->unique()
        ->values()
        ->all();

    expect($previewSections)->toContain('Ayah', 'Ibu', 'Wali')
        ->and($service->apply([$row]))->toBe(1)
        ->and($parentWrites)->toBe(3)
        ->and($prospect->ppdbParents()->count())->toBe(3);

    $parents = $prospect->fresh()->ppdbParents->keyBy('role');
    expect($parents['father']->nama)->toBe('AYAH BARU')
        ->and($parents['father']->status_hidup)->toBe('hidup')
        ->and($parents['father']->income_range)->toBe(ParentIncomeRange::FiveToSevenMillion)
        ->and($parents['mother']->nama)->toBe('IBU BARU')
        ->and($parents['mother']->status_hidup)->toBe('wafat')
        ->and($parents['mother']->tanggal_lahir->format('Y-m-d'))->toBe('1982-03-04')
        ->and($parents['mother']->occupation)->toBe('Dokter')
        ->and($parents['guardian']->nama)->toBe('WALI BARU')
        ->and($parents['guardian']->status_hidup)->toBeNull()
        ->and($parents['guardian']->pendidikan_terakhir)->toBe('D3')
        ->and($parents['guardian']->occupation)->toBe('Pedagang')
        ->and($prospect->fresh()->nama_orang_tua)->toBe('AYAH BARU')
        ->and($prospect->fresh()->no_telp_orang_tua)->toBe('081111111111');
});

it('membuat hanya role parent yang diminta', function (string $role, string $field) {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'nama_orang_tua' => null,
        'no_telp_orang_tua' => null,
    ]);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow(['id_sistem' => $prospect->id, $field => 'Nama Role']),
    ]))->toBe(1)
        ->and($prospect->ppdbParents()->count())->toBe(1)
        ->and($prospect->ppdbParents()->value('role'))->toBe($role)
        ->and($prospect->ppdbParents()->value('nama'))->toBe('NAMA ROLE');
})->with([
    'father only' => ['father', 'father_nama'],
    'mother only' => ['mother', 'mother_nama'],
    'guardian only' => ['guardian', 'guardian_nama'],
]);

it('menjaga status hidup ayah dan ibu tetap independen', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    makeBulkParent($prospect, 'father', ['status_hidup' => 'hidup']);
    makeBulkParent($prospect, 'mother', ['status_hidup' => 'wafat']);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'father_status_hidup' => 'wafat',
        ]),
    ]))->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'father')->value('status_hidup'))->toBe('wafat')
        ->and($prospect->ppdbParents()->where('role', 'mother')->value('status_hidup'))->toBe('wafat');
});

it('blank mempertahankan parent dan clear hanya mengosongkan scalar tanpa menghapus role', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    makeBulkParent($prospect, 'mother', ['email' => 'ibu.lama@example.test', 'occupation' => 'Guru']);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'mother_email' => '',
    ])]))->toBe(0)
        ->and($prospect->ppdbParents()->where('role', 'mother')->value('email'))->toBe('ibu.lama@example.test');

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'mother_email' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
        'mother_occupation' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
    ])]))->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'mother')->value('email'))->toBeNull()
        ->and($prospect->ppdbParents()->where('role', 'mother')->value('occupation'))->toBeNull()
        ->and($prospect->ppdbParents()->where('role', 'mother')->count())->toBe(1);
});

it('tidak membuat parent kosong dari clear pada role yang belum ada', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'guardian_email' => ProspectiveStudentBulkUpdateService::CLEAR_MARKER,
        ]),
    ]))->toBe(0)
        ->and($prospect->ppdbParents()->where('role', 'guardian')->count())->toBe(0);
});

it('memvalidasi format parent hanya ketika nilainya diubah', function (string $field, string $value, string $message) {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([
        prospectiveBulkRow(['id_sistem' => $prospect->id, $field => $value]),
    ]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and(implode(' ', $preview['rows'][0]['errors']))->toContain($message);
})->with([
    'invalid living status' => ['father_status_hidup', 'unknown', 'Status Hidup tidak valid'],
    'invalid NIK' => ['father_nik', '123', 'harus terdiri dari 16 digit'],
    'invalid email' => ['mother_email', 'bukan-email', 'alamat email yang valid'],
    'invalid phone' => ['guardian_no_telp', '123', 'nomor telepon valid'],
    'invalid date' => ['mother_tanggal_lahir', '31/12/1980', 'format YYYY-MM-DD'],
    'invalid income' => ['guardian_income_range', 'rentang_buatan', 'Penghasilan tidak valid'],
]);

it('tidak memaksa kelengkapan parent historis ketika field lain diperbarui', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    makeBulkParent($prospect, 'father', [
        'nik' => null,
        'email' => null,
        'no_telp' => null,
        'income_range' => null,
    ]);
    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'father_occupation' => 'Petani']);

    expect(app(ProspectiveStudentBulkUpdateService::class)->preview([$row])['summary']['ready'])->toBe(1)
        ->and(app(ProspectiveStudentBulkUpdateService::class)->apply([$row]))->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'father')->value('occupation'))->toBe('Petani');
});

it('menyinkronkan alias legacy ayah dari kedua arah dan menormalisasi nama', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'nama_orang_tua' => 'AYAH LAMA',
        'no_telp_orang_tua' => '081000000000',
    ]);
    makeBulkParent($prospect, 'father', ['nama' => 'AYAH LAMA', 'no_telp' => '081000000000']);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'father_nama' => 'ayah dari kolom role',
        'father_no_telp' => '081111111111',
    ])]))->toBe(1)
        ->and($prospect->fresh()->nama_orang_tua)->toBe('AYAH DARI KOLOM ROLE')
        ->and($prospect->fresh()->no_telp_orang_tua)->toBe('081111111111');

    $aliasRow = prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'nama_orang_tua' => 'ayah dari alias',
        'no_telp_orang_tua' => '082222222222',
    ]);

    expect($service->apply([$aliasRow]))->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'father')->value('nama'))->toBe('AYAH DARI ALIAS')
        ->and($prospect->ppdbParents()->where('role', 'father')->value('no_telp'))->toBe('082222222222');
});

it('memblokir input alias legacy dan ayah yang sama-sama diubah tetapi bertentangan', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'nama_orang_tua' => 'AYAH LAMA',
    ]);
    makeBulkParent($prospect, 'father', ['nama' => 'AYAH LAMA']);
    $preview = app(ProspectiveStudentBulkUpdateService::class)->preview([
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'nama_orang_tua' => 'Alias Berbeda',
            'father_nama' => 'Ayah Berbeda',
        ]),
    ]);

    expect($preview['summary']['blocked'])->toBe(1)
        ->and(implode(' ', $preview['rows'][0]['errors']))->toContain('bertentangan');
});

it('tidak menyinkronkan mismatch legacy lama ketika field ayah tidak disentuh', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
        'nama_orang_tua' => 'ALIAS LAMA',
    ]);
    makeBulkParent($prospect, 'father', ['nama' => 'AYAH BERBEDA']);

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow(['id_sistem' => $prospect->id, 'asal_sekolah' => 'Sekolah Baru']),
    ]))->toBe(1)
        ->and($prospect->fresh()->nama_orang_tua)->toBe('ALIAS LAMA')
        ->and($prospect->ppdbParents()->where('role', 'father')->value('nama'))->toBe('AYAH BERBEDA');
});

it('tidak membuat duplicate parent role pada update berulang', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $service = app(ProspectiveStudentBulkUpdateService::class);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'mother_nama' => 'Ibu Pertama',
    ])]))->toBe(1);

    expect($service->apply([prospectiveBulkRow([
        'id_sistem' => $prospect->id,
        'mother_email' => 'ibu@example.test',
    ])]))->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'mother')->count())->toBe(1);
});

it('menolak snapshot parent stale dan kemunculan role setelah download', function (bool $roleExistsInitially) {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);

    if ($roleExistsInitially) {
        $parent = makeBulkParent($prospect, 'mother', ['nama' => 'IBU AWAL']);
    }

    $row = prospectiveBulkRow(['id_sistem' => $prospect->id, 'mother_email' => 'excel@example.test']);

    if ($roleExistsInitially) {
        $parent->update(['nama' => 'IBU ADMIN LAIN']);
    } else {
        makeBulkParent($prospect, 'mother', ['nama' => 'IBU DIBUAT ADMIN LAIN']);
    }

    expect(fn () => app(ProspectiveStudentBulkUpdateService::class)->apply([$row]))
        ->toThrow(ValidationException::class)
        ->and($prospect->ppdbParents()->where('role', 'mother')->count())->toBe(1)
        ->and($prospect->ppdbParents()->where('role', 'mother')->value('email'))->not->toBe('excel@example.test');
})->with([
    'existing role changed' => true,
    'missing role created concurrently' => false,
]);

it('parent invalid pada satu baris menggulung perubahan parent seluruh file', function () {
    $first = makeBulkProspect([
        'registration_number' => 'REG-PARENT-001',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $second = makeBulkProspect([
        'registration_number' => 'REG-PARENT-002',
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $rows = [
        prospectiveBulkRow(['row_number' => 2, 'id_sistem' => $first->id, 'mother_nama' => 'Ibu Valid']),
        prospectiveBulkRow(['row_number' => 3, 'id_sistem' => $second->id, 'father_nik' => '123']),
    ];

    expect(fn () => app(ProspectiveStudentBulkUpdateService::class)->apply($rows))
        ->toThrow(ValidationException::class)
        ->and($first->ppdbParents()->count())->toBe(0)
        ->and($second->ppdbParents()->count())->toBe(0);
});

it('parent unchanged tidak menghasilkan insert atau update', function () {
    $prospect = makeBulkProspect([
        'academic_year_id' => $this->academicYear->id,
        'school_class_id' => $this->class7->id,
    ]);
    $father = makeBulkParent($prospect, 'father');
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        $sql = strtolower(trim($query->sql));
        if (str_contains($sql, 'ppdb_parents') && (str_starts_with($sql, 'insert') || str_starts_with($sql, 'update'))) {
            $writes[] = $sql;
        }
    });

    expect(app(ProspectiveStudentBulkUpdateService::class)->apply([
        prospectiveBulkRow([
            'id_sistem' => $prospect->id,
            'father_status_hidup' => $father->status_hidup,
            'father_nama' => $father->nama,
            'father_nik' => $father->nik,
            'father_email' => $father->email,
            'father_no_telp' => $father->no_telp,
            'father_income_range' => $father->income_range->value,
        ]),
    ]))->toBe(0)
        ->and($writes)->toBe([]);
});

it('menolak NIK dan telepon parent numeric dari Excel', function (string $header, int $value) {
    $path = tempnam(sys_get_temp_dir(), 'annur-parent-numeric-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName(ProspectiveStudentBulkUpdateSpreadsheet::DATA_SHEET_NAME);
    $writer->addRow(new Row(array_map(fn (string $item): StringCell => new StringCell($item), ProspectiveStudentBulkUpdateSpreadsheet::HEADERS)));
    $values = array_fill(0, count(ProspectiveStudentBulkUpdateSpreadsheet::HEADERS), '');
    array_splice($values, 0, 5, ['1', 'REG-1', '2027/2028', 'VII A', str_repeat('a', 64)]);
    $cells = array_map(fn (mixed $item): StringCell => new StringCell((string) $item), $values);
    $cells[array_search($header, ProspectiveStudentBulkUpdateSpreadsheet::HEADERS, true)] = new NumericCell($value);
    $writer->addRow(new Row($cells));
    $writer->close();

    try {
        expect(fn () => app(ProspectiveStudentBulkUpdateSpreadsheet::class)->read($path))
            ->toThrow(RuntimeException::class, 'harus berformat teks');
    } finally {
        @unlink($path);
    }
})->with([
    'father NIK' => ['Ayah - NIK', 1234567890123456],
    'mother phone' => ['Ibu - No. Telepon', 81234567890],
    'guardian NIK' => ['Wali - NIK', 1234567890123456],
]);
