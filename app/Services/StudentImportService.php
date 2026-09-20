<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentImportService
{
    public const CONTEXT_PROSPECTIVE = 'prospective';

    public const CONTEXT_ACTIVE = 'active';

    public function __construct(
        private readonly StudentCreationService $studentCreationService,
        private readonly StudentNisConflictService $studentNisConflictService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, new: int, continued: int, errors: int}, has_errors: bool, academic_year: string, context: string}
     */
    public function preview(array $rows, int $academicYearId, string $context): array
    {
        $academicYear = $this->validateContext($academicYearId, $context);
        $classes = SchoolClass::query()->get()->keyBy(fn (SchoolClass $class): string => $this->normalizeClassName($class->name));
        $nisCounts = collect($rows)->pluck('nis')->filter()->countBy();
        $nameClassCounts = collect($rows)
            ->map(fn (array $row): string => $this->normalizeClassName((string) ($row['nama_lengkap'] ?? '')).'|'.$this->normalizeClassName((string) ($row['kelas'] ?? '')))
            ->filter(fn (string $key): bool => $key !== '|')
            ->countBy();

        $previewRows = collect($rows)->map(function (array $row) use ($academicYear, $classes, $nisCounts, $nameClassCounts): array {
            $errors = $this->rowErrors($row);
            $schoolClass = $classes->get($this->normalizeClassName($row['kelas']));
            $warnings = [];

            if (! $schoolClass && $row['kelas'] !== '') {
                $errors[] = "Kelas {$row['kelas']} tidak ditemukan.";
            }

            $nameClassKey = $this->normalizeClassName((string) ($row['nama_lengkap'] ?? '')).'|'.$this->normalizeClassName((string) ($row['kelas'] ?? ''));

            if (($nameClassCounts[$nameClassKey] ?? 0) > 1) {
                $warnings[] = 'Nama dan kelas yang sama ditemukan lebih dari sekali.';
            }

            if (($nisCounts[$row['nis']] ?? 0) > 1) {
                $errors[] = "NIS {$row['nis']} muncul lebih dari sekali dalam file.";
            }

            $status = 'new';
            $statusLabel = 'Siswa Baru';
            $previousSchoolLevels = [];

            if ($schoolClass && is_string($row['nis']) && $row['nis'] !== '') {
                $conflict = $this->studentNisConflictService->findConflict($row['nis'], $academicYear, $schoolClass);

                if ($conflict) {
                    $errors[] = "NIS {$row['nis']} sudah terdaftar pada jenjang {$schoolClass->schoolLevel?->value} di tahun ajaran {$academicYear->year}.";
                } else {
                    $previousSchoolLevels = $this->studentNisConflictService->previousSchoolLevels($row['nis']);

                    if ($previousSchoolLevels !== []) {
                        $statusLabel = 'Siswa Baru Jenjang — NIS pernah digunakan di '.implode(', ', $previousSchoolLevels);
                    }
                }
            }

            if ($errors !== []) {
                $status = 'error';
                $statusLabel = 'Error: '.implode(' ', array_unique($errors));
            }

            return [
                ...$row,
                'class_id' => $schoolClass?->id,
                'status' => $status,
                'status_label' => $statusLabel,
                'warnings' => $warnings,
                'previous_school_levels' => $previousSchoolLevels,
                'target_school_level' => $schoolClass?->schoolLevel?->value,
                'errors' => array_values(array_unique($errors)),
            ];
        })->values();

        $summary = [
            'total' => $previewRows->count(),
            'new' => $previewRows->where('status', 'new')->count(),
            'continued' => $previewRows->where('status', 'continued')->count(),
            'errors' => $previewRows->where('status', 'error')->count(),
        ];

        return [
            'rows' => $previewRows->all(),
            'summary' => $summary,
            'has_errors' => $summary['errors'] > 0,
            'academic_year' => $academicYear->year,
            'context' => $context === self::CONTEXT_PROSPECTIVE ? 'Calon Siswa' : 'Siswa Aktif',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, new: int, continued: int, academic_year: string, context: string, breakdown: array<string, int>}
     */
    public function import(array $rows, int $academicYearId, string $context): array
    {
        return DB::transaction(function () use ($rows, $academicYearId, $context): array {
            $preview = $this->preview($rows, $academicYearId, $context);

            if ($preview['has_errors']) {
                throw ValidationException::withMessages([
                    'import' => 'Import dibatalkan karena masih ada baris bermasalah.',
                ]);
            }

            $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            $new = 0;
            $breakdown = [];

            foreach ($preview['rows'] as $row) {
                $profile = [
                    'nama_lengkap' => $row['nama_lengkap'],
                    'nama_panggilan' => $this->nullableString($row['nama_panggilan']),
                    'class_id' => (int) $row['class_id'],
                    'jenis_kelamin' => $this->nullableString($row['jenis_kelamin']),
                    'tempat_lahir' => $this->nullableString($row['tempat_lahir']),
                    'tanggal_lahir' => $this->nullableString($row['tanggal_lahir']),
                    'entry_date' => $this->nullableString($row['entry_date'] ?? null),
                    'nama_ayah' => $this->nullableString($row['nama_ayah']),
                    'no_telp_ayah' => $this->nullableString($row['no_telp_ayah']),
                    'nama_ibu' => $this->nullableString($row['nama_ibu']),
                    'no_telp_ibu' => $this->nullableString($row['no_telp_ibu']),
                    'alamat' => $this->nullableString($row['alamat']),
                ];

                $student = $this->studentCreationService->create([
                    'nis' => $this->nullableString($row['nis']),
                    ...$profile,
                    'foto' => null,
                    'entry_academic_year_id' => $academicYear->id,
                ]);
                $new++;

                $jenjang = $this->jenjangLabel((int) $student->schoolClass->level);
                $breakdown[$jenjang] = ($breakdown[$jenjang] ?? 0) + 1;
            }

            return [
                'total' => $new,
                'new' => $new,
                'continued' => 0,
                'academic_year' => $academicYear->year,
                'context' => $preview['context'],
                'breakdown' => $breakdown,
            ];
        });
    }

    private function validateContext(int $academicYearId, string $context): AcademicYear
    {
        $validator = Validator::make([
            'academic_year_id' => $academicYearId,
            'context' => $context,
        ], [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'context' => ['required', 'in:'.self::CONTEXT_PROSPECTIVE.','.self::CONTEXT_ACTIVE],
        ]);
        $validator->validate();

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $activeYear = AcademicYear::active();

        if ($context === self::CONTEXT_ACTIVE && ! $academicYear->is_active) {
            throw ValidationException::withMessages([
                'importContext' => 'Siswa Aktif hanya dapat diimpor ke tahun ajaran aktif.',
            ]);
        }

        if ($context === self::CONTEXT_PROSPECTIVE && (! $activeYear || $academicYear->start_date <= $activeYear->start_date)) {
            throw ValidationException::withMessages([
                'importContext' => 'Calon Siswa harus diimpor ke tahun ajaran yang lebih baru dari tahun aktif.',
            ]);
        }

        return $academicYear;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function rowErrors(array $row): array
    {
        $validator = Validator::make($row, [
            'nis' => ['nullable', 'string', 'max:50'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:100'],
            'kelas' => ['required', 'string'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'no_telp_ayah' => ['nullable', 'string', 'max:50'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'no_telp_ibu' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
        ], [
            'required' => ':attribute wajib diisi.',
            'jenis_kelamin.in' => 'Jenis Kelamin harus L atau P.',
            'tanggal_lahir.date_format' => 'Tanggal Lahir harus berformat YYYY-MM-DD.',
            'entry_date.date_format' => 'Tanggal Masuk harus berformat YYYY-MM-DD.',
        ], [
            'nis' => 'NIS',
            'nama_lengkap' => 'Nama Lengkap',
            'nama_panggilan' => 'Nama Panggilan',
            'kelas' => 'Kelas',
            'jenis_kelamin' => 'Jenis Kelamin',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'entry_date' => 'Tanggal Masuk',
            'nama_ayah' => 'Nama Ayah',
            'no_telp_ayah' => 'No Telp Ayah',
            'nama_ibu' => 'Nama Ibu',
            'no_telp_ibu' => 'No Telp Ibu',
            'alamat' => 'Alamat',
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

    private function normalizeClassName(string $name): string
    {
        $name = trim($name);

        return preg_replace('/\s+/', ' ', mb_strtoupper($name)) ?? mb_strtoupper($name);
    }

    private function jenjangLabel(int $level): string
    {
        $schoolLevel = SchoolLevel::fromClassLevel($level);

        return $schoolLevel ? $schoolLevel->value : 'Lainnya';
    }
}
