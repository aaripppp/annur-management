<?php

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Services\PpdbBiodataRecapQuery;
use App\Services\PpdbBiodataRecapSpreadsheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;

function recapWriterYear(string $year = '2032/2033'): AcademicYear
{
    [$start, $end] = explode('/', $year);

    return AcademicYear::create([
        'year' => $year,
        'is_active' => false,
        'start_date' => $start.'-07-01',
        'end_date' => $end.'-06-30',
    ]);
}

function recapWriterApplicant(AcademicYear $year, SchoolClass $class, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ], $overrides));
}

/** @return array<string, Collection<int, Row>> */
function recapWriterRows(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))->values();
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

/** @return array<string, mixed> */
function recapWriterFirstDetailRow(Collection $rows): array
{
    $headerIndex = $rows->search(fn ($row): bool => ($row->toArray()[0] ?? null) === 'No.');
    $headers = $rows[$headerIndex]->toArray();
    $values = $rows[$headerIndex + 1]->toArray();

    return array_combine($headers, array_pad($values, count($headers), ''));
}

beforeEach(function () {
    $this->year = recapWriterYear();
    $this->smpClass = SchoolClass::factory()->create(['level' => 8]);
    $this->spreadsheet = app(PpdbBiodataRecapSpreadsheet::class);
});

it('creates exactly six ordered sheets identical headers recap totals and empty groups', function () {
    recapWriterApplicant($this->year, $this->smpClass);
    recapWriterApplicant($this->year, $this->smpClass, [
        'status' => ProspectiveStudentStatus::Converted,
        'jenis_kelamin' => 'P',
    ]);
    recapWriterApplicant($this->year, $this->smpClass, [
        'status' => ProspectiveStudentStatus::Cancelled,
        'applicant_category' => null,
    ]);
    $unclassified = recapWriterApplicant($this->year, $this->smpClass);
    DB::table('prospective_students')->where('id', $unclassified->id)->update(['applicant_category' => 'legacy_category']);
    recapWriterApplicant($this->year, $this->smpClass, ['jenis_kelamin' => null]);
    $invalidGender = recapWriterApplicant($this->year, $this->smpClass);
    DB::statement('PRAGMA ignore_check_constraints = ON');
    DB::table('prospective_students')->where('id', $invalidGender->id)->update(['jenis_kelamin' => 'X']);
    DB::statement('PRAGMA ignore_check_constraints = OFF');

    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::SMP);

    try {
        $sheets = recapWriterRows($export['path']);
        $detailHeaders = [];
        foreach (array_slice(PpdbBiodataRecapQuery::SHEET_NAMES, 1) as $sheetName) {
            $headerRow = $sheets[$sheetName]->first(fn ($row): bool => ($row->toArray()[0] ?? null) === 'No.');
            $detailHeaders[] = $headerRow->toArray();
        }
        $recapRows = $sheets['REKAP']->map(fn ($row): array => $row->toArray())->values();
        $recapHeaderIndex = $recapRows->search(fn (array $row): bool => ($row[0] ?? null) === 'Kelompok');
        $recap = $recapRows->slice($recapHeaderIndex + 1, 6)->pluck(1, 0)->all();
        $unclassifiedRows = $sheets['BELUM DIKATEGORIKAN'];
        $unclassifiedHeaderIndex = $unclassifiedRows->search(fn ($row): bool => ($row->toArray()[0] ?? null) === 'No.');
        $unclassifiedHeaders = $unclassifiedRows[$unclassifiedHeaderIndex]->toArray();
        $categoryIndex = array_search('Kategori Pendaftar', $unclassifiedHeaders, true);
        $genderIndex = array_search('Jenis Kelamin', $unclassifiedHeaders, true);
        $unclassifiedValues = $unclassifiedRows->slice($unclassifiedHeaderIndex + 1)->map(fn ($row): array => $row->toArray());

        expect(array_keys($sheets))->toBe(PpdbBiodataRecapQuery::SHEET_NAMES)
            ->and($detailHeaders)->each->toBe(PpdbBiodataRecapQuery::detailHeaders())
            ->and($recap)->toMatchArray([
                'Ikhwan Keluarga Annur' => 1,
                'Akhwat Keluarga Annur' => 1,
                'Ikhwan Non-Keluarga' => 0,
                'Akhwat Non-Keluarga' => 0,
                'Belum Dikategorikan' => 3,
                'GRAND TOTAL' => 5,
            ])
            ->and($sheets['IKHWAN NON-KELUARGA']->filter(fn ($row): bool => is_numeric($row->toArray()[0] ?? null)))->toBeEmpty()
            ->and($sheets['AKHWAT NON-KELUARGA']->filter(fn ($row): bool => is_numeric($row->toArray()[0] ?? null)))->toBeEmpty()
            ->and($unclassifiedValues->pluck($categoryIndex))->toContain('legacy_category')
            ->and($unclassifiedValues->pluck($genderIndex))->toContain('X')
            ->and($export['filename'])->toBe('Rekap_Biodata_PPDB_2032-2033_SMP.xlsx');
    } finally {
        @unlink($export['path']);
    }
});

