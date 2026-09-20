<?php

namespace App\Services;

use App\Models\SchoolClass;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class StudentImportSpreadsheet
{
    /** @var array<int, string> */
    public const LEGACY_HEADERS = [
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
    ];

    /** @var array<int, string> */
    public const HEADERS = [
        ...self::LEGACY_HEADERS,
        'Tanggal Masuk',
    ];

    /** @var array<int, string> */
    public const MINIMAL_HEADERS = [
        'Nama Lengkap',
        'Kelas',
    ];

    /**
     * @return array<int, array<string, int|string>>
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                $rowNumber = 0;
                $columnCount = count(self::HEADERS);
                $isMinimal = false;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $rowValues = $row->toArray();

                    if ($rowNumber === 1) {
                        $currentHeaders = array_map($this->stringValue(...), array_slice($rowValues, 0, count(self::HEADERS)));
                        $legacyHeaders = array_slice($currentHeaders, 0, count(self::LEGACY_HEADERS));
                        $minimalHeaders = array_slice($currentHeaders, 0, count(self::MINIMAL_HEADERS));

                        if ($currentHeaders === self::HEADERS) {
                            $columnCount = count(self::HEADERS);
                        } elseif ($legacyHeaders === self::LEGACY_HEADERS) {
                            $columnCount = count(self::LEGACY_HEADERS);
                        } elseif ($minimalHeaders === self::MINIMAL_HEADERS) {
                            $columnCount = count(self::MINIMAL_HEADERS);
                            $isMinimal = true;
                        } else {
                            throw new RuntimeException('Header file tidak sesuai template Import Siswa.');
                        }

                        continue;
                    }

                    $values = array_map($this->stringValue(...), array_slice($rowValues, 0, $columnCount));
                    $values = array_pad($values, count(self::HEADERS), '');

                    if (collect($values)->every(fn (string $value): bool => $value === '')) {
                        continue;
                    }

                    if ($isMinimal) {
                        $rows[] = [
                            'row_number' => $rowNumber,
                            'nis' => '',
                            'nama_lengkap' => $values[0],
                            'nama_panggilan' => '',
                            'kelas' => $values[1],
                            'jenis_kelamin' => '',
                            'tempat_lahir' => '',
                            'tanggal_lahir' => '',
                            'nama_ayah' => '',
                            'no_telp_ayah' => '',
                            'nama_ibu' => '',
                            'no_telp_ibu' => '',
                            'alamat' => '',
                            'entry_date' => '',
                        ];

                        continue;
                    }

                    $rows[] = [
                        'row_number' => $rowNumber,
                        'nis' => $values[0],
                        'nama_lengkap' => $values[1],
                        'nama_panggilan' => $values[2],
                        'kelas' => $values[3],
                        'jenis_kelamin' => strtoupper($values[4]),
                        'tempat_lahir' => $values[5],
                        'tanggal_lahir' => $values[6],
                        'nama_ayah' => $values[7],
                        'no_telp_ayah' => $values[8],
                        'nama_ibu' => $values[9],
                        'no_telp_ibu' => $values[10],
                        'alamat' => $values[11],
                        'entry_date' => $values[12],
                    ];
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet data siswa.');
    }

    public function createTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-student-import-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file template import.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA SISWA');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $referenceSheet = $writer->addNewSheetAndMakeItCurrent();
            $referenceSheet->setName('REFERENSI KELAS');
            $writer->addRow(Row::fromValues(['Jenjang', 'Tingkat', 'Nama Kelas']));

            SchoolClass::query()
                ->get()
                ->sort(function (SchoolClass $left, SchoolClass $right): int {
                    $levelComparison = $left->level <=> $right->level;

                    return $levelComparison !== 0 ? $levelComparison : strnatcasecmp($left->name, $right->name);
                })
                ->each(function (SchoolClass $schoolClass) use ($writer): void {
                    $writer->addRow(Row::fromValues([
                        $this->jenjangLabel((int) $schoolClass->level),
                        $schoolClass->level_name,
                        $schoolClass->name,
                    ]));
                });

            $guideSheet = $writer->addNewSheetAndMakeItCurrent();
            $guideSheet->setName('PANDUAN');
            $writer->addRow(Row::fromValues(['PANDUAN IMPORT SISWA']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['KOLOM WAJIB', 'Nama Lengkap', 'Kelas']));
            $writer->addRow(Row::fromValues(['KOLOM OPSIONAL', 'NIS', 'Nama Panggilan', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir (YYYY-MM-DD)', 'Nama Ayah', 'No Telp Ayah', 'Nama Ibu', 'No Telp Ibu', 'Alamat', 'Tanggal Masuk (YYYY-MM-DD)']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['File minimal cukup berisi dua kolom:', 'Nama Lengkap', 'Kelas']));
            $writer->addRow(Row::fromValues(['Gunakan nama kelas persis seperti sheet REFERENSI KELAS. Kelas yang tidak ditemukan akan ditandai bermasalah.']));
        } finally {
            $writer->close();
        }

        return $path;
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

    private function jenjangLabel(int $level): string
    {
        return match (true) {
            in_array($level, [-3, -2, -1], true) => 'TK',
            $level >= 1 && $level <= 6 => 'SD',
            $level >= 7 && $level <= 9 => 'SMP',
            $level >= 10 && $level <= 12 => 'SMA',
            default => '-',
        };
    }
}
