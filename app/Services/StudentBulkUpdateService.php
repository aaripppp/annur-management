<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk update biodata siswa yang sudah ada.
 *
 * Kontrak penting:
 * - Pencocokan baris HANYA melalui "ID Sistem" (Student primary key).
 * - Tidak ada pencocokan nama / nama+kelas / NIS.
 * - Sel kosong = "pertahankan nilai lama", BUKAN mengosongkan database.
 * - Tidak membuat siswa baru.
 * - Tidak mengubah kelas/enrollment/tagihan.
 */
class StudentBulkUpdateService
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
        'nis',
        'nama_panggilan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
        'alamat',
    ];

    /** @var array<string, array{label: string, max?: int, in?: array<int, string>}> */
    private const FIELD_SPECS = [
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'max' => 255],
        'nis' => ['label' => 'NIS', 'max' => 50],
        'nama_panggilan' => ['label' => 'Nama Panggilan', 'max' => 100],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'in' => ['L', 'P']],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'max' => 255],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir'],
        'nama_ayah' => ['label' => 'Nama Ayah', 'max' => 255],
        'no_telp_ayah' => ['label' => 'No Telp Ayah', 'max' => 50],
        'nama_ibu' => ['label' => 'Nama Ibu', 'max' => 255],
        'no_telp_ibu' => ['label' => 'No Telp Ibu', 'max' => 50],
        'alamat' => ['label' => 'Alamat'],
    ];

    public function __construct(private readonly StudentNisConflictService $studentNisConflictService) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, unchanged: int, blocked: int}}
     */
    public function preview(array $rows): array
    {
        $studentMap = $this->fetchStudents($rows);
        $idCounts = $this->idCounts($rows);

        $previewRows = collect($rows)->map(function (array $row) use ($studentMap, $idCounts): array {
            $id = $row['id_sistem'] ?? null;
            $errors = [];
            $warnings = [];
            $student = null;

            if ($id === null || trim((string) $id) === '') {
                $errors[] = 'ID Sistem tidak valid atau kosong.';
            } elseif (! ctype_digit((string) $id)) {
                $errors[] = 'ID Sistem harus berupa angka.';
            } else {
                $student = $studentMap->get((int) $id);

                if ($student === null) {
                    $errors[] = 'ID Sistem tidak ditemukan.';
                } elseif (($idCounts[(int) $id] ?? 0) > 1) {
                    $errors[] = "ID Sistem {$id} muncul lebih dari satu kali di file.";
                }
            }

            $changes = [];
            $update = [];

            if ($student !== null && $errors === []) {
                $provided = $this->providedValueMap($row);
                [$changes, $update, $fieldErrors, $fieldWarnings] = $this->compareStudent($student, $provided);

                $errors = array_merge($errors, $fieldErrors);
                $warnings = array_merge($warnings, $fieldWarnings);

                if ($this->hasClassChange($student, $provided)) {
                    $warnings[] = 'Perubahan kelas diabaikan. Gunakan proses perpindahan/kenaikan kelas.';
                }
            }

            $status = $errors !== []
                ? self::STATUS_BLOCKED
                : ($changes === [] ? self::STATUS_UNCHANGED : self::STATUS_READY);

            return [
                'row_number' => (int) $row['row_number'],
                'id_sistem' => $id === null ? '' : (string) $id,
                'student_id' => $student?->id,
                'nama_lengkap' => $student?->nama_lengkap ?? '',
                'kelas' => $student?->schoolClass?->name ?? '',
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
                if ($row['status'] !== self::STATUS_READY || $row['student_id'] === null || $row['update'] === []) {
                    continue;
                }

                Student::query()->whereKey($row['student_id'])->update($row['update']);
                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Muat siswa sekali untuk seluruh file (hindari N+1).
     *
     * @return Collection<int, Student>
     */
    private function fetchStudents(array $rows): Collection
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

        return Student::query()
            ->with('schoolClass')
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

        if (isset($row['kelas']) && is_string($row['kelas']) && trim($row['kelas']) !== '') {
            $provided['kelas'] = trim($row['kelas']);
        }

        return $provided;
    }

    /**
     * @param  array<string, mixed>  $provided
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<int, string>, 3: array<int, string>}
     */
    private function compareStudent(Student $student, array $provided): array
    {
        $changes = [];
        $update = [];
        $errors = [];
        $warnings = [];
        $nisValidationFailed = false;

        foreach ($provided as $field => $incoming) {
            if (! in_array($field, self::UPDATEABLE_FIELDS, true)) {
                continue;
            }

            $spec = self::FIELD_SPECS[$field];
            $current = $this->currentValue($student, $field);

            $normalized = $this->normalizeField($field, $incoming);

            $fieldError = $this->validateField($field, $normalized);
            if ($fieldError !== null) {
                if ($field === 'nis') {
                    $nisValidationFailed = true;
                }

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

        if (! $nisValidationFailed && ($fieldError = $this->nisConflict($student, $provided['nis'] ?? null)) !== null) {
            $errors[] = $fieldError;
        }

        return [$changes, $update, $errors, $warnings];
    }

    /**
     * @param  array<string, mixed>  $provided
     */
    private function hasClassChange(Student $student, array $provided): bool
    {
        $kelas = $provided['kelas'] ?? null;

        if ($kelas === null || trim((string) $kelas) === '') {
            return false;
        }

        return strtolower((string) $kelas) !== strtolower((string) ($student->schoolClass?->name ?? ''));
    }

    private function currentValue(Student $student, string $field): mixed
    {
        return match ($field) {
            'jenis_kelamin' => strtoupper((string) ($student->jenis_kelamin ?? '')),
            'tanggal_lahir' => $student->tanggal_lahir?->format('Y-m-d') ?? '',
            default => (string) ($student->{$field} ?? ''),
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

        if ($field === 'tanggal_lahir') {
            $date = \DateTime::createFromFormat('Y-m-d', $value);

            if (! $date || $date->format('Y-m-d') !== $value) {
                return 'Tanggal Lahir harus berformat YYYY-MM-DD.';
            }
        }

        return null;
    }

    /**
     * Saat NIS baru disediakan (bukan kosong) dan berbeda dari nilai lama,
     * jalankan deteksi konflik NIS sesuai kebutuhan jenjang/tahun ajaran.
     */
    private function nisConflict(Student $student, mixed $incomingNis): ?string
    {
        if ($incomingNis === null || trim((string) $incomingNis) === '') {
            return null;
        }

        $newNis = trim((string) $incomingNis);

        if ($newNis === (string) ($student->nis ?? '') && $student->nis !== null) {
            return null;
        }

        $enrollment = $this->representedEnrollment($student);

        if (! $enrollment || ! $enrollment->schoolClass) {
            return null;
        }

        $academicYear = AcademicYear::query()->find($enrollment->academic_year_id);

        if ($academicYear === null) {
            return null;
        }

        $conflict = $this->studentNisConflictService->findConflict(
            $newNis,
            $academicYear,
            $enrollment->schoolClass,
            $student->id,
        );

        if ($conflict === null) {
            return null;
        }

        return "NIS {$newNis} sudah terdaftar pada jenjang {$enrollment->schoolClass->schoolLevel?->value} di tahun ajaran {$academicYear->year}.";
    }

    private function representedEnrollment(Student $student): ?StudentAcademicEnrollment
    {
        $activeYear = AcademicYear::active();

        if ($activeYear === null) {
            return null;
        }

        return $student->enrollments()
            ->with('schoolClass')
            ->where(function ($query) use ($activeYear): void {
                $query
                    ->where(fn ($q) => $q
                        ->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active'))
                    ->orWhereHas('academicYear', function ($q) use ($activeYear): void {
                        $q->whereDate('start_date', '>', $activeYear->start_date);
                    });
            })
            ->orderBy(AcademicYear::query()
                ->select('start_date')
                ->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id')
                ->limit(1))
            ->first();
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
