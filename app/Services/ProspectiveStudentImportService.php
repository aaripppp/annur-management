<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProspectiveStudentImportService
{
    public function __construct(
        private readonly ProspectiveStudentRegistrationNumberGenerator $registrationNumberGenerator,
        private readonly ProspectiveStudentBillGenerationService $billGenerationService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, new: int, errors: int}, has_errors: bool, academic_year: string}
     */
    public function preview(array $rows, int $academicYearId): array
    {
        $academicYear = $this->validateTargetYear($academicYearId);
        $classes = SchoolClass::query()->get()->keyBy(fn (SchoolClass $class): string => $this->normalizeClassName($class->name));
        $nameClassCounts = collect($rows)
            ->map(fn (array $row): string => $this->normalizeClassName((string) ($row['nama_lengkap'] ?? '')).'|'.$this->normalizeClassName((string) ($row['kelas'] ?? '')))
            ->filter(fn (string $key): bool => $key !== '|')
            ->countBy();

        $previewRows = collect($rows)->map(function (array $row) use ($classes, $nameClassCounts): array {
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

            $status = $errors !== [] ? 'error' : 'new';
            $statusLabel = $errors !== [] ? 'Bermasalah' : 'Calon Siswa Baru';

            return [
                ...$row,
                'class_id' => $schoolClass?->id,
                'status' => $status,
                'status_label' => $statusLabel,
                'warnings' => $warnings,
                'target_school_level' => $schoolClass?->schoolLevel?->value,
                'errors' => array_values(array_unique($errors)),
            ];
        })->values();

        $summary = [
            'total' => $previewRows->count(),
            'new' => $previewRows->where('status', 'new')->count(),
            'errors' => $previewRows->where('status', 'error')->count(),
        ];

        return [
            'rows' => $previewRows->all(),
            'summary' => $summary,
            'has_errors' => $summary['errors'] > 0,
            'academic_year' => $academicYear->year,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, new: int, academic_year: string, breakdown: array<string, int>}
     */
    public function import(array $rows, int $academicYearId): array
    {
        return DB::transaction(function () use ($rows, $academicYearId): array {
            $preview = $this->preview($rows, $academicYearId);

            if ($preview['has_errors']) {
                throw ValidationException::withMessages([
                    'import' => 'Import dibatalkan karena masih ada baris bermasalah.',
                ]);
            }

            $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            $startYear = (int) substr($academicYear->year, 0, 4);
            $new = 0;
            $breakdown = [];

            foreach ($preview['rows'] as $row) {
                $prospect = ProspectiveStudent::query()->create([
                    'registration_number' => $this->registrationNumberGenerator->next($startYear),
                    'nama_lengkap' => $row['nama_lengkap'],
                    'nama_panggilan' => $this->nullableString($row['nama_panggilan']),
                    'jenis_kelamin' => $this->nullableString($row['jenis_kelamin']),
                    'nama_orang_tua' => $this->nullableString($row['nama_orang_tua']),
                    'no_telp_orang_tua' => $this->nullableString($row['no_telp_orang_tua']),
                    'alamat' => $this->nullableString($row['alamat']),
                    'notes' => $this->nullableString($row['notes']),
                    'academic_year_id' => $academicYear->id,
                    'school_class_id' => (int) $row['class_id'],
                    'status' => ProspectiveStudent::STATUS_REGISTERED,
                    'created_by' => auth()->id(),
                ]);

                $this->billGenerationService->generateFor($prospect);
                $new++;

                $jenjang = SchoolLevel::tryFrom((string) ($row['target_school_level'] ?? ''))?->value ?? 'Lainnya';
                $breakdown[$jenjang] = ($breakdown[$jenjang] ?? 0) + 1;
            }

            return [
                'total' => $new,
                'new' => $new,
                'academic_year' => $academicYear->year,
                'breakdown' => $breakdown,
            ];
        });
    }

    private function validateTargetYear(int $academicYearId): AcademicYear
    {
        $validator = Validator::make([
            'academic_year_id' => $academicYearId,
        ], [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
        ]);
        $validator->validate();

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $activeYear = AcademicYear::active();

        if (! $activeYear || $academicYear->start_date->lte($activeYear->start_date)) {
            throw ValidationException::withMessages([
                'academicYearId' => 'Calon siswa harus diimpor ke tahun ajaran yang lebih baru dari tahun aktif.',
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
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'kelas' => ['required', 'string'],
            'nama_panggilan' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'nama_orang_tua' => ['nullable', 'string', 'max:255'],
            'no_telp_orang_tua' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ], [
            'required' => ':attribute wajib diisi.',
            'jenis_kelamin.in' => 'Jenis Kelamin harus L atau P.',
        ], [
            'nama_lengkap' => 'Nama Lengkap',
            'kelas' => 'Kelas Tujuan',
            'nama_panggilan' => 'Nama Panggilan',
            'jenis_kelamin' => 'Jenis Kelamin',
            'nama_orang_tua' => 'Nama Orang Tua',
            'no_telp_orang_tua' => 'No Telp Orang Tua',
            'alamat' => 'Alamat',
            'notes' => 'Catatan',
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
}
