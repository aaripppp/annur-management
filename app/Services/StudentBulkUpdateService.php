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
        $nisContext = $this->preloadNisConflictContext($rows, $studentMap);

        $previewRows = collect($rows)->map(function (array $row) use ($studentMap, $idCounts, $nisContext): array {
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
                [$changes, $update, $fieldErrors, $fieldWarnings] = $this->compareStudent($student, $provided, $nisContext);

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
     * @param  array{resolved: array<int, string|null>}  $nisContext
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: array<int, string>, 3: array<int, string>}
     */
    private function compareStudent(Student $student, array $provided, array $nisContext = ['resolved' => []]): array
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

        if (! $nisValidationFailed && ($fieldError = $this->nisConflict($student, $provided['nis'] ?? null, $nisContext)) !== null) {
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
     *
     * Hasil untuk baris yang bisa dibtachkan dibaca dari $nisContext (satu
     * putaran query untuk seluruh file). Bila sebuah baris tidak masuk batch
     * yang aman, jalur query tunggal lama tetap dipakai sebagai fallback.
     *
     * @param  array{resolved: array<int, string|null>}  $nisContext
     */
    private function nisConflict(Student $student, mixed $incomingNis, array $nisContext = ['resolved' => []]): ?string
    {
        if ($incomingNis === null || trim((string) $incomingNis) === '') {
            return null;
        }

        $newNis = trim((string) $incomingNis);

        if ($newNis === (string) ($student->nis ?? '') && $student->nis !== null) {
            return null;
        }

        if (array_key_exists($student->id, $nisContext['resolved'])) {
            return $nisContext['resolved'][$student->id];
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

    /**
     * Bangun resolusi konflik NIS untuk seluruh file dengan jumlah query tetap.
     *
     * Hanya baris yang benar-benar mengubah NIS (non-kosong dan berbeda dari
     * nilai lama) yang disertakan, sama seperti guard di dalam nisConflict().
     * Bila seluruh resolusi tidak dapat dibuktikan identik dengan query tunggal
     * (mis. kandidat enrollment seri, atau NIS cocok hanya karena collation),
     * hasil kosong dikembalikan dan jalur query tunggal dipakai untuk semua
     * baris.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Student>  $studentMap
     * @return array{resolved: array<int, string|null>}
     */
    private function preloadNisConflictContext(array $rows, Collection $studentMap): array
    {
        $candidates = [];

        foreach ($rows as $row) {
            $id = $row['id_sistem'] ?? null;

            if ($id === null || trim((string) $id) === '' || ! ctype_digit((string) $id)) {
                continue;
            }

            $student = $studentMap->get((int) $id);

            if ($student === null) {
                continue;
            }

            $incoming = $row['nis'] ?? null;

            if ($incoming === null || trim((string) $incoming) === '') {
                continue;
            }

            $newNis = trim((string) $incoming);

            if ($newNis === (string) ($student->nis ?? '') && $student->nis !== null) {
                continue;
            }

            $candidates[$student->id] = $newNis;
        }

        if ($candidates === []) {
            return ['resolved' => []];
        }

        $activeYear = AcademicYear::active();

        if ($activeYear === null) {
            return ['resolved' => []];
        }

        $represented = $this->representedEnrollmentsFor($activeYear, array_keys($candidates));

        /** @var array<int, string|null> $resolved */
        $resolved = [];
        /** @var array<int, array{academicYear: AcademicYear, nis: array<int, string>, rows: array<int, array{nis: string, level: string}>}> $groups */
        $groups = [];

        foreach ($candidates as $studentId => $incomingNis) {
            $enrollment = $represented[$studentId] ?? null;

            if ($enrollment === 'fallback') {
                return ['resolved' => []];
            }

            if (! $enrollment instanceof StudentAcademicEnrollment || ! $enrollment->schoolClass) {
                $resolved[$studentId] = null;

                continue;
            }

            $level = $enrollment->schoolClass->schoolLevel;

            if ($level === null) {
                $resolved[$studentId] = null;

                continue;
            }

            $academicYear = $enrollment->academicYear;

            if (! $academicYear instanceof AcademicYear) {
                return ['resolved' => []];
            }

            $groups[$academicYear->id]['academicYear'] = $academicYear;
            $groups[$academicYear->id]['nis'][$incomingNis] = $incomingNis;
            $groups[$academicYear->id]['rows'][$studentId] = [
                'nis' => $incomingNis,
                'level' => $level->value,
            ];
        }

        foreach ($groups as $group) {
            $conflicts = $this->studentNisConflictService->findConflicts(
                array_values($group['nis']),
                $group['academicYear'],
                null,
            );

            if (! $this->nisBatchIsExact(array_values($group['nis']), $conflicts)) {
                return ['resolved' => []];
            }

            foreach ($group['rows'] as $studentId => $target) {
                $key = $target['nis'].'|'.$target['level'];

                $resolved[$studentId] = $conflicts->has($key)
                    ? "NIS {$target['nis']} sudah terdaftar pada jenjang {$target['level']} di tahun ajaran {$group['academicYear']->year}."
                    : null;
            }
        }

        return ['resolved' => $resolved];
    }

    /**
     * Muat enrollment "terwakili" untuk banyak siswa sekaligus.
     *
     * Predikat, status, dan urutan identik dengan representedEnrollment():
     * the active enrollment of the active academic year, else the matched
     * enrollment whose academic year starts earliest. Bila hasil tidak
     * deterministik (dua kandidat dengan start_date sama), nilai "fallback"
     * ditandai agar query tunggal dipakai.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, StudentAcademicEnrollment|string|null>
     */
    private function representedEnrollmentsFor(AcademicYear $activeYear, array $studentIds): array
    {
        $enrollments = StudentAcademicEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where(function ($query) use ($activeYear): void {
                $query
                    ->where(fn ($q) => $q
                        ->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active'))
                    ->orWhereHas('academicYear', function ($q) use ($activeYear): void {
                        $q->whereDate('start_date', '>', $activeYear->start_date);
                    });
            })
            ->with(['schoolClass', 'academicYear'])
            ->get()
            ->groupBy('student_id');

        $result = [];

        foreach ($studentIds as $studentId) {
            $list = $enrollments->get($studentId);

            if ($list === null || $list->isEmpty()) {
                $result[$studentId] = null;

                continue;
            }

            if ($list->count() === 1) {
                $result[$studentId] = $list->first();

                continue;
            }

            $earliestStart = null;

            foreach ($list as $enrollment) {
                $startDate = $enrollment->academicYear?->start_date;

                if ($startDate === null) {
                    $result[$studentId] = 'fallback';

                    continue 2;
                }

                $timestamp = $startDate->getTimestamp();

                if ($earliestStart === null || $timestamp < $earliestStart) {
                    $earliestStart = $timestamp;
                }
            }

            $earliest = $list->filter(
                fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academicYear?->start_date->getTimestamp() === $earliestStart
            );

            $result[$studentId] = $earliest->count() === 1 ? $earliest->first() : 'fallback';
        }

        return $result;
    }

    /**
     * Konfirmasi hasil batch dapat dipetakan kembali secara byte-identik.
     *
     * Database produksi (MySQL utf8mb4_unicode_ci) mencocokkan NIS tanpa peduli
     * besar-kecil huruf atau aksen, sehingga sebuah baris bisa cocok walau
     * string-nya tidak identik. Pemetaan kunci PHP tidak dapat menirunya.
     * Batch hanya dipercaya bila setiap NIS hasil cocok byte-identik dengan
     * NIS yang diminta dan tidak ada dua NIS berbeda yang dapat bertumbukan.
     *
     * @param  array<int, string>  $nisList
     * @param  Collection<int, StudentAcademicEnrollment>  $map
     */
    private function nisBatchIsExact(array $nisList, Collection $map): bool
    {
        $requested = [];
        $normalized = [];

        foreach ($nisList as $nis) {
            $nis = trim((string) $nis);

            if (! in_array($nis, $requested, true)) {
                $requested[] = $nis;
            }

            $key = $this->nisCollationKey($nis);

            if (array_key_exists($key, $normalized) && $normalized[$key] !== $nis) {
                return false;
            }

            $normalized[$key] = $nis;
        }

        foreach ($map->keys() as $mapKey) {
            $mapKey = (string) $mapKey;
            $separator = strrpos($mapKey, '|');
            $databaseNis = $separator === false ? $mapKey : substr($mapKey, 0, $separator);

            if (! in_array($databaseNis, $requested, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bentuk kunci sedekat mungkin dengan collation utf8mb4_unicode_ci:
     * huruf kecil tanpa penanda aksen.
     */
    private function nisCollationKey(string $value): string
    {
        $value = trim($value);

        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);

            if ($decomposed !== false) {
                $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
            }
        }

        return mb_strtolower($value);
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
