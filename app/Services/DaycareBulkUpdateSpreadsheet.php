<?php

namespace App\Services;

use App\Models\DaycareChild;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Ekspor data anak daycare untuk keperluan update massal biodata dan membaca
 * kembali file tersebut. Sel yang dikosongkan admin TIDAK diartikan sebagai
 * "hapus nilai", tetapi "pertahankan nilai lama" (ditangani oleh DaycareBulkUpdateService).
 *
 * Kontrak penyimpan identitas: kolom "ID Sistem" berisi ID anak daycare dan
 * DAHULUKANNYA tidak boleh diubah. Identitas update hanya dari "ID Sistem",
 * bukan nama + kelas.
 */
class DaycareBulkUpdateSpreadsheet
{
    /**
     * Header lengkap file update (identitas + referensi kelas + kolom biodata).
     *
     * @var array<int, string>
     */
    public const HEADERS = [
        'ID Sistem',
        'Kelas',
        'Nama Lengkap',
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

    /** Header identitas yang tidak dapat diubah. */
    public const ID_SISTEM_HEADER = 'ID Sistem';

    public const KELAS_HEADER = 'Kelas';

    /** @var array<int, array{key: string, label: string}> */
    public const UPDATE_COLUMNS = [
        ['key' => 'nama_lengkap', 'label' => 'Nama Lengkap'],
        ['key' => 'nama_panggilan', 'label' => 'Nama Panggilan'],
        ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir'],
        ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir'],
        ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin'],
        ['key' => 'alamat', 'label' => 'Alamat'],
        ['key' => 'nama_ayah', 'label' => 'Nama Ayah'],
        ['key' => 'no_telp_ayah', 'label' => 'No Telp Ayah'],
        ['key' => 'nama_ibu', 'label' => 'Nama Ibu'],
        ['key' => 'no_telp_ibu', 'label' => 'No Telp Ibu'],
    ];

    /**
     * Buat file XLSX berisi anak daycare yang EKSIS (bukan template kosong),
     * difilter berdasarkan kelas apabila disediakan.
     *
     * @return string path file sementara
     */
    public function create(?string $kelas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-daycare-bulk-update-');

        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file update data daycare.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('DATA DAYCARE');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $children = $this->childrenForExport($kelas);

            foreach ($children as $child) {
                $writer->addRow(Row::fromValues([
                    $child->id,
                    $child->kelas,
                    $child->nama_lengkap ?? '',
                    $child->nama_panggilan ?? '',
                    $child->tempat_lahir ?? '',
                    $this->dateValue($child->tanggal_lahir),
                    $child->jenis_kelamin ?? '',
                    $child->alamat ?? '',
                    $child->nama_ayah ?? '',
                    $child->no_telp_ayah ?? '',
                    $child->nama_ibu ?? '',
                    $child->no_telp_ibu ?? '',
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
     * @return array<int, array<string, int|string|null>> baris dengan kunci atribut
     */
    public function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'DATA DAYCARE') {
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
                            throw new RuntimeException('Header file tidak sesuai template Update Data Daycare (kurang: '.implode(', ', $missing).').');
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
                        $mapped[$this->attributeForKey($expected)] = $expected === self::ID_SISTEM_HEADER
                            ? $this->parseId($value)
                            : $value;
                    }

                    $rows[] = $mapped;
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException('Workbook tidak memiliki sheet DATA DAYCARE.');
    }

    /**
     * @return Collection<int, DaycareChild>
     */
    private function childrenForExport(?string $kelas): Collection
    {
        $query = DaycareChild::query();

        if ($kelas !== null && $kelas !== '') {
            $query->where('kelas', $kelas);
        }

        return $query
            ->orderBy('kelas')
            ->orderBy('nama_lengkap')
            ->get();
    }

    private function addGuideSheet(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('PANDUAN');

        $writer->addRow(Row::fromValues(['PANDUAN UPDATE DATA DAYCARE']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Jangan ubah ID Sistem. Kolom ini memastikan data diperbarui pada anak yang sama.']));
        $writer->addRow(Row::fromValues(['Sel kosong = data lama tidak diubah (dibiarkan seperti sekarang).']));
        $writer->addRow(Row::fromValues(['Isi hanya biodata yang ingin ditambahkan atau diperbarui.']));
        $writer->addRow(Row::fromValues(['Kelas yang kosong dipertahankan. Kelas yang diisi harus A, B, C, D, atau E.']));
        $writer->addRow(Row::fromValues(['Fitur ini hanya memperbarui anak yang sudah ada dan tidak pernah membuat anak baru.']));
        $writer->addRow(Row::fromValues(['Upload kembali file melalui menu "Update Data Daycare".']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Untuk mengosongkan nilai: gunakan form Edit pada Data Daycare, bukan fitur update massal.']));
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
