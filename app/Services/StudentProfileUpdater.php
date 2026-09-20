<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentProfileUpdater
{
    public function __construct(private readonly StudentNisConflictService $studentNisConflictService) {}

    /**
     * @return array<string, string>
     */
    public function rules(Student $student): array
    {
        return [
            'nis' => 'nullable|string|max:50',
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            ...$this->biodataRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function biodataRules(): array
    {
        return [
            'nama_ayah' => 'nullable|string|max:255',
            'no_telp_ayah' => 'nullable|string|max:50',
            'nama_ibu' => 'nullable|string|max:255',
            'no_telp_ibu' => 'nullable|string|max:50',
            'tempat_lahir' => 'nullable|string|max:255',
            'tanggal_lahir' => 'nullable|date_format:Y-m-d',
            'entry_date' => 'nullable|date_format:Y-m-d',
            'foto_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_foto' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.max' => 'NIS maksimal 50 karakter.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max' => 'Nama lengkap terlalu panjang.',
            'nama_panggilan.max' => 'Nama panggilan terlalu panjang.',
            'class_id.required' => 'Kelas wajib dipilih.',
            'class_id.exists' => 'Kelas yang dipilih tidak valid.',
            'jenis_kelamin.in' => 'Pilihan jenis kelamin tidak valid.',
            'tanggal_lahir.date_format' => 'Tanggal lahir harus berformat YYYY-MM-DD.',
            'entry_date.date_format' => 'Tanggal masuk harus berformat YYYY-MM-DD.',
            'foto_upload.image' => 'Foto siswa harus berupa gambar.',
            'foto_upload.mimes' => 'Foto siswa harus berformat JPG, JPEG, PNG, atau WEBP.',
            'foto_upload.max' => 'Ukuran foto siswa maksimal 2 MB.',
        ];
    }

    public function representedClassId(Student $student): ?int
    {
        $representedEnrollment = $this->representedEnrollment($student);

        return $representedEnrollment ? $representedEnrollment->school_class_id : $student->class_id;
    }

    /**
     * @param  array<string, mixed>  $validatedData
     */
    public function update(Student $student, array $validatedData): void
    {
        DB::transaction(function () use ($student, $validatedData): void {
            $representedEnrollment = $this->representedEnrollment($student);
            $profileData = Arr::only($validatedData, [
                'nis',
                'nama_lengkap',
                'nama_panggilan',
                'class_id',
                'jenis_kelamin',
                'alamat',
                'nama_ayah',
                'no_telp_ayah',
                'nama_ibu',
                'no_telp_ibu',
                'tempat_lahir',
                'tanggal_lahir',
                'entry_date',
                'foto',
            ]);

            $profileData = $this->normalizeBiodata($profileData);

            if ($representedEnrollment) {
                $academicYear = AcademicYear::query()->findOrFail($representedEnrollment->academic_year_id);
                $schoolClass = SchoolClass::query()->findOrFail((int) $profileData['class_id']);
                $nis = is_string($profileData['nis'] ?? null) ? trim($profileData['nis']) : '';

                if ($nis !== '') {
                    $this->studentNisConflictService->ensureNoConflict(
                        $nis,
                        $academicYear,
                        $schoolClass,
                        $student->id,
                    );
                }
            }

            $student->update($profileData);

            $representedEnrollment?->update([
                'school_class_id' => $profileData['class_id'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeBiodata(array $data): array
    {
        foreach (['nis', 'nama_panggilan', 'jenis_kelamin', 'alamat', 'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu', 'tempat_lahir', 'tanggal_lahir', 'entry_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    private function representedEnrollment(Student $student): ?StudentAcademicEnrollment
    {
        $academicStatus = $student->academicStatus();
        $activeYear = AcademicYear::active();

        if ($academicStatus === StudentStatus::Graduated->value || ! $activeYear) {
            return null;
        }

        if ($academicStatus === 'calon_siswa') {
            return $student->enrollments()
                ->whereHas('academicYear', function ($query) use ($activeYear) {
                    $query->whereDate('start_date', '>', $activeYear->start_date);
                })
                ->orderBy(
                    AcademicYear::query()
                        ->select('start_date')
                        ->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id')
                        ->limit(1)
                )
                ->first();
        }

        return $student->enrollments()
            ->where('academic_year_id', $activeYear->id)
            ->where('status', 'active')
            ->first();
    }
}
