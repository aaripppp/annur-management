<?php

namespace App\Services;

use App\Models\SchoolClass;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ProspectiveStudentImportSpreadsheet
{
    /** @var array<int, string> */
    public const HEADERS = [
        'Nama Lengkap',
        'Kelas Tujuan',
        'Nama Panggilan',
        'Jenis Kelamin',
        'Nama Orang Tua',
        'No Telp Orang Tua',
        'Alamat',
        'Catatan',
    ];

    /** @var array<int, string> */
    public const MINIMAL_HEADERS = [
        'Nama Lengkap',
        'Kelas Tujuan',
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
                        $minimalHeaders = array_slice($currentHeaders, 0, count(self::MINIMAL_HEADERS));

                        if ($currentHeaders === self::HEADERS) {
                            $columnCount = count(self::HEADERS);
                        } elseif ($minimalHeaders === self::MINIMAL_HEADERS) {
                            $columnCount = count(self::MINIMAL_HEADERS);
                            $isMinimal = true;
                        } else {
                            throw new RuntimeException('Header file tidak sesuai template Import Calon Siswa.');
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
                            'nama_lengkap' => $values[0],
                            'kelas' => $values[1],
                            'nama_panggilan' => '',
                            'jenis_kelamin' => '',
                            'nama_orang_tua' => '',
                            'no_telp_orang_tua' => '',
                            'alamat' => '',
                            'notes' => '',
                        ];

                        continue;
                    }

                    $rows[] = [
                        'row_number' => $rowNumber,
                        'nama_lengkap' => $values[0],
                        'kelas' => $values[1],
                        'nama_panggilan' => $values[2],
                        'jenis_kelamin' => strtoupper($values[3]),
                        'nama_orang_tua' => $values[4],
                        'no_telp_orang_tua' => $values[5],
                        'alamat' => $values[6],
                        'notes' => $values[7],
                    ];
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet data calon siswa.');
    }

    public function createTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-prospective-import-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file template import.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA CALON SISWA');
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
            $writer->addRow(Row::fromValues(['PANDUAN IMPORT CALON SISWA']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['KOLOM WAJIB', 'Nama Lengkap', 'Kelas Tujuan']));
            $writer->addRow(Row::fromValues(['KOLOM OPSIONAL', 'Nama Panggilan', 'Jenis Kelamin (L/P)', 'Nama Orang Tua', 'No Telp Orang Tua', 'Alamat', 'Catatan']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['File minimal cukup berisi dua kolom:', 'Nama Lengkap', 'Kelas Tujuan']));
            $writer->addRow(Row::fromValues(['Gunakan nama kelas persis seperti sheet REFERENSI KELAS. Kelas yang tidak ditemukan akan ditandai bermasalah.']));
            $writer->addRow(Row::fromValues(['Tahun ajaran tujuan ditentukan dari halaman import, bukan dari file.']));
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
