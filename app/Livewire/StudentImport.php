<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Services\StudentImportService;
use App\Services\StudentImportSpreadsheet;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Layout('layouts.app')]
class StudentImport extends Component
{
    use WithFileUploads;

    public string $academicYearId = '';

    public string $importContext = '';

    public ?TemporaryUploadedFile $file = null;

    public int $step = 1;

    /** @var array<int, array<string, mixed>> */
    public array $importRows = [];

    /** @var array<string, mixed> */
    public array $preview = [];

    /** @var array<string, mixed> */
    public array $result = [];

    public function downloadTemplate(StudentImportSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $path = $spreadsheet->createTemplate();

        return response()
            ->download($path, 'template-import-siswa.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewImport(StudentImportSpreadsheet $spreadsheet, StudentImportService $importService): void
    {
        $this->validate([
            'academicYearId' => ['required', 'exists:academic_years,id'],
            'importContext' => ['required', 'in:'.StudentImportService::CONTEXT_PROSPECTIVE.','.StudentImportService::CONTEXT_ACTIVE],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ], [
            'academicYearId.required' => 'Tahun ajaran wajib dipilih.',
            'importContext.required' => 'Status/konteks siswa wajib dipilih.',
            'file.required' => 'File XLSX wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
        ]);

        try {
            $this->importRows = $spreadsheet->read($this->file->getRealPath());

            if ($this->importRows === []) {
                $this->addError('file', 'File tidak memiliki data siswa.');

                return;
            }

            $this->preview = $importService->preview(
                $this->importRows,
                (int) $this->academicYearId,
                $this->importContext,
            );
            $this->step = 2;
            $this->reset('file');
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());
        }
    }

    public function confirmImport(StudentImportService $importService): void
    {
        if ($this->importRows === [] || ($this->preview['has_errors'] ?? true)) {
            $this->addError('import', 'Import tidak dapat diproses selama masih ada baris bermasalah.');

            return;
        }

        try {
            $this->result = $importService->import(
                $this->importRows,
                (int) $this->academicYearId,
                $this->importContext,
            );
            $this->step = 3;
            $this->importRows = [];
            $this->preview = [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('import', 'Import gagal dan seluruh perubahan telah dibatalkan. '.$exception->getMessage());
        }
    }

    public function backToSetup(): void
    {
        $this->step = 1;
        $this->importRows = [];
        $this->preview = [];
        $this->result = [];
        $this->reset('file');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.student-import', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
        ]);
    }
}
