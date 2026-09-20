<?php

namespace App\Services;

use App\Enums\ProspectiveStudentStatus;
use App\Models\ProspectiveStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk update biodata calon siswa yang sudah ada.
 *
 * Kontrak penting:
 * - Pencocokan baris HANYA melalui "ID Sistem" (ProspectiveStudent primary key).
 * - Tidak ada pencocokan nama / no. pendaftaran / kelas / telp.
 * - Sel kosong = "pertahankan nilai lama", BUKAN mengosongkan database.
 * - Hanya profil (biodata) yang diubah. Tidak pernah mengubah kelas tujuan,
 *   tahun ajaran, no. pendaftaran, status, atau data konversi.
 * - Calon siswa yang sudah dikonversi atau dibatalkan tidak dapat diubah.
 * - Tidak membuat calon siswa baru dan tidak memicu tagihan/pembayaran.
 */
class ProspectiveStudentBulkUpdateService
{
    public const STATUS_READY = 'ready';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * Field biodata yang boleh diperbarui lewat bulk update.
     *
     * @var array<int, string>
     */
    public const UPDATEABLE_FIELDS = [
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'nama_orang_tua',
        'no_telp_orang_tua',
        'alamat',
        'notes',
    ];

    /** @var array<string, array{label: string, max?: int, in?: array<int, string>}> */
    private const FIELD_SPECS = [
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'max' => 255],
        'nama_panggilan' => ['label' => 'Nama Panggilan', 'max' => 100],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'in' => ['L', 'P']],
        'nama_orang_tua' => ['label' => 'Nama Orang Tua', 'max' => 255],
        'no_telp_orang_tua' => ['label' => 'No Telp Orang Tua', 'max' => 50],
        'alamat' => ['label' => 'Alamat'],
        'notes' => ['label' => 'Catatan'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, unchanged: int, blocked: int}}
     */
    public function preview(array $rows): array
    {
        $prospectMap = $this->fetchProspects($rows);
        $idCounts = $this->idCounts($rows);

        $previewRows = collect($rows)->map(function (array $row) use ($prospectMap, $idCounts): array {
            $id = $row['id_sistem'] ?? null;
            $errors = [];
            $warnings = [];
            $prospect = null;

            if ($id === null || trim((string) $id) === '') {
                $errors[] = 'ID Sistem tidak valid atau kosong.';
            } elseif (! ctype_digit((string) $id)) {
                $errors[] = 'ID Sistem harus berupa angka.';
            } else {
                $prospect = $prospectMap->get((int) $id);

                if ($prospect === null) {
                    $errors[] = 'ID Sistem tidak ditemukan.';
                } elseif (($idCounts[(int) $id] ?? 0) > 1) {
                    $errors[] = "ID Sistem {$id} muncul lebih dari satu kali di file.";
                } elseif ($prospect->status === ProspectiveStudentStatus::Converted) {
                    $errors[] = 'Calon siswa yang sudah dikonversi tidak dapat diubah melalui bulk update.';
                } elseif ($prospect->status === ProspectiveStudentStatus::Cancelled) {
                    $errors[] = 'Calon siswa yang dibatalkan tidak dapat diubah melalui bulk update.';
                }
            }

            $changes = [];
            $update = [];

            if ($prospect !== null && $errors === []) {
                $provided = $this->providedValueMap($row);
                [$changes, $update, $fieldErrors, $fieldWarnings] = $this->compareProspect($prospect, $provided);

                $errors = array_merge($errors, $fieldErrors);
                $warnings = array_merge($warnings, $fieldWarnings);
                $warnings = array_merge($warnings, $this->readOnlyWarnings($prospect, $row));
            }

            $status = $errors !== []
                ? self::STATUS_BLOCKED
                : ($changes === [] ? self::STATUS_UNCHANGED : self::STATUS_READY);

            return [
                'row_number' => (int) $row['row_number'],
                'id_sistem' => $id === null ? '' : (string) $id,
                'prospective_student_id' => $prospect?->id,
                'no_pendaftaran' => $prospect?->registration_number ?? ($row['no_pendaftaran'] ?? ''),
                'nama_lengkap' => $prospect?->nama_lengkap ?? '',
                'kelas' => $prospect?->schoolClass?->name ?? ($row['kelas'] ?? ''),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'errors' => array_values(array_unique($errors)),
                'warnings' => array_values(array_unique($warnings)),
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
     * Terapkan hanya baris berstatus READY.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function apply(array $rows): int
    {
        $preview = $this->preview($rows);

        return DB::transaction(function () use ($preview): int {
            $updated = 0;

            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== self::STATUS_READY || $row['prospective_student_id'] === null || $row['update'] === []) {
                    continue;
                }

                ProspectiveStudent::query()->whereKey($row['prospective_student_id'])->update($row['update']);
                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Muat calon siswa sekali untuk seluruh file (hindari N+1).
     *
     * @return Collection<int, ProspectiveStudent>
     */
    private function fetchProspects(array $rows): Collection
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

        return ProspectiveStudent::query()
            ->with(['academicYear', 'schoolClass'])
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
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<int, string>, 3: array<int, string>}
     */
    private function compareProspect(ProspectiveStudent $prospect, array $provided): array
    {
        $changes = [];
        $update = [];
        $errors = [];
        $warnings = [];

        foreach ($provided as $field => $incoming) {
            if (! in_array($field, self::UPDATEABLE_FIELDS, true)) {
                continue;
            }

            $spec = self::FIELD_SPECS[$field];
            $current = $this->currentValue($prospect, $field);
            $normalized = $this->normalizeField($field, $incoming);

            $fieldError = $this->validateField($field, $normalized);
            if ($fieldError !== null) {
                $errors[] = $fieldError;

                continue;
            }

            $oldDisplay = $this->displayValue($current);
            $newDisplay = $this->displayValue($normalized);

            if ($normalized === $current) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $spec['label'],
                'old' => $current,
                'new' => $normalized,
                'old_display' => $oldDisplay,
                'new_display' => $newDisplay,
            ];
            $update[$field] = $normalized;
        }

        return [$changes, $update, $errors, $warnings];
    }

    /**
     * Deteksi perubahan pada kolom referensi read-only (No. Pendaftaran,
     * Tahun Ajaran Tujuan, Kelas Tujuan) → peringatan saja, tidak terapkan.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function readOnlyWarnings(ProspectiveStudent $prospect, array $row): array
    {
        $warnings = [];

        if (isset($row['no_pendaftaran'])
            && is_string($row['no_pendaftaran'])
            && trim($row['no_pendaftaran']) !== ''
            && trim($row['no_pendaftaran']) !== $prospect->registration_number) {
            $warnings[] = 'Perubahan No. Pendaftaran diabaikan.';
        }

        if (isset($row['tahun_ajaran'])
            && is_string($row['tahun_ajaran'])
            && trim($row['tahun_ajaran']) !== ''
            && trim($row['tahun_ajaran']) !== ($prospect->academicYear?->year ?? '')) {
            $warnings[] = 'Perubahan Tahun Ajaran Tujuan diabaikan.';
        }

        if (isset($row['kelas'])
            && is_string($row['kelas'])
            && trim($row['kelas']) !== ''
            && strtolower(trim($row['kelas'])) !== strtolower((string) ($prospect->schoolClass?->name ?? ''))) {
            $warnings[] = 'Perubahan Kelas Tujuan diabaikan. Gunakan form Edit Calon Siswa.';
        }

        return $warnings;
    }

    private function currentValue(ProspectiveStudent $prospect, string $field): string
    {
        return match ($field) {
            'jenis_kelamin' => strtoupper((string) ($prospect->jenis_kelamin ?? '')),
            default => (string) ($prospect->{$field} ?? ''),
        };
    }

    private function normalizeField(string $field, mixed $value): string
    {
        return $field === 'jenis_kelamin' ? strtoupper(trim((string) $value)) : trim((string) $value);
    }

    private function validateField(string $field, string $value): ?string
    {
        $spec = self::FIELD_SPECS[$field];

        if (isset($spec['in']) && ! in_array($value, $spec['in'], true)) {
            return "Nilai {$spec['label']} tidak valid (harus L atau P).";
        }

        if (isset($spec['max']) && mb_strlen($value) > $spec['max']) {
            return "{$spec['label']} terlalu panjang (maksimal {$spec['max']} karakter).";
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
