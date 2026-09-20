<?php

namespace App\Services;

use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Ekspor data calon siswa untuk keperluan bulk update biodata dan membaca
 * kembali file tersebut. Kolom identitas (ID Sistem, No. Pendaftaran, Tahun
 * Ajaran Tujuan, Kelas Tujuan) bersifat read-only. Sel kosong pada kolom
 * biodata TIDAK diartikan sebagai "hapus", tetapi "pertahankan nilai lama".
 */
class ProspectiveStudentBulkUpdateSpreadsheet
{
    /**
     * Header identitas/referensi yang tidak dapat diubah via bulk update.
     *
     * @var array<int, string>
     */
    public const READ_ONLY_HEADERS = [
        'ID Sistem',
        'No. Pendaftaran',
        'Tahun Ajaran Tujuan',
        'Kelas Tujuan',
    ];

    /**
     * Kolom biodata yang dapat diubah melalui bulk update. Kunci memakai
     * nama atribut ProspectiveStudent.
     *
     * @var array<int, array{key: string, label: string}>
     */
    public const UPDATE_COLUMNS = [
        ['key' => 'nama_lengkap', 'label' => 'Nama Lengkap'],
        ['key' => 'nama_panggilan', 'label' => 'Nama Panggilan'],
        ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin'],
        ['key' => 'nama_orang_tua', 'label' => 'Nama Orang Tua'],
        ['key' => 'no_telp_orang_tua', 'label' => 'No Telp Orang Tua'],
        ['key' => 'alamat', 'label' => 'Alamat'],
        ['key' => 'notes', 'label' => 'Catatan'],
    ];

    /** @var array<int, string> */
    public const HEADERS = [
        ...self::READ_ONLY_HEADERS,
        'Nama Lengkap',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Nama Orang Tua',
        'No Telp Orang Tua',
        'Alamat',
        'Catatan',
    ];

    public const DATA_SHEET_NAME = 'DATA CALON SISWA';

    /**
     * Buat file XLSX berisi calon siswa yang EKSIS (bukan template kosong).
     *
     * Calon siswa yang sudah dikonversi dikecualikan dari ekspor.
     *
     * @return string path file sementara
     */
    public function create(?int $academicYearId, ?SchoolLevel $schoolLevel, ?int $classId): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-prospective-bulk-update-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file update data calon siswa.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName(self::DATA_SHEET_NAME);
            $writer->addRow(Row::fromValues(self::HEADERS));

            $prospects = $this->prospectsForExport($academicYearId, $schoolLevel, $classId);

            foreach ($prospects as $prospect) {
                $writer->addRow(Row::fromValues([
                    $prospect->id,
                    $prospect->registration_number,
                    $prospect->academicYear?->year ?? '-',
                    $prospect->schoolClass?->name ?? '-',
                    $prospect->nama_lengkap ?? '',
                    $prospect->nama_panggilan ?? '',
                    $prospect->jenis_kelamin ?? '',
                    $prospect->nama_orang_tua ?? '',
                    $prospect->no_telp_orang_tua ?? '',
                    $prospect->alamat ?? '',
                    $prospect->notes ?? '',
                ]));
            }