it('maps complete biodata parents legacy fallback siblings and safe text cells', function () {
    $applicant = recapWriterApplicant($this->year, $this->smpClass, [
        'registration_number' => 'REG-00123',
        'nama_lengkap' => 'Ahmad Lengkap',
        'nama_panggilan' => 'Ahmad',
        'nama_orang_tua' => 'Ayah Legacy',
        'no_telp_orang_tua' => '081000000001',
        'alamat' => '=HYPERLINK("https://example.test")',
    ]);
    PpdbProfile::create([
        'prospective_student_id' => $applicant->id,
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2018-01-02',
        'asal_sekolah' => 'TK Annur',
        'nik_siswa' => '0012345678901234',
        'nisn' => '0012345678',
        'nomor_kk' => '0000000000000123',
        'nomor_akta_kelahiran' => '0001/AKTA',
        'anak_ke' => 2,
        'jumlah_saudara' => 4,
        'hobi' => '+Menggambar',
        'cita_cita' => '@Dokter',
        'status_anak' => 'Kandung',
        'agama' => 'Islam',
        'bahasa_sehari_hari' => 'Indonesia',
        'rt' => '001',
        'rw' => '002',
        'kelurahan' => 'Mekar',
        'kecamatan' => 'Tengah',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '00123',
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => '2.50',
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => 'Perahu',
        'tinggi_badan' => '120.50',
        'berat_badan' => '25.25',
        'lingkar_kepala' => '50.00',
        'golongan_darah' => 'O',
        'riwayat_penyakit' => '-Tidak ada',
        'berkebutuhan_khusus' => true,
        'keterangan_kebutuhan_khusus' => 'Pendampingan',
    ]);
    PpdbParent::create([
        'prospective_student_id' => $applicant->id,
        'role' => 'father',
        'status_hidup' => 'hidup',
        'nik' => '0000000000000011',
        'tempat_lahir' => 'Garut',
        'tanggal_lahir' => '1980-01-01',
        'pendidikan_terakhir' => 'SMA',
        'email' => 'ayah@example.test',
        'nama' => null,
        'no_telp' => null,
        'occupation' => 'Guru',
        'income_range' => ParentIncomeRange::OneToTwoMillion,
    ]);
    PpdbParent::create([
        'prospective_student_id' => $applicant->id,
        'role' => 'mother',
        'status_hidup' => 'wafat',
        'nik' => '0000000000000022',
        'nama' => 'Ibu Mandiri',
        'no_telp' => '081000000002',
        'occupation' => 'Dokter',
        'income_range' => ParentIncomeRange::FiveToSevenMillion,
    ]);
    PpdbParent::create([
        'prospective_student_id' => $applicant->id,
        'role' => 'guardian',
        'nik' => '0000000000000033',
        'nama' => 'Wali Opsional',
        'no_telp' => '081000000003',
        'occupation' => 'Wiraswasta',
    ]);
    PpdbSibling::create([
        'prospective_student_id' => $applicant->id,
        'sort_order' => 2,
        'nama_lengkap' => 'Saudara Kedua',
        'tanggal_lahir' => '2020-02-02',
        'jenis_kelamin' => 'P',
        'pendidikan_pekerjaan' => 'TK',
    ]);
    PpdbSibling::create([
        'prospective_student_id' => $applicant->id,
        'sort_order' => 1,
        'nama_lengkap' => 'Saudara Pertama',
        'tanggal_lahir' => '2016-01-01',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'SD',
    ]);

    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::SMP);

    try {
        $sheets = recapWriterRows($export['path']);
        $rows = $sheets['IKHWAN KELUARGA ANNUR'];
        $detail = recapWriterFirstDetailRow($rows);
        $headerIndex = $rows->search(fn ($row): bool => ($row->toArray()[0] ?? null) === 'No.');
        $dataRow = $rows[$headerIndex + 1];

        expect($detail)->toMatchArray([
            'No. Pendaftaran' => 'REG-00123',
            'Tahun Ajaran' => '2032/2033',
            'Jenjang' => 'SMP',
            'Status Pendaftaran' => 'Terdaftar',
            'Kategori Pendaftar' => 'Keluarga Annur',
            'Jenis Kelamin' => 'Laki-laki',
            'NIK Siswa' => '0012345678901234',
            'NISN' => '0012345678',
            'Nomor KK' => '0000000000000123',
            'RT' => '001',
            'RW' => '002',
            'Kode Pos' => '00123',
            'Ayah - Nama' => 'AYAH LEGACY',
            'Ayah - No. Telepon' => '081000000001',
            'Ayah - NIK' => '0000000000000011',
            'Ibu - Nama' => 'IBU MANDIRI',
            'Wali - Nama' => 'WALI OPSIONAL',
            'Jumlah Saudara' => '4',
            'Jumlah Record Detail Saudara' => '2',
            'Nama Saudara' => "1. SAUDARA PERTAMA\n2. SAUDARA KEDUA",
            'Tanggal Lahir Saudara' => "1. 2016-01-01\n2. 2020-02-02",
            'Jenis Kelamin Saudara' => "1. Laki-laki\n2. Perempuan",
            'Pendidikan/Pekerjaan Saudara' => "1. SD\n2. TK",
            'Alamat' => '=HYPERLINK("https://example.test")',
            'Hobi' => '+Menggambar',
            'Cita-cita' => '@Dokter',
        ])->and($dataRow->cells[1])->toBeInstanceOf(StringCell::class)
            ->and($dataRow->cells[array_search('NISN', PpdbBiodataRecapQuery::detailHeaders(), true)])->toBeInstanceOf(StringCell::class);

        $archive = new ZipArchive;
        expect($archive->open($export['path']))->toBeTrue();
        $worksheetXml = '';
        for ($sheet = 1; $sheet <= 6; $sheet++) {
            $worksheetXml .= (string) $archive->getFromName("xl/worksheets/sheet{$sheet}.xml");
        }
        $archive->close();

        expect($worksheetXml)->not->toContain('<f>')
            ->toContain('=HYPERLINK', '+Menggambar', '@Dokter');
    } finally {
        @unlink($export['path']);
    }
});

