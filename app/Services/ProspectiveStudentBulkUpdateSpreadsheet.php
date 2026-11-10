<?php

namespace App\Services;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ProspectiveStudentBulkUpdateSpreadsheet
{
    public const SNAPSHOT_HEADER = 'Token Snapshot';

    /** @var list<string> */
    public const READ_ONLY_HEADERS = [
        'ID Sistem',
        'No. Pendaftaran',
        'Tahun Ajaran Tujuan',
        'Kelas Tujuan',
        self::SNAPSHOT_HEADER,
    ];

    /** @var list<array{key: string, label: string, section: string}> */
    public const UPDATE_COLUMNS = [
        ['key' => 'applicant_category', 'label' => 'Kategori Pendaftar', 'section' => 'Registrasi & Kategori'],
        ['key' => 'nama_lengkap', 'label' => 'Nama Lengkap', 'section' => 'Data Inti Siswa'],
        ['key' => 'nama_panggilan', 'label' => 'Nama Panggilan', 'section' => 'Data Inti Siswa'],
        ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'section' => 'Data Inti Siswa'],
        ['key' => 'nama_orang_tua', 'label' => 'Nama Orang Tua', 'section' => 'Data Inti Siswa'],
        ['key' => 'no_telp_orang_tua', 'label' => 'No Telp Orang Tua', 'section' => 'Data Inti Siswa'],
        ['key' => 'notes', 'label' => 'Catatan', 'section' => 'Data Inti Siswa'],
        ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'section' => 'Identitas Siswa'],
        ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'section' => 'Identitas Siswa'],
        ['key' => 'asal_sekolah', 'label' => 'Asal Sekolah', 'section' => 'Identitas Siswa'],
        ['key' => 'nik_siswa', 'label' => 'NIK Siswa', 'section' => 'Identitas Siswa'],
        ['key' => 'nisn', 'label' => 'NISN', 'section' => 'Identitas Siswa'],
        ['key' => 'nomor_kk', 'label' => 'Nomor KK', 'section' => 'Identitas Siswa'],
        ['key' => 'nomor_akta_kelahiran', 'label' => 'Nomor Akta Kelahiran', 'section' => 'Identitas Siswa'],
        ['key' => 'anak_ke', 'label' => 'Anak Ke', 'section' => 'Identitas Siswa'],
        ['key' => 'jumlah_saudara', 'label' => 'Jumlah Saudara', 'section' => 'Identitas Siswa'],
        ['key' => 'hobi', 'label' => 'Hobi', 'section' => 'Identitas Siswa'],
        ['key' => 'cita_cita', 'label' => 'Cita-cita', 'section' => 'Identitas Siswa'],
        ['key' => 'status_anak', 'label' => 'Status Anak', 'section' => 'Identitas Siswa'],
        ['key' => 'agama', 'label' => 'Agama', 'section' => 'Identitas Siswa'],
        ['key' => 'bahasa_sehari_hari', 'label' => 'Bahasa Sehari-hari', 'section' => 'Identitas Siswa'],
        ['key' => 'alamat', 'label' => 'Alamat', 'section' => 'Alamat'],
        ['key' => 'rt', 'label' => 'RT', 'section' => 'Alamat'],
        ['key' => 'rw', 'label' => 'RW', 'section' => 'Alamat'],
        ['key' => 'kelurahan', 'label' => 'Kelurahan', 'section' => 'Alamat'],
        ['key' => 'kecamatan', 'label' => 'Kecamatan', 'section' => 'Alamat'],
        ['key' => 'kabupaten_kota', 'label' => 'Kabupaten/Kota', 'section' => 'Alamat'],
        ['key' => 'provinsi', 'label' => 'Provinsi', 'section' => 'Alamat'],
        ['key' => 'kode_pos', 'label' => 'Kode Pos', 'section' => 'Alamat'],
        ['key' => 'jarak_tempuh_kategori', 'label' => 'Kategori Jarak Tempuh', 'section' => 'Alamat'],
        ['key' => 'jarak_tempuh_detail', 'label' => 'Detail Jarak Tempuh (km)', 'section' => 'Alamat'],
        ['key' => 'moda_transportasi', 'label' => 'Moda Transportasi', 'section' => 'Alamat'],
        ['key' => 'moda_transportasi_lainnya', 'label' => 'Moda Transportasi Lainnya', 'section' => 'Alamat'],
        ['key' => 'tinggi_badan', 'label' => 'Tinggi Badan (cm)', 'section' => 'Kesehatan'],
        ['key' => 'berat_badan', 'label' => 'Berat Badan (kg)', 'section' => 'Kesehatan'],
        ['key' => 'lingkar_kepala', 'label' => 'Lingkar Kepala (cm)', 'section' => 'Kesehatan'],
        ['key' => 'golongan_darah', 'label' => 'Golongan Darah', 'section' => 'Kesehatan'],
        ['key' => 'riwayat_penyakit', 'label' => 'Riwayat Penyakit', 'section' => 'Kesehatan'],
        ['key' => 'berkebutuhan_khusus', 'label' => 'Berkebutuhan Khusus', 'section' => 'Kesehatan'],
        ['key' => 'keterangan_kebutuhan_khusus', 'label' => 'Keterangan Kebutuhan Khusus', 'section' => 'Kesehatan'],
        ['key' => 'father_status_hidup', 'label' => 'Ayah - Status Hidup', 'section' => 'Ayah'],
        ['key' => 'father_nama', 'label' => 'Ayah - Nama', 'section' => 'Ayah'],
        ['key' => 'father_nik', 'label' => 'Ayah - NIK', 'section' => 'Ayah'],
        ['key' => 'father_tempat_lahir', 'label' => 'Ayah - Tempat Lahir', 'section' => 'Ayah'],
        ['key' => 'father_tanggal_lahir', 'label' => 'Ayah - Tanggal Lahir', 'section' => 'Ayah'],
        ['key' => 'father_pendidikan_terakhir', 'label' => 'Ayah - Pendidikan Terakhir', 'section' => 'Ayah'],
        ['key' => 'father_email', 'label' => 'Ayah - Email', 'section' => 'Ayah'],
        ['key' => 'father_no_telp', 'label' => 'Ayah - No. Telepon', 'section' => 'Ayah'],
        ['key' => 'father_occupation', 'label' => 'Ayah - Pekerjaan', 'section' => 'Ayah'],
        ['key' => 'father_income_range', 'label' => 'Ayah - Penghasilan', 'section' => 'Ayah'],
        ['key' => 'mother_status_hidup', 'label' => 'Ibu - Status Hidup', 'section' => 'Ibu'],
        ['key' => 'mother_nama', 'label' => 'Ibu - Nama', 'section' => 'Ibu'],
        ['key' => 'mother_nik', 'label' => 'Ibu - NIK', 'section' => 'Ibu'],
        ['key' => 'mother_tempat_lahir', 'label' => 'Ibu - Tempat Lahir', 'section' => 'Ibu'],
        ['key' => 'mother_tanggal_lahir', 'label' => 'Ibu - Tanggal Lahir', 'section' => 'Ibu'],
        ['key' => 'mother_pendidikan_terakhir', 'label' => 'Ibu - Pendidikan Terakhir', 'section' => 'Ibu'],
        ['key' => 'mother_email', 'label' => 'Ibu - Email', 'section' => 'Ibu'],
        ['key' => 'mother_no_telp', 'label' => 'Ibu - No. Telepon', 'section' => 'Ibu'],
        ['key' => 'mother_occupation', 'label' => 'Ibu - Pekerjaan', 'section' => 'Ibu'],
        ['key' => 'mother_income_range', 'label' => 'Ibu - Penghasilan', 'section' => 'Ibu'],
        ['key' => 'guardian_nama', 'label' => 'Wali - Nama', 'section' => 'Wali'],
        ['key' => 'guardian_nik', 'label' => 'Wali - NIK', 'section' => 'Wali'],
        ['key' => 'guardian_tempat_lahir', 'label' => 'Wali - Tempat Lahir', 'section' => 'Wali'],
        ['key' => 'guardian_tanggal_lahir', 'label' => 'Wali - Tanggal Lahir', 'section' => 'Wali'],
        ['key' => 'guardian_pendidikan_terakhir', 'label' => 'Wali - Pendidikan Terakhir', 'section' => 'Wali'],
        ['key' => 'guardian_email', 'label' => 'Wali - Email', 'section' => 'Wali'],
        ['key' => 'guardian_no_telp', 'label' => 'Wali - No. Telepon', 'section' => 'Wali'],
        ['key' => 'guardian_occupation', 'label' => 'Wali - Pekerjaan', 'section' => 'Wali'],
        ['key' => 'guardian_income_range', 'label' => 'Wali - Penghasilan', 'section' => 'Wali'],
    ];

    /** @var list<string> */
    public const HEADERS = [
        ...self::READ_ONLY_HEADERS,
        'Kategori Pendaftar',
        'Nama Lengkap',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Nama Orang Tua',
        'No Telp Orang Tua',
        'Catatan',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Asal Sekolah',
        'NIK Siswa',
        'NISN',
        'Nomor KK',
        'Nomor Akta Kelahiran',
        'Anak Ke',
        'Jumlah Saudara',
        'Hobi',
        'Cita-cita',
        'Status Anak',
        'Agama',
        'Bahasa Sehari-hari',
        'Alamat',
        'RT',
        'RW',
        'Kelurahan',
        'Kecamatan',
        'Kabupaten/Kota',
        'Provinsi',
        'Kode Pos',
        'Kategori Jarak Tempuh',
        'Detail Jarak Tempuh (km)',
        'Moda Transportasi',
        'Moda Transportasi Lainnya',
        'Tinggi Badan (cm)',
        'Berat Badan (kg)',
        'Lingkar Kepala (cm)',
        'Golongan Darah',
        'Riwayat Penyakit',
        'Berkebutuhan Khusus',
        'Keterangan Kebutuhan Khusus',
        'Ayah - Status Hidup',
        'Ayah - Nama',
        'Ayah - NIK',
        'Ayah - Tempat Lahir',
        'Ayah - Tanggal Lahir',
        'Ayah - Pendidikan Terakhir',
        'Ayah - Email',
        'Ayah - No. Telepon',
        'Ayah - Pekerjaan',
        'Ayah - Penghasilan',
        'Ibu - Status Hidup',
        'Ibu - Nama',
        'Ibu - NIK',
        'Ibu - Tempat Lahir',
        'Ibu - Tanggal Lahir',
        'Ibu - Pendidikan Terakhir',
        'Ibu - Email',
        'Ibu - No. Telepon',
        'Ibu - Pekerjaan',
        'Ibu - Penghasilan',
        'Wali - Nama',
        'Wali - NIK',
        'Wali - Tempat Lahir',
        'Wali - Tanggal Lahir',
        'Wali - Pendidikan Terakhir',
        'Wali - Email',
        'Wali - No. Telepon',
        'Wali - Pekerjaan',
        'Wali - Penghasilan',
    ];

    /** @var list<string> */
    private const TEXT_ONLY_HEADERS = [
        'No. Pendaftaran',
        self::SNAPSHOT_HEADER,
        'No Telp Orang Tua',
        'NIK Siswa',
        'NISN',
        'Nomor KK',
        'Nomor Akta Kelahiran',
        'RT',
        'RW',
        'Kode Pos',
        'Ayah - NIK',
        'Ayah - No. Telepon',
        'Ibu - NIK',
        'Ibu - No. Telepon',
        'Wali - NIK',
        'Wali - No. Telepon',
    ];

    public const DATA_SHEET_NAME = 'DATA CALON SISWA';

    public function __construct(private readonly ProspectiveStudentBulkUpdateService $bulkUpdateService) {}

    public function create(?int $academicYearId, ?SchoolLevel $schoolLevel, ?int $classId, ?User $user = null): string
    {
        if ($user?->isTuUnit()) {
            $schoolLevel = $user->school_level;
        }

        $prospects = $this->prospectsForExport($academicYearId, $schoolLevel, $classId, $user);
        $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file update data calon siswa.');
        }

        $writer = new Writer;
        $writer->openToFile($path);
        $completed = false;

        try {
            $writer->getCurrentSheet()->setName(self::DATA_SHEET_NAME);
            $writer->addRow($this->stringRow(self::HEADERS));

            foreach ($prospects as $prospect) {
                $values = [
                    (string) $prospect->id,
                    $prospect->registration_number,
                    $prospect->academicYear?->year ?? '-',
                    $prospect->schoolClass?->name ?? '-',
                    $this->bulkUpdateService->snapshotToken($prospect),
                ];

                foreach (self::UPDATE_COLUMNS as $column) {
                    $values[] = $this->exportValue($prospect, $column['key']);
                }

                $writer->addRow($this->stringRow($values));
            }

            $this->addGuideSheet($writer);
            $completed = true;
        } finally {
            $writer->close();

            if (! $completed) {
                @unlink($path);
            }
        }

        return $path;
    }

    /** @return array<int, array<string, string|int|null>> */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== self::DATA_SHEET_NAME) {
                    continue;
                }

                if (! $sheet->isVisible()) {
                    throw new RuntimeException('Sheet DATA CALON SISWA tidak boleh disembunyikan.');
                }

                $rows = [];
                $rowNumber = 0;
                $isFirstRow = true;
                $columnCount = count(self::HEADERS);

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $this->rejectFormulaCells($row, $rowNumber);

                    if ($row->getNumCells() > $columnCount) {
                        throw new RuntimeException("Baris {$rowNumber} memiliki kolom tambahan yang tidak dikenali.");
                    }

                    $rowValues = $row->toArray();

                    if ($isFirstRow) {
                        $headers = array_map(fn (mixed $value): string => trim((string) $value), $rowValues);

                        if (count($headers) !== $columnCount
                            || count(array_unique($headers)) !== $columnCount
                            || $headers !== self::HEADERS) {
                            throw new RuntimeException('Header file tidak sesuai template Update Data Calon Siswa, berurutan, dan tanpa duplikat. Unduh template terbaru.');
                        }

                        $isFirstRow = false;

                        continue;
                    }

                    $values = array_pad(array_slice($rowValues, 0, $columnCount), $columnCount, '');
                    if (collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                        continue;
                    }

                    if (count($rows) >= ProspectiveStudentBulkUpdateService::MAX_DATA_ROWS) {
                        throw new RuntimeException('File melebihi batas maksimal '.ProspectiveStudentBulkUpdateService::MAX_DATA_ROWS.' baris data. Persempit data dan unggah kembali.');
                    }

                    $mapped = ['row_number' => $rowNumber];
                    foreach (self::HEADERS as $index => $header) {
                        $mapped[$this->attributeForHeader($header)] = $header === 'ID Sistem'
                            ? $this->parseId($values[$index] ?? '')
                            : $this->stringValue($values[$index] ?? '', $row->cells[$index] ?? null, $header, $rowNumber);
                    }

                    $rows[] = $mapped;
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet DATA CALON SISWA.');
    }

    /** @return Collection<int, ProspectiveStudent> */
    private function prospectsForExport(?int $academicYearId, ?SchoolLevel $schoolLevel, ?int $classId, ?User $user = null): Collection
    {
        $query = ProspectiveStudent::query()
            ->with([
                'academicYear',
                'schoolClass',
                'ppdbProfile',
                'ppdbParents',
                'convertedStudent:'.implode(',', PpdbStudentDataSynchronizer::STUDENT_COLUMNS),
            ])
            ->whereIn('status', [
                ProspectiveStudentStatus::Registered->value,
                ProspectiveStudentStatus::Converted->value,
            ])
            ->when($academicYearId !== null, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($classId !== null, fn ($query) => $query->where('school_class_id', $classId))
            ->when($schoolLevel !== null, function ($query) use ($schoolLevel): void {
                $query->whereIn(
                    'school_class_id',
                    SchoolClass::query()->whereIn('level', $schoolLevel->classLevels())->select('id'),
                );
            });

        if ($user?->isTuUnit()) {
            $query = app(ProspectiveStudentUnitScope::class)->applyForUser($query, $user);
        }

        $prospects = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(ProspectiveStudentBulkUpdateService::MAX_DATA_ROWS + 1)
            ->get();

        if ($prospects->count() > ProspectiveStudentBulkUpdateService::MAX_DATA_ROWS) {
            throw new RuntimeException('Data export melebihi batas maksimal '.ProspectiveStudentBulkUpdateService::MAX_DATA_ROWS.' calon siswa. Persempit filter tahun ajaran, jenjang, atau kelas.');
        }

        return $prospects;
    }

    private function addGuideSheet(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('PANDUAN');

        $categories = collect(PpdbApplicantCategory::cases())
            ->map(fn (PpdbApplicantCategory $category): string => $category->value.' = '.$category->label())
            ->implode('; ');
        $distanceOptions = collect(ProspectiveStudentBulkUpdateService::DISTANCE_OPTIONS)
            ->map(fn (string $label, string $value): string => $value.' = '.$label)
            ->implode('; ');
        $transportOptions = collect(ProspectiveStudentBulkUpdateService::TRANSPORTATION_OPTIONS)
            ->map(fn (string $label, string $value): string => $value.' = '.$label)
            ->implode('; ');
        $incomeOptions = collect(ParentIncomeRange::cases())
            ->map(fn (ParentIncomeRange $range): string => $range->value.' = '.$range->label())
            ->implode('; ');

        foreach ([
            'PANDUAN UPDATE DATA CALON SISWA',
            '',
            'Alur: download file terbaru, edit kolom yang diizinkan, upload, periksa preview, lalu konfirmasi.',
            'Data calon siswa terdaftar dan dikonversi dapat diperbarui. Field yang terkait siswa aktif disinkronkan otomatis.',
            'ID Sistem, No. Pendaftaran, Tahun Ajaran Tujuan, Kelas Tujuan, dan Token Snapshot tidak boleh diubah.',
            'Sel kosong berarti nilai lama dipertahankan. Gunakan #CLEAR# hanya untuk field nullable yang ingin dikosongkan.',
            'Nama Lengkap dan kolom referensi/protected tidak dapat dikosongkan dengan #CLEAR#.',
            'Kategori pendaftar yang valid: '.$categories.'.',
            'Kategori jarak yang valid: '.$distanceOptions.'. Detail wajib jika more_than_1_km, lebih dari 1 dan maksimal 9999,99 km.',
            'Moda transportasi yang valid: '.$transportOptions.'. Detail lainnya wajib jika moda = lainnya.',
            'Berkebutuhan Khusus: gunakan 1/0 atau Ya/Tidak. Keterangan wajib jika bernilai 1/Ya.',
            'Kolom Ayah, Ibu, dan Wali dipetakan berdasarkan role. Wali bersifat opsional dan tidak memiliki Status Hidup.',
            'Status Hidup Ayah/Ibu yang valid: hidup atau wafat. Penghasilan yang valid: '.$incomeOptions.'.',
            'NIK, NISN, Nomor KK, seluruh nomor telepon, RT, RW, dan Kode Pos harus tetap berformat teks agar nol di depan tidak hilang.',
            'Nama Orang Tua dan No Telp Orang Tua adalah alias legacy Ayah. Jika alias dan kolom Ayah sama-sama diubah, nilainya harus sama.',
            'Sel kosong pada kolom parent tidak menghapus atau membuat record. #CLEAR# hanya mengosongkan field nullable, bukan menghapus role.',
            'NISN non-TK harus tepat 10 digit jika diisi. NISN TK harus kosong.',
            'Tanggal Lahir menggunakan format YYYY-MM-DD dan harus sebelum hari ini.',
            'Maksimum 500 baris data. Persempit filter jika hasil export melebihi batas.',
            'Template lama atau data yang berubah setelah download akan ditolak sebagai stale. Unduh ulang file terbaru.',
            'Import bersifat all-or-nothing: satu baris gagal membatalkan seluruh file.',
        ] as $line) {
            $writer->addRow($this->stringRow([$line]));
        }
    }

    private function exportValue(ProspectiveStudent $prospect, string $field): string
    {
        if ($field === 'applicant_category') {
            return $prospect->applicant_category instanceof PpdbApplicantCategory
                ? $prospect->applicant_category->value
                : (string) ($prospect->applicant_category ?? '');
        }

        if (in_array($field, ProspectiveStudentBulkUpdateService::CORE_FIELDS, true)) {
            return (string) ($prospect->{$field} ?? '');
        }

        $parentParts = $this->parentFieldParts($field);
        if ($parentParts !== null) {
            [$role, $attribute] = $parentParts;
            $parent = $prospect->ppdbParents->firstWhere('role', $role);
            $value = $parent?->{$attribute};

            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if ($value instanceof ParentIncomeRange) {
                return $value->value;
            }

            return (string) ($value ?? '');
        }

        if ($field === 'nisn' && $prospect->schoolClass?->schoolLevel === SchoolLevel::TK) {
            return '';
        }

        $value = $prospect->ppdbProfile?->{$field};

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($field === 'berkebutuhan_khusus') {
            return $value === null ? '' : (string) (int) $value;
        }

        return (string) ($value ?? '');
    }

    /** @return array{0: string, 1: string}|null */
    private function parentFieldParts(string $field): ?array
    {
        foreach (array_keys(ProspectiveStudentBulkUpdateService::PARENT_ROLES) as $role) {
            $prefix = $role.'_';
            if (str_starts_with($field, $prefix)) {
                return [$role, substr($field, strlen($prefix))];
            }
        }

        return null;
    }

    private function attributeForHeader(string $header): string
    {
        $column = collect(self::UPDATE_COLUMNS)->first(fn (array $column): bool => $column['label'] === $header);

        return match (true) {
            $header === 'ID Sistem' => 'id_sistem',
            $header === 'No. Pendaftaran' => 'no_pendaftaran',
            $header === 'Tahun Ajaran Tujuan' => 'tahun_ajaran',
            $header === 'Kelas Tujuan' => 'kelas',
            $header === self::SNAPSHOT_HEADER => 'snapshot_token',
            $column !== null => $column['key'],
            default => strtolower(str_replace(' ', '_', $header)),
        };
    }

    private function parseId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' && ctype_digit($trimmed) && strlen($trimmed) <= 18
            ? (int) $trimmed
            : null;
    }

    private function stringValue(mixed $value, ?Cell $cell, string $header, int $rowNumber): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (in_array($header, self::TEXT_ONLY_HEADERS, true)
            && $value !== null
            && $value !== ''
            && (! is_string($value) || ! $cell instanceof StringCell)) {
            throw new RuntimeException("Kolom {$header} pada baris {$rowNumber} harus berformat teks agar angka dan nol di depan tetap utuh.");
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<int, scalar|null> $values */
    private function stringRow(array $values): Row
    {
        return new Row(array_map(
            fn (mixed $value): StringCell => new StringCell((string) ($value ?? '')),
            array_values($values),
        ));
    }

    private function rejectFormulaCells(Row $row, int $rowNumber): void
    {
        foreach ($row->cells as $index => $cell) {
            if ($cell instanceof FormulaCell) {
                throw new RuntimeException('Formula tidak diizinkan pada sheet DATA CALON SISWA (baris '.$rowNumber.', kolom '.($index + 1).').');
            }
        }
    }
}