            $this->addGuideSheet($writer);
        } finally {
            $writer->close();
        }

        return $path;
    }

    /**
     * Baca file update yang diunggah.
     *
     * @return array<int, array<string, string|int|null>> baris dengan kunci atribut
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== self::DATA_SHEET_NAME) {
                    continue;
                }

                $rows = [];
                $rowNumber = 0;
                $headerIndex = [];
                $isFirstRow = true;
                $columnCount = count(self::HEADERS);

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $rowValues = $row->toArray();

                    if ($isFirstRow) {
                        $headers = array_slice(array_map(fn (mixed $value): string => trim((string) $value), $rowValues), 0, $columnCount);

                        foreach (self::HEADERS as $index => $expected) {
                            if (($headers[$index] ?? null) === $expected) {
                                $headerIndex[$expected] = $index;
                            }
                        }

                        $missing = array_values(array_diff(self::HEADERS, array_keys($headerIndex)));
                        if ($missing !== []) {
                            throw new RuntimeException('Header file tidak sesuai template Update Data Calon Siswa (kurang: '.implode(', ', $missing).').');
                        }

                        $isFirstRow = false;

                        continue;
                    }

                    $values = array_slice($rowValues, 0, $columnCount);
                    $values = array_pad($values, $columnCount, '');

                    if (collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                        continue;
                    }

                    $mapped = ['row_number' => $rowNumber];

                    foreach (self::HEADERS as $index => $expected) {
                        $value = $this->stringValue($values[$index] ?? '');
                        $parsed = $expected === 'ID Sistem'
                            ? $this->parseId($value)
                            : $value;

                        $mapped[$this->attributeForKey($expected)] = $parsed;
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

    /**
     * @return Collection<int, ProspectiveStudent>
     */
    private function prospectsForExport(?int $academicYearId, ?SchoolLevel $schoolLevel, ?int $classId): Collection
    {
        $query = ProspectiveStudent::query()
            ->with(['academicYear', 'schoolClass'])
            ->where('status', '!=', ProspectiveStudentStatus::Converted->value)
            ->when($academicYearId !== null, function ($query) use ($academicYearId): void {
                $query->where('academic_year_id', $academicYearId);
            })
            ->when($classId !== null, function ($query) use ($classId): void {
                $query->where('school_class_id', $classId);
            })
            ->when($schoolLevel !== null, function ($query) use ($schoolLevel): void {
                $query->whereIn(
                    'school_class_id',
                    SchoolClass::query()->whereIn('level', $schoolLevel->classLevels())->select('id')
                );
            });

        return $query
            ->orderBy(SchoolClass::query()
                ->select('level')
                ->whereColumn('school_classes.id', 'prospective_students.school_class_id')
                ->limit(1))
            ->orderBy(SchoolClass::query()
                ->select('name')
                ->whereColumn('school_classes.id', 'prospective_students.school_class_id')
                ->limit(1))
            ->orderBy('nama_lengkap')
            ->get();
    }

    private function addGuideSheet(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('PANDUAN');

        $writer->addRow(Row::fromValues(['PANDUAN UPDATE DATA CALON SISWA']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Jangan ubah ID Sistem. Kolom ini memastikan data diperbarui pada calon siswa yang sama.']));
        $writer->addRow(Row::fromValues(['No. Pendaftaran, Tahun Ajaran Tujuan, dan Kelas Tujuan hanya referensi. Perubahan di kolom tersebut diabaikan.']));
        $writer->addRow(Row::fromValues(['Sel kosong = data lama tidak diubah (dibiarkan seperti sekarang).']));
        $writer->addRow(Row::fromValues(['Isi hanya biodata yang ingin ditambahkan atau diperbarui.']));
        $writer->addRow(Row::fromValues(['Untuk mengosongkan nilai: gunakan form Edit Calon Siswa, bukan fitur update massal.']));
        $writer->addRow(Row::fromValues(['Upload kembali file melalui menu "Update Data Calon Siswa".']));
    }

    private function attributeForKey(string $header): string
    {
        $match = collect(self::UPDATE_COLUMNS)->first(fn (array $column): bool => $column['label'] === $header);

        return match (true) {
            $header === 'ID Sistem' => 'id_sistem',
            $header === 'No. Pendaftaran' => 'no_pendaftaran',
            $header === 'Tahun Ajaran Tujuan' => 'tahun_ajaran',
            $header === 'Kelas Tujuan' => 'kelas',
            $match !== null => $match['key'],
            default => strtolower(str_replace(' ', '_', $header)),
        };
    }

    private function parseId(string $value): ?int
    {
        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
