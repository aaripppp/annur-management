<?php

namespace App\Services;

use App\Models\DaycareChild;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DaycareImportService
{
    /** @var array<int, string> */
    public const VALID_CLASSES = ['A', 'B', 'C', 'D', 'E'];

    public const STATUS_NEW = 'new';

    public const STATUS_ERROR = 'error';

    /**
     * Kontrak penting:
     * - Fitur ini hanya MEMBUAT anak daycare baru. Tidak pernah memperbarui
     *   atau menghapus data yang sudah ada.
     * - Nama + kelas yang sama dengan anak yang sudah ada hanya memunculkan
     *   peringatan (duplikat perlu dicek admin), tidak pernah menimpa data lama.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, new: int, warning: int, error: int}, has_errors: bool}
     */
    public function preview(array $rows): array
    {
        $existingByNameClass = DaycareChild::query()
            ->get()
            ->mapWithKeys(fn (DaycareChild $child): array => [$this->normalizeNameClass($child->nama_lengkap, $child->kelas) => $child->id]);

        $previewRows = collect($rows)->map(function (array $row) use ($existingByNameClass): array {
            $errors = [];
            $warnings = [];

            $namaLengkap = trim((string) ($row['nama_lengkap'] ?? ''));
            $kelas = strtoupper(trim((string) ($row['kelas'] ?? '')));

            if ($namaLengkap === '') {
                $errors[] = 'Nama Lengkap wajib diisi.';
            }

            if ($kelas === '') {
                $errors[] = 'Kelas wajib diisi.';
            } elseif (! in_array($kelas, self::VALID_CLASSES, true)) {
                $errors[] = "Kelas {$kelas} tidak valid.";
            }

            $errors = [...$errors, ...$this->fieldErrors($row)];

            if ($namaLengkap !== '' && $kelas !== '') {
                $nameClassKey = $this->normalizeNameClass($namaLengkap, $kelas);

                if ($existingByNameClass->has($nameClassKey)) {
                    $warnings[] = 'Nama dan kelas yang sama sudah ada.';
                }
            }

            $status = $errors === [] ? self::STATUS_NEW : self::STATUS_ERROR;

            return [
                ...$row,
                'nama_lengkap' => $namaLengkap,
                'kelas' => $kelas,
                'status' => $status,
                'status_label' => $status === self::STATUS_NEW ? 'NEW' : 'ERROR',
                'warnings' => $warnings,
                'errors' => array_values(array_unique($errors)),
            ];
        })->values();

        $summary = [
            'total' => $previewRows->count(),
            'new' => $previewRows->where('status', self::STATUS_NEW)->count(),
            'warning' => $previewRows->filter(fn (array $row): bool => $row['warnings'] !== [])->count(),
            'error' => $previewRows->where('status', self::STATUS_ERROR)->count(),
        ];

        return [
            'rows' => $previewRows->all(),
            'summary' => $summary,
            'has_errors' => $summary['error'] > 0,
        ];
    }

    /**
     * Semua baris divalidasi ulang dan seluruh file diterapkan dalam satu
     * transaksi. Jika masih ada baris bermasalah, tidak ada baris yang diimpor.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, created: int}
     */
    public function import(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $preview = $this->preview($rows);

            if ($preview['has_errors']) {
                throw ValidationException::withMessages([
                    'import' => 'Import dibatalkan karena masih ada baris bermasalah.',
                ]);
            }

            $created = 0;

            foreach ($preview['rows'] as $row) {
                $this->applyCreate($row);
                $created++;
            }

            return [
                'total' => $created,
                'created' => $created,
            ];
        });
    }

    /** @param  array<string, mixed>  $row */
    private function applyCreate(array $row): void
    {
        DaycareChild::query()->create([
            'nama_lengkap' => $this->nullableString($row['nama_lengkap']),
            'kelas' => strtoupper($this->nullableString($row['kelas']) ?? ''),
            'nama_panggilan' => $this->nullableString($row['nama_panggilan']),
            'tempat_lahir' => $this->nullableString($row['tempat_lahir']),
            'tanggal_lahir' => $this->nullableString($row['tanggal_lahir']),
            'jenis_kelamin' => $this->optionalUpperString($row['jenis_kelamin']),
            'alamat' => $this->nullableString($row['alamat']),
            'nama_ayah' => $this->nullableString($row['nama_ayah']),
            'no_telp_ayah' => $this->nullableString($row['no_telp_ayah']),
            'nama_ibu' => $this->nullableString($row['nama_ibu']),
            'no_telp_ibu' => $this->nullableString($row['no_telp_ibu']),
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function fieldErrors(array $row): array
    {
        $validator = Validator::make($row, [
            'nama_panggilan' => ['nullable', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'alamat' => ['nullable', 'string'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'no_telp_ayah' => ['nullable', 'string', 'max:50'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'no_telp_ibu' => ['nullable', 'string', 'max:50'],
        ], [
            'tanggal_lahir.date_format' => 'Tanggal Lahir harus berformat YYYY-MM-DD.',
            'jenis_kelamin.in' => 'Jenis Kelamin harus L atau P.',
        ], [
            'nama_panggilan' => 'Nama Panggilan',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'jenis_kelamin' => 'Jenis Kelamin',
            'alamat' => 'Alamat',
            'nama_ayah' => 'Nama Ayah',
            'no_telp_ayah' => 'No Telp Ayah',
            'nama_ibu' => 'Nama Ibu',
            'no_telp_ibu' => 'No Telp Ibu',
        ]);

        return $validator->errors()->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalUpperString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : strtoupper($value);
    }

    private function normalizeNameClass(string $name, string $class): string
    {
        $name = preg_replace('/\s+/', ' ', mb_strtoupper(trim($name))) ?? mb_strtoupper(trim($name));

        return $name.'|'.strtoupper(trim($class));
    }
}
