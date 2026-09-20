<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Ekspor data siswa untuk keperluan bulk update biodata dan membaca kembali
 * file tersebut. Kotak yang dikosongkan admin TIDAK diartikan sebagai "hapus",
 * tetapi "pertahankan nilai lama" (ditangani oleh StudentBulkUpdateService).
 */
class StudentBulkUpdateSpreadsheet
{
    /**
     * Kolom update yang dapat diubah lewat bulk update. Header ini juga menjadi
     * kontrak parse; kunci menggunakan nama atribut Student.
     *
     * @var array<int, array{key: string, label: string}>
     */
    public const UPDATE_COLUMNS = [
        ['key' => 'nama_lengkap', 'label' => 'Nama Lengkap'],
        ['key' => 'nis', 'label' => 'NIS'],
        ['key' => 'nama_panggilan', 'label' => 'Nama Panggilan'],
        ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin'],
        ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir'],
        ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir'],
        ['key' => 'nama_ayah', 'label' => 'Nama Ayah'],
        ['key' => 'no_telp_ayah', 'label' => 'No Telp Ayah'],
        ['key' => 'nama_ibu', 'label' => 'Nama Ibu'],
        ['key' => 'no_telp_ibu', 'label' => 'No Telp Ibu'],
        ['key' => 'alamat', 'label' => 'Alamat'],
    ];

    /** Header identitas yang tidak dapat diubah. */
    public const ID_SISTEM_HEADER = 'ID Sistem';

    public const KELAS_HEADER = 'Kelas';

    /**
     * Header lengkap file update (identitas + referensi + kolom biodata).
     *
     * @var array<int, string>
     */
    public const HEADERS = [
        self::ID_SISTEM_HEADER,
        self::KELAS_HEADER,
        'Nama Lengkap',
        'NIS',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Nama Ayah',
        'No Telp Ayah',
        'Nama Ibu',
        'No Telp Ibu',
        'Alamat',
    ];

    /**
     * Buat file XLSX berisi siswa yang EKSIS (bukan template kosong).
     *
     * @return string path file sementara
     */
    public function create(?SchoolLevel $schoolLevel, ?int $classId): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-bulk-update-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file update data siswa.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA SISWA');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $students = $this->studentsForExport($schoolLevel, $classId);

            foreach ($students as $student) {
                $writer->addRow(Row::fromValues([
                    $student->id,
                    $student->schoolClass?->name ?? '-',
                    $student->nama_lengkap ?? '',
                    $student->nis ?? '',
                    $student->nama_panggilan ?? '',
                    $student->jenis_kelamin ?? '',
                    $student->tempat_lahir ?? '',
                    $this->dateValue($student->tanggal_lahir),
                    $student->nama_ayah ?? '',
                    $student->no_telp_ayah ?? '',
                    $student->nama_ibu ?? '',
                    $student->no_telp_ibu ?? '',
                    $student->alamat ?? '',
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
     * @return array<int, array<string, string|int>> baris dengan kunci atribut
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'DATA SISWA') {
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
                            throw new RuntimeException('Header file tidak sesuai template Update Data Siswa (kurang: '.implode(', ', $missing).').');
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
                        $parsed = $expected === self::ID_SISTEM_HEADER
                            ? $this->parseId($value)
                            : ($expected === self::KELAS_HEADER ? $value : $value);

                        $mapped[$this->attributeForKey($expected)] = $parsed;
                    }

                    $rows[] = $mapped;
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet DATA SISWA.');
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsForExport(?SchoolLevel $schoolLevel, ?int $classId): Collection
    {
        $query = Student::query()->with('schoolClass');

        if ($classId !== null) {
            $query->where('class_id', $classId);
        } elseif ($schoolLevel !== null) {
            $query->whereHas('schoolClass', fn ($q) => $q->whereIn('level', $schoolLevel->classLevels()));
        }

        return $query
            ->orderBy(SchoolClass::query()
                ->select('level')
                ->whereColumn('school_classes.id', 'students.class_id')
                ->limit(1))
            ->orderBy(SchoolClass::query()
                ->select('name')
                ->whereColumn('school_classes.id', 'students.class_id')
                ->limit(1))
            ->orderBy('nama_lengkap')
            ->get();
    }

    private function addGuideSheet(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('PANDUAN');

        $writer->addRow(Row::fromValues(['PANDUAN UPDATE DATA SISWA']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Jangan ubah ID Sistem. Kolom ini memastikan data diperbarui pada siswa yang sama.']));
        $writer->addRow(Row::fromValues(['Kelas hanya sebagai referensi identitas. Perubahan kelas melalui fitur ini diabaikan.']));
        $writer->addRow(Row::fromValues(['Sel kosong = data lama tidak diubah (dibiarkan seperti sekarang).']));
        $writer->addRow(Row::fromValues(['Isi hanya biodata yang ingin ditambahkan atau diperbarui.']));
        $writer->addRow(Row::fromValues(['Perubahan pindah/kenaikan kelas harus dilakukan melalui fitur akademik.']));
        $writer->addRow(Row::fromValues(['Upload kembali file melalui menu "Update Data Siswa".']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Untuk mengosongkan nilai: gunakan form Edit Siswa, bukan fitur update massal.']));
    }

    private function attributeForKey(string $header): string
    {
        $match = collect(self::UPDATE_COLUMNS)->first(fn (array $column): bool => $column['label'] === $header);

        return match (true) {
            $header === self::ID_SISTEM_HEADER => 'id_sistem',
            $header === self::KELAS_HEADER => 'kelas',
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

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return '';
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
