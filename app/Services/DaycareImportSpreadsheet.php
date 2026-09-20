<?php

namespace App\Services;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class DaycareImportSpreadsheet
{
    /** @var array<int, string> */
    public const HEADERS = [
        'Nama Lengkap',
        'Kelas',
        'Nama Panggilan',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Jenis Kelamin',
        'Alamat',
        'Nama Ayah',
        'No Telp Ayah',
        'Nama Ibu',
        'No Telp Ibu',
    ];

    /** @var array<int, string> */
    public const MINIMAL_HEADERS = [
        'Nama Lengkap',
        'Kelas',
    ];

    /**
     * @return array<int, array<string, string>>
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
                            throw new RuntimeException('Header file tidak sesuai template Import Daycare.');
                        }

                        continue;
                    }

                    $values = array_map($this->stringValue(...), array_slice($rowValues, 0, $columnCount));
                    $values = array_pad($values, count(self::HEADERS), '');

                    if (collect($values)->every(fn (string $value): bool => $value === '')) {
                        continue;
                    }

                    if ($isMinimal) {
                        $rows[] = $this->rowFromValues($rowNumber, $values[0], $values[1], '', '', '', '', '', '', '', '', '');

                        continue;
                    }

                    $rows[] = $this->rowFromValues(
                        $rowNumber,
                        $values[0],
                        $values[1],
                        $values[2],
                        $values[3],
                        $values[4],
                        $values[5],
                        $values[6],
                        $values[7],
                        $values[8],
                        $values[9],
                        $values[10],
                    );
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet data daycare.');
    }

    public function createTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-daycare-import-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file template import.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA DAYCARE');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $referenceSheet = $writer->addNewSheetAndMakeItCurrent();
            $referenceSheet->setName('REFERENSI KELAS');
            $writer->addRow(Row::fromValues(['Kelas']));

            foreach (DaycareImportService::VALID_CLASSES as $kelas) {
                $writer->addRow(Row::fromValues([$kelas]));
            }

            $guideSheet = $writer->addNewSheetAndMakeItCurrent();
            $guideSheet->setName('PANDUAN');
            $writer->addRow(Row::fromValues(['PANDUAN IMPORT DAYCARE']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['KOLOM WAJIB', 'Nama Lengkap', 'Kelas']));
            $writer->addRow(Row::fromValues(['KOLOM OPSIONAL', 'Nama Panggilan', 'Tempat Lahir', 'Tanggal Lahir (YYYY-MM-DD)', 'Jenis Kelamin (L/P)', 'Alamat', 'Nama Ayah', 'No Telp Ayah', 'Nama Ibu', 'No Telp Ibu']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['File minimal cukup berisi dua kolom:', 'Nama Lengkap', 'Kelas']));
            $writer->addRow(Row::fromValues(['Gunakan nama kelas persis seperti sheet REFERENSI KELAS. Kelas yang tidak valid (di luar A-E) akan ditandai bermasalah.']));
            $writer->addRow(Row::fromValues(['Fitur ini hanya menambah anak daycare baru dan tidak memperbarui ataupun menghapus data yang sudah ada.']));
        } finally {
            $writer->close();
        }

        return $path;
    }

    /**
     * @return array{row_number: int, nama_lengkap: string, kelas: string, nama_panggilan: string, tempat_lahir: string, tanggal_lahir: string, jenis_kelamin: string, alamat: string, nama_ayah: string, no_telp_ayah: string, nama_ibu: string, no_telp_ibu: string}
     */
    private function rowFromValues(
        int $rowNumber,
        string $namaLengkap,
        string $kelas,
        string $namaPanggilan,
        string $tempatLahir,
        string $tanggalLahir,
        string $jenisKelamin,
        string $alamat,
        string $namaAyah,
        string $noTelpAyah,
        string $namaIbu,
        string $noTelpIbu,
    ): array {
        return [
            'row_number' => $rowNumber,
            'nama_lengkap' => $namaLengkap,
            'kelas' => strtoupper($kelas),
            'nama_panggilan' => $namaPanggilan,
            'tempat_lahir' => $tempatLahir,
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => strtoupper($jenisKelamin),
            'alamat' => $alamat,
            'nama_ayah' => $namaAyah,
            'no_telp_ayah' => $noTelpAyah,
            'nama_ibu' => $namaIbu,
            'no_telp_ibu' => $noTelpIbu,
        ];
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
