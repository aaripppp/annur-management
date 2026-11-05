<?php

namespace App\Livewire;

use App\Enums\PpdbDocumentType;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Services\PpdbDocumentReplacer as PpdbDocumentReplacerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class PpdbDocumentReplacer extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public bool $isOpen = false;

    public ?int $prospectiveStudentId = null;

    public ?int $studentId = null;

    public ?int $documentId = null;

    public string $documentType = '';

    public ?string $currentOriginalName = null;

    public $file = null;

    public function mount(?int $prospectiveStudentId = null, ?int $studentId = null): void
    {
        $this->prospectiveStudentId = $prospectiveStudentId;
        $this->studentId = $studentId;
    }

    #[On('ppdb-document:replace')]
    public function openReplace(?int $prospectiveStudentId = null, ?int $studentId = null, ?int $documentId = null, ?string $documentType = null): void
    {
        $this->prospectiveStudentId = $prospectiveStudentId ?? $this->prospectiveStudentId;
        $this->studentId = $studentId ?? $this->studentId;
        $this->documentId = $documentId;
        $this->documentType = $documentType ?? '';
        $this->file = null;
        $this->resetValidation();

        $prospectiveStudent = null;
        if ($this->prospectiveStudentId) {
            $prospectiveStudent = ProspectiveStudent::query()->find($this->prospectiveStudentId);
        }

        if (! $prospectiveStudent) {
            $this->addError('file', 'Data calon siswa tidak ditemukan.');

            return;
        }

        // Verify linkage if student context provided
        if ($this->studentId) {
            $student = Student::query()->find($this->studentId);
            if (! $student || (int) $prospectiveStudent->converted_student_id !== (int) $student->id) {
                $this->addError('file', 'Tautan siswa tidak valid.');

                return;
            }
        }

        Gate::authorize('replace', $this->documentId ? PpdbDocument::find($this->documentId) : new PpdbDocument);

        if ($this->documentId) {
            $document = PpdbDocument::query()->find($this->documentId);
            if ($document && (int) $document->prospective_student_id === (int) $prospectiveStudent->id) {
                $this->currentOriginalName = $document->original_name;
                $this->documentType = $document->document_type instanceof PpdbDocumentType ? $document->document_type->value : (string) $document->document_type;
            }
        }

        $this->isOpen = true;
    }

    public function cancel(): void
    {
        $this->isOpen = false;
        $this->file = null;
        $this->resetValidation();
    }

    public function save(PpdbDocumentReplacerService $replacer): void
    {
        $this->resetValidation();

        if (! $this->prospectiveStudentId || $this->documentType === '') {
            $this->addError('file', 'Data dokumen tidak lengkap.');

            return;
        }

        if (! $this->file) {
            $this->addError('file', 'Pilih berkas pengganti terlebih dahulu.');

            return;
        }

        $prospectiveStudent = ProspectiveStudent::query()->find($this->prospectiveStudentId);
        if (! $prospectiveStudent) {
            $this->addError('file', 'Data calon siswa tidak ditemukan.');

            return;
        }

        if ($this->studentId) {
            $student = Student::query()->find($this->studentId);
            if (! $student || (int) $prospectiveStudent->converted_student_id !== (int) $student->id) {
                $this->addError('file', 'Tautan siswa tidak valid.');

                return;
            }
        }

        $documentForAuth = null;
        $expectedPath = null;
        $expectedUpdatedAt = null;
        if ($this->documentId) {
            $documentForAuth = PpdbDocument::query()->find($this->documentId);
            if ($documentForAuth && (int) $documentForAuth->prospective_student_id !== (int) $prospectiveStudent->id) {
                throw ValidationException::withMessages([
                    'file' => 'Dokumen tidak valid untuk calon siswa ini.',
                ]);
            }
            if ($documentForAuth) {
                $expectedPath = $documentForAuth->path;
                $expectedUpdatedAt = $documentForAuth->updated_at?->toISOString();
            }
        }

        Gate::authorize('replace', $documentForAuth ?? new PpdbDocument);

        try {
            $replacer->replaceOrUpload([
                'prospective_student_id' => $this->prospectiveStudentId,
                'document_type' => $this->documentType,
                'document_id' => $this->documentId,
                'expected_path' => $expectedPath,
                'expected_updated_at' => $expectedUpdatedAt,
            ], $this->file);

            $this->isOpen = false;
            $this->file = null;
            $this->dispatch('ppdb-documents-updated');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('file', 'Gagal mengganti dokumen. Silakan coba kembali.');
        }
    }

    public function render()
    {
        return view('livewire.ppdb-document-replacer', [
            'documentTypeLabel' => $this->documentType !== '' && in_array($this->documentType, PpdbDocumentType::values(), true)
                ? PpdbDocumentType::from($this->documentType)->label()
                : ($this->documentType !== '' ? $this->documentType : '-'),
        ]);
    }
}