it('writes blanks for missing profile and parents and keeps TK NISN blank', function () {
    $tkClass = SchoolClass::factory()->create(['level' => -1]);
    $withStoredNisn = recapWriterApplicant($this->year, $tkClass, [
        'nama_orang_tua' => null,
        'no_telp_orang_tua' => null,
    ]);
    PpdbProfile::create([
        'prospective_student_id' => $withStoredNisn->id,
        'nisn' => '0012345678',
    ]);
    recapWriterApplicant($this->year, $tkClass, [
        'jenis_kelamin' => 'P',
        'nama_orang_tua' => null,
        'no_telp_orang_tua' => null,
    ]);

    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::TK);

    try {
        $sheets = recapWriterRows($export['path']);
        $ikhwan = recapWriterFirstDetailRow($sheets['IKHWAN KELUARGA ANNUR']);
        $akhwat = recapWriterFirstDetailRow($sheets['AKHWAT KELUARGA ANNUR']);

        expect($ikhwan['NISN'])->toBe('')
            ->and($ikhwan['Ayah - Nama'])->toBe('')
            ->and($ikhwan['Ibu - Nama'])->toBe('')
            ->and($ikhwan['Wali - Nama'])->toBe('')
            ->and($akhwat['NIK Siswa'])->toBe('')
            ->and($akhwat['Tempat Lahir'])->toBe('');
    } finally {
        @unlink($export['path']);
    }
});

