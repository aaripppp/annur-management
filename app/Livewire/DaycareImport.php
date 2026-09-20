<?php

namespace App\Livewire;

use App\Services\DaycareImportService;
use App\Services\DaycareImportSpreadsheet;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Layout('layouts.app')]
class DaycareImport extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public int $step = 1;

    /** @var array<int, array<string, mixed>> */
    public array $importRows = [];

    /** @var array<string, mixed> */
    public array $preview = [];

    /** @var array<string, mixed> */
    public array $result = [];

    public function downloadTemplate(DaycareImportSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $path = $spreadsheet->createTemplate();

        return response()
            ->download($path, 'template-import-daycare.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewImport(DaycareImportSpreadsheet $spreadsheet, DaycareImportService $importService): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ], [
            'file.required' => 'File XLSX wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
        ]);

        try {
            $this->importRows = $spreadsheet->read($this->file->getRealPath());

            if ($this->importRows === []) {
                $this->addError('file', 'File tidak memiliki data daycare.');

                return;
            }

            $this->preview = $importService->preview($this->importRows);
            $this->step = 2;
            $this->reset('file');
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());
        }
    }

    public function confirmImport(DaycareImportService $importService): void
    {
        if ($this->importRows === [] || ($this->preview['has_errors'] ?? true)) {
            $this->addError('import', 'Import tidak dapat diproses selama masih ada baris bermasalah.');

            return;
        }

        try {
            $this->result = $importService->import($this->importRows);
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
        return view('livewire.daycare-import');
    }
}
