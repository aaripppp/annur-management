<?php

namespace App\Services;

use App\Models\DaycareChild;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Update massal biodata anak daycare yang sudah ada.
 *
 * Kontrak penting:
 * - Pencocokan baris HANYA melalui "ID Sistem" (DaycareChild primary key).
 * - Tidak ada pencocokan nama / nama+kelas.
 * - Sel kosong = "pertahankan nilai lama", BUKAN mengosongkan database.
 * - Tidak membuat anak daycare baru.
 */
class DaycareBulkUpdateService
{
    public const STATUS_READY = 'ready';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * Field biodata yang boleh diperbarui lewat update massal.
     *
     * @var array<int, string>
     */
    public const UPDATEABLE_FIELDS = [
        'nama_lengkap',
        'kelas',
        'nama_panggilan',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
    ];

    /** @var array<string, array{label: string, max?: int, in?: array<int, string>}> */
    private const FIELD_SPECS = [
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'max' => 255],
        'kelas' => ['label' => 'Kelas', 'in' => ['A', 'B', 'C', 'D', 'E']],
        'nama_panggilan' => ['label' => 'Nama Panggilan', 'max' => 100],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'max' => 255],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir'],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'in' => ['L', 'P']],
        'alamat' => ['label' => 'Alamat'],
        'nama_ayah' => ['label' => 'Nama Ayah', 'max' => 255],
        'no_telp_ayah' => ['label' => 'No Telp Ayah', 'max' => 50],
        'nama_ibu' => ['label' => 'Nama Ibu', 'max' => 255],
        'no_telp_ibu' => ['label' => 'No Telp Ibu', 'max' => 50],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, unchanged: int, blocked: int}}
     */
    public function preview(array $rows): array
    {
        $childMap = $this->fetchChildren($rows);
        $idCounts = $this->idCounts($rows);

        $previewRows = collect($rows)->map(function (array $row) use ($childMap, $idCounts): array {
            $id = $row['id_sistem'] ?? null;
            $errors = [];
            $warnings = [];
            $child = null;

            if ($id === null || trim((string) $id) === '') {
                $errors[] = 'ID Sistem tidak valid atau kosong.';
            } elseif (! ctype_digit((string) $id)) {
                $errors[] = 'ID Sistem harus berupa angka.';
            } else {
                $child = $childMap->get((int) $id);

                if ($child === null) {
                    $errors[] = 'ID Sistem tidak ditemukan.';
                } elseif (($idCounts[(int) $id] ?? 0) > 1) {
                    $errors[] = "ID Sistem {$id} muncul lebih dari satu kali di file.";
                }
            }

            $changes = [];
            $update = [];

            if ($child !== null && $errors === []) {
                $provided = $this->providedValueMap($row);
                [$changes, $update, $fieldErrors] = $this->compareChild($child, $provided);
                $errors = array_merge($errors, $fieldErrors);
            }

            $status = $errors !== []
                ? self::STATUS_BLOCKED
                : ($changes === [] ? self::STATUS_UNCHANGED : self::STATUS_READY);

            return [
                'row_number' => (int) $row['row_number'],
                'id_sistem' => $id === null ? '' : (string) $id,
                'daycare_child_id' => $child?->id,
                'nama_lengkap' => $child?->nama_lengkap ?? '',
                'kelas' => $child?->kelas ?? '',
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'errors' => array_values(array_unique($errors)),
                'warnings' => array_values(array_unique(array_merge($warnings, []))),
                'changes' => $changes,
                'update' => $update,
            ];
        })->values();

        return [
            'rows' => $previewRows->all(),
            'summary' => [
                'total' => $previewRows->count(),
                'ready' => $previewRows->where('status', self::STATUS_READY)->count(),
                'unchanged' => $previewRows->where('status', self::STATUS_UNCHANGED)->count(),
                'blocked' => $previewRows->where('status', self::STATUS_BLOCKED)->count(),
            ],
        ];
    }

    /**
     * Terapkan hanya baris berstatus READY. Tidak pernah membuat anak baru.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function apply(array $rows): int
    {
        $preview = $this->preview($rows);

        return DB::transaction(function () use ($preview): int {
            $updated = 0;

            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== self::STATUS_READY || $row['daycare_child_id'] === null || $row['update'] === []) {
                    continue;
                }

                DaycareChild::query()->whereKey($row['daycare_child_id'])->update($row['update']);
                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Muat anak sekali untuk seluruh file (hindari N+1).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, DaycareChild>
     */
    private function fetchChildren(array $rows): Collection
    {
        $ids = collect($rows)
            ->pluck('id_sistem')
            ->filter(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '' && ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DaycareChild::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, int> */
    private function idCounts(array $rows): Collection
    {
        return collect($rows)
            ->pluck('id_sistem')
            ->filter(fn (mixed $value): bool => $value !== null && ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->countBy();
    }

    /**
     * Sel kosong dianggap "tidak disertakan" (pertahankan nilai lama).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function providedValueMap(array $row): array
    {
        $provided = [];

        foreach (self::UPDATEABLE_FIELDS as $field) {
            $raw = $row[$field] ?? null;

            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                continue;
            }

            $provided[$field] = is_string($raw) ? trim($raw) : $raw;
        }

        return $provided;
    }

    /**
     * @param  array<string, mixed>  $provided
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<int, string>}
     */
    private function compareChild(DaycareChild $child, array $provided): array
    {
        $changes = [];
        $update = [];
        $errors = [];

        foreach ($provided as $field => $incoming) {
            if (! in_array($field, self::UPDATEABLE_FIELDS, true)) {
                continue;
            }

            $spec = self::FIELD_SPECS[$field];
            $current = $this->currentValue($child, $field);
            $normalized = $this->normalizeField($field, $incoming);

            $fieldError = $this->validateField($field, $normalized);
            if ($fieldError !== null) {
                $errors[] = $fieldError;

                continue;
            }

            if ($normalized === $current) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $spec['label'],
                'old' => $current,
                'new' => $normalized,
                'old_display' => $this->displayValue($current),
                'new_display' => $this->displayValue($normalized),
            ];
            $update[$field] = $normalized;
        }

        return [$changes, $update, $errors];
    }

    private function currentValue(DaycareChild $child, string $field): ?string
    {
        return match ($field) {
            'jenis_kelamin' => strtoupper((string) ($child->jenis_kelamin ?? '')),
            'tanggal_lahir' => $child->tanggal_lahir?->format('Y-m-d') ?? '',
            default => ($child->{$field} ?? '') === null ? null : (string) $child->{$field},
        };
    }

    private function normalizeField(string $field, mixed $value): string
    {
        $trimmed = trim((string) $value);

        return in_array($field, ['kelas', 'jenis_kelamin'], true) ? strtoupper($trimmed) : $trimmed;
    }

    private function validateField(string $field, string $value): ?string
    {
        $spec = self::FIELD_SPECS[$field];

        if (isset($spec['in']) && ! in_array($value, $spec['in'], true)) {
            $allowed = implode(', ', $spec['in']);

            return "Nilai {$spec['label']} tidak valid (harus {$allowed}).";
        }

        if (isset($spec['max']) && mb_strlen($value) > $spec['max']) {
            return "{$spec['label']} terlalu panjang (maksimal {$spec['max']} karakter).";
        }

        if ($field === 'tanggal_lahir') {
            $date = \DateTime::createFromFormat('Y-m-d', $value);

            if (! $date || $date->format('Y-m-d') !== $value) {
                return 'Tanggal Lahir harus berformat YYYY-MM-DD.';
            }
        }

        return null;
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Belum diisi';
        }

        return (string) $value;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_READY => 'SIAP UPDATE',
            self::STATUS_UNCHANGED => 'TANPA PERUBAHAN',
            default => 'GAGAL',
        };
    }
}