it('preserves newest first ordering and id descending timestamp tie breaker', function () {
    $oldest = recapWriterApplicant($this->year, $this->smpClass, ['registration_number' => 'REG-OLD']);
    $tieFirst = recapWriterApplicant($this->year, $this->smpClass, ['registration_number' => 'REG-TIE-1']);
    $tieSecond = recapWriterApplicant($this->year, $this->smpClass, ['registration_number' => 'REG-TIE-2']);
    DB::table('prospective_students')->where('id', $oldest->id)->update(['created_at' => '2031-01-01 08:00:00']);
    DB::table('prospective_students')->whereIn('id', [$tieFirst->id, $tieSecond->id])->update(['created_at' => '2032-01-01 08:00:00']);

    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::SMP);

    try {
        $rows = recapWriterRows($export['path'])['IKHWAN KELUARGA ANNUR'];
        $headerIndex = $rows->search(fn ($row): bool => ($row->toArray()[0] ?? null) === 'No.');
        $registrationIndex = array_search('No. Pendaftaran', $rows[$headerIndex]->toArray(), true);
        $order = $rows->slice($headerIndex + 1)->map(fn ($row) => $row->toArray()[$registrationIndex] ?? null)->filter()->values()->all();

        expect($order)->toBe(['REG-TIE-2', 'REG-TIE-1', 'REG-OLD']);
    } finally {
        @unlink($export['path']);
    }
});

it('adds freeze panes and autofilters to every detail sheet including empty groups', function () {
    recapWriterApplicant($this->year, $this->smpClass);
    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::SMP);
    $archive = new ZipArchive;

    try {
        expect($archive->open($export['path']))->toBeTrue();

        for ($sheet = 2; $sheet <= 6; $sheet++) {
            $xml = (string) $archive->getFromName("xl/worksheets/sheet{$sheet}.xml");
            expect($xml)->toContain('ySplit="7"', 'topLeftCell="A8"', '<autoFilter ref="A7:BZ');
        }
    } finally {
        $archive->close();
        @unlink($export['path']);
    }
});

it('keeps writer selects bounded without relation N plus one', function (int $size, int $expectedSelects) {
    ProspectiveStudent::factory()->count($size)->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->smpClass->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $beforePeak = memory_get_peak_usage(true);

    $export = $this->spreadsheet->create($this->year->id, SchoolLevel::SMP);

    $selectCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
        ->count();
    DB::disableQueryLog();
    $peakIncrease = memory_get_peak_usage(true) - $beforePeak;

    try {
        expect(is_file($export['path']))->toBeTrue()
            ->and(filesize($export['path']))->toBeGreaterThan(0)
            ->and($selectCount)->toBe($expectedSelects)
            ->and($peakIncrease)->toBeLessThan(64 * 1024 * 1024);
    } finally {
        @unlink($export['path']);
    }
})->with([
    'one populated chunk' => [1, 11],
    'two populated chunks' => [201, 16],
    'two exact populated chunks' => [400, 16],
]);

it('removes the incomplete temporary workbook and exposes only a safe error', function () {
    $before = glob(sys_get_temp_dir().'/annur-ppdb-biodata-recap-*') ?: [];
    $failingQuery = new class extends PpdbBiodataRecapQuery
    {
        public function processSnapshot(
            int $academicYearId,
            SchoolLevel $schoolLevel,
            Closure $callback,
            ?Closure $initialize = null,
        ): array {
            throw new RuntimeException('raw applicant biodata must not escape');
        }
    };
    $spreadsheet = new PpdbBiodataRecapSpreadsheet($failingQuery);

    expect(fn () => $spreadsheet->create($this->year->id, SchoolLevel::SMP))
        ->toThrow(RuntimeException::class, 'Gagal membuat rekap biodata PPDB.');

    $after = glob(sys_get_temp_dir().'/annur-ppdb-biodata-recap-*') ?: [];
    expect($after)->toBe($before);
});
