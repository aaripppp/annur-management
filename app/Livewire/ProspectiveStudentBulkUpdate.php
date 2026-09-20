<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\ProspectiveStudentBulkUpdateSpreadsheet;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Layout('layouts.app')]
class ProspectiveStudentBulkUpdate extends Component
{
    use WithFileUploads;

    public string $filterAcademicYearId = '';

    public string $filterLevel = '';

    public string $filterClassId = '';

    public ?TemporaryUploadedFile $file = null;

    public int $step = 1;

    /** @var array<int, array<string, mixed>> */
    public array $fileRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, int> */
    public array $summary = [];

    /** @var array<string, mixed> */
    public array $result = [];

    public function downloadTemplate(ProspectiveStudentBulkUpdateSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $academicYearId = $this->filterAcademicYearId !== '' ? (int) $this->filterAcademicYearId : null;
        $level = $this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null;
        $classId = $this->filterClassId !== '' ? (int) $this->filterClassId : null;

        $path = $spreadsheet->create($academicYearId, $level, $classId);

        return response()
            ->download($path, 'update-data-calon-siswa.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewUpdate(ProspectiveStudentBulkUpdateSpreadsheet $spreadsheet, ProspectiveStudentBulkUpdateService $service): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ], [
            'file.required' => 'File XLSX wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
        ]);

        try {
            if ($this->file === null) {
                return;
            }

            $parsedRows = $spreadsheet->read($this->file->getRealPath());

            if ($parsedRows === []) {
                $this->addError('file', 'File tidak memiliki baris data calon siswa.');

                return;
            }

            $this->fileRows = $parsedRows;

            $previewResult = $service->preview($parsedRows);
            $this->rows = $previewResult['rows'];
            $this->summary = $previewResult['summary'];
            $this->step = 2;
            $this->reset('file');
            $this->resetValidation();
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());
        }
    }

    public function confirmUpdate(ProspectiveStudentBulkUpdateService $service): void
    {
        if ($this->fileRows === []) {
            return;
        }

        try {
            $count = $service->apply($this->fileRows);
            $this->result = [
                'updated' => $count,
            ];
            $this->step = 3;
            $this->fileRows = [];
            $this->rows = [];
            $this->summary = [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('update', 'Update gagal karena terjadi kesalahan. '.$exception->getMessage());
        }
    }

    public function backToSetup(): void
    {
        $this->step = 1;
        $this->fileRows = [];
        $this->rows = [];
        $this->summary = [];
        $this->result = [];
        $this->reset('file');
        $this->resetValidation();
    }

    public function updatedFilterLevel(): void
    {
        $this->filterClassId = '';
    }

    public function render(): View
    {
        $level = $this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null;

        $classes = SchoolClass::query()
            ->when($level instanceof SchoolLevel, function ($query) use ($level) {
                $query->whereIn('level', $level->classLevels());
            })
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('livewire.prospective-student-bulk-update', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'levels' => SchoolLevel::cases(),
            'classes' => $classes,
        ]);
    }
}
