<?php

namespace App\Services;

use App\Enums\ProspectiveStudentStatus;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengonversi calon siswa menjadi siswa resmi (alur "Jadikan Siswa").
 *
 * Calon siswa berstatus Terdaftar dibuatkan profil Student untuk tahun ajaran
 * tujuan dengan buku tagihan awal mengikuti inisialisasi siswa masa depan:
 * tagihan tahunan + sekali bayar + komponen bulanan bulan Juli pada tahun
 * ajaran tujuan. Tagihan pendaftaran, pembayaran, dan kwitansi calon siswa
 * TIDAK dihapus ataupun disalin — riwayatnya dipertahankan dan hanya
 * ditautkan kembali melalui converted_student_id / converted_at.
 *
 * Seluruh proses berjalan dalam satu transaksi database. Baris calon siswa
 * dikunci dengan FOR UPDATE lalu status dan converted_student_id diperiksa
 * ulang di dalam transaksi untuk mencegah konversi ganda saat proses terjadi
 * bersamaan.
 */
class ProspectiveStudentConversionService
{
    public function __construct(
        private readonly StudentCreationService $studentCreationService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function convert(ProspectiveStudent $prospectiveStudent, ?string $nis = null): Student
    {
        return DB::transaction(function () use ($prospectiveStudent, $nis): Student {
            $prospect = ProspectiveStudent::query()
                ->whereKey($prospectiveStudent->getKey())
                ->with(['academicYear', 'schoolClass'])
                ->lockForUpdate()
                ->first();

            if ($prospect === null) {
                throw ValidationException::withMessages([
                    'prospectiveStudent' => 'Calon siswa tidak ditemukan.',
                ]);
            }

            $this->assertEligible($prospect);

            $student = $this->studentCreationService->create($this->studentData($prospect, $nis));

            $prospect->update([
                'status' => ProspectiveStudentStatus::Converted,
                'converted_student_id' => $student->id,
                'converted_at' => now(),
            ]);

            return $student->refresh();
        });
    }

    private function assertEligible(ProspectiveStudent $prospect): void
    {
        if ($prospect->status !== ProspectiveStudentStatus::Registered) {
            throw ValidationException::withMessages([
                'prospectiveStudent' => 'Hanya calon siswa berstatus Terdaftar yang dapat dijadikan siswa.',
            ]);
        }

        if ($prospect->converted_student_id !== null) {
            throw ValidationException::withMessages([
                'prospectiveStudent' => 'Calon siswa ini sudah dikonversi menjadi siswa.',
            ]);
        }

        if ($prospect->schoolClass === null || $prospect->school_class_id === null) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Kelas tujuan wajib ditentukan sebelum calon siswa dijadikan siswa.',
            ]);
        }

        if ($prospect->academicYear === null || $prospect->academic_year_id === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Tahun ajaran tujuan wajib ditentukan sebelum calon siswa dijadikan siswa.',
            ]);
        }

        $activeYear = AcademicYear::active();

        if ($activeYear === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Tahun ajaran aktif saat ini tidak ditemukan.',
            ]);
        }

        if ($prospect->academicYear->start_date->lessThanOrEqualTo($activeYear->start_date)) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Calon siswa hanya dapat dijadikan siswa untuk tahun ajaran mendatang. Gunakan alur Data Siswa untuk siswa pada tahun ajaran aktif.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function studentData(ProspectiveStudent $prospect, ?string $nis): array
    {
        return [
            'nis' => is_string($nis) && trim($nis) !== '' ? trim($nis) : null,
            'nama_lengkap' => $prospect->nama_lengkap,
            'nama_panggilan' => $prospect->nama_panggilan,
            'class_id' => $prospect->school_class_id,
            'jenis_kelamin' => $prospect->jenis_kelamin,
            'alamat' => $prospect->alamat,
            'nama_ayah' => $prospect->nama_orang_tua,
            'no_telp_ayah' => $prospect->no_telp_orang_tua,
            'entry_academic_year_id' => $prospect->academic_year_id,
        ];
    }
}
