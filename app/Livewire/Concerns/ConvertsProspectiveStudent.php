<?php

namespace App\Livewire\Concerns;

use App\Enums\ProspectiveStudentStatus;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Services\ProspectiveStudentConversionService;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Perilaku bersama untuk tombol "Jadikan Siswa" pada halaman detail calon
 * siswa, workspace pembayaran calon siswa, dan daftar calon siswa. Properti
 * calon siswa yang akan dikonversi diisi oleh pemanggil sebelum modal dibuka.
 */
trait ConvertsProspectiveStudent
{
    public ?ProspectiveStudent $prospectiveStudent = null;

    public bool $isConvertModalOpen = false;

    public ?string $convertNis = null;

    public function openConvertModal(): void
    {
        if ($this->prospectiveStudent->status !== ProspectiveStudentStatus::Registered
            || $this->prospectiveStudent->converted_student_id !== null) {
            session()->flash('error', 'Calon siswa ini tidak dapat dijadikan siswa.');

            return;
        }

        $this->reset('convertNis');
        $this->resetValidation();
        $this->isConvertModalOpen = true;
    }

    public function closeConvertModal(): void
    {
        $this->isConvertModalOpen = false;
        $this->reset('convertNis');
        $this->resetValidation('convertNis');
    }

    public function convertToStudent(ProspectiveStudentConversionService $conversionService): void
    {
        $validated = $this->validate(['convertNis' => 'nullable|string|max:50']);

        try {
            $student = $conversionService->convert($this->prospectiveStudent, $validated['convertNis']);
        } catch (ValidationException $exception) {
            $messages = Arr::flatten($exception->errors());
            session()->flash('error', $messages[0] ?? 'Calon siswa tidak dapat dijadikan siswa.');

            return;
        }

        session()->flash('success', "Calon siswa {$this->prospectiveStudent->nama_lengkap} berhasil dijadikan siswa.");

        $this->handleSuccessfulConversion($student);
    }

    /**
     * Perilaku setelah konversi berhasil. Secara default pengguna dialihkan
     * ke halaman detail siswa yang baru dibuat. Komponen pemanggil dapat
     * mengesampingkan metode ini, misalnya untuk tetap berada di halaman
     * daftar calon siswa.
     */
    protected function handleSuccessfulConversion(Student $student): void
    {
        $this->redirectRoute('siswa.show', ['student' => $student->id]);
    }
}
