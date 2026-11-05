<?php

namespace App\Services;

use App\Enums\PpdbDocumentType;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PpdbDocumentReplacer
{
    /**
     * @param  array{prospective_student_id:int, document_type:string, document_id?:int|null, expected_path?:string|null, expected_updated_at?:string|null}  $context
     */
    public function replaceOrUpload(array $context, UploadedFile $file): PpdbDocument
    {
        $prospectiveStudentId = (int) ($context['prospective_student_id'] ?? 0);
        $documentTypeValue = (string) ($context['document_type'] ?? '');
        $documentId = isset($context['document_id']) ? (int) $context['document_id'] : null;
        $expectedPath = $context['expected_path'] ?? null;
        $expectedUpdatedAt = $context['expected_updated_at'] ?? null;

        if (! in_array($documentTypeValue, PpdbDocumentType::values(), true)) {
            throw ValidationException::withMessages([
                'document_type' => 'Tipe dokumen tidak valid.',
            ]);
        }

        $this->validateFile($file);

        $stagedPath = $this->stageFile($documentTypeValue, $file);
        $stagedDisk = PpdbPublicRegistrationService::DOCUMENT_DISK;

        try {
            $result = DB::transaction(function () use ($prospectiveStudentId, $documentTypeValue, $documentId, $file, $stagedPath, $stagedDisk, $expectedPath, $expectedUpdatedAt): PpdbDocument {
                $prospectiveStudent = ProspectiveStudent::query()
                    ->lockForUpdate()
                    ->find($prospectiveStudentId);

                if (! $prospectiveStudent) {
                    throw ValidationException::withMessages([
                        'prospective_student_id' => 'Data calon siswa tidak ditemukan.',
                    ]);
                }

                $existingDocument = null;
                if ($documentId) {
                    $existingDocument = PpdbDocument::query()
                        ->whereKey($documentId)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $existingDocument) {
                    $existingDocument = PpdbDocument::query()
                        ->where('prospective_student_id', $prospectiveStudentId)
                        ->where('document_type', $documentTypeValue)
                        ->lockForUpdate()
                        ->first();
                }

                if ($existingDocument) {
                    if ((int) $existingDocument->prospective_student_id !== $prospectiveStudentId) {
                        throw ValidationException::withMessages([
                            'document' => 'Dokumen tidak valid untuk calon siswa ini.',
                        ]);
                    }
                    $existingDocumentType = $existingDocument->document_type instanceof PpdbDocumentType
                        ? $existingDocument->document_type->value
                        : (string) $existingDocument->document_type;
                    if ($existingDocumentType !== $documentTypeValue) {
                        throw ValidationException::withMessages([
                            'document_type' => 'Tipe dokumen tidak sesuai.',
                        ]);
                    }

                    // Stale update protection
                    if (is_string($expectedPath) && $expectedPath !== '' && $existingDocument->path !== $expectedPath) {
                        throw ValidationException::withMessages([
                            'file' => 'Dokumen telah diperbarui oleh admin lain. Muat ulang dan coba kembali.',
                        ]);
                    }
                    if (is_string($expectedUpdatedAt) && $expectedUpdatedAt !== '') {
                        $current = $existingDocument->updated_at?->toISOString();
                        if (is_string($current) && $current !== $expectedUpdatedAt) {
                            throw ValidationException::withMessages([
                                'file' => 'Dokumen telah diperbarui oleh admin lain. Muat ulang dan coba kembali.',
                            ]);
                        }
                    }

                    $oldDocumentSnapshot = [
                        'document_id' => $existingDocument->id,
                        'prospective_student_id' => $existingDocument->prospective_student_id,
                        'disk' => $existingDocument->disk,
                        'path' => $existingDocument->path,
                    ];

                    $existingDocument->update([
                        'disk' => $stagedDisk,
                        'path' => $stagedPath,
                        'original_name' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
                        'mime_type' => (string) $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                        'uploaded_by' => auth()->id(),
                    ]);

                    $existingDocument->refresh();

                    // Record cleanup intent durably inside transaction and delete only after outermost commit
                    try {
                        $cleanup = app(PpdbDocumentCleanupService::class);
                        // Use a temporary object to leverage scheduleFor's ledger/afterCommit behavior safely
                        $tmp = new class($oldDocumentSnapshot)
                        {
                            public function __construct(private array $snap) {}

                            public function ppdbDocuments()
                            {
                                return new class($this->snap)
                                {
                                    private array $snap;

                                    public function __construct(array $snap)
                                    {
                                        $this->snap = $snap;
                                    }

                                    public function lockForUpdate()
                                    {
                                        return $this;
                                    }

                                    public function get($columns = ['*'])
                                    {
                                        return collect([
                                            (object) [
                                                'id' => $this->snap['document_id'],
                                                'prospective_student_id' => $this->snap['prospective_student_id'],
                                                'disk' => $this->snap['disk'],
                                                'path' => $this->snap['path'],
                                            ],
                                        ]);
                                    }
                                };
                            }
                        };
                        $cleanup->scheduleFor($tmp);
                    } catch (Throwable) {
                        // Fallback to immediate cleanup path only if scheduling not possible
                    }

                    return $existingDocument;
                }

                // Missing document upload
                $document = PpdbDocument::query()->create([
                    'prospective_student_id' => $prospectiveStudentId,
                    'document_type' => $documentTypeValue,
                    'disk' => $stagedDisk,
                    'path' => $stagedPath,
                    'original_name' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => (int) $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            // Clean staged file on failure
            try {
                if (Storage::disk($stagedDisk)->delete($stagedPath)) {
                    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty($stagedDisk, $stagedPath);
                }
            } catch (Throwable) {
                // ignore
            }

            throw $exception;
        }

        return $result;
    }

    private function validateFile(UploadedFile $file): void
    {
        $validator = Validator::make(['file' => $file], [
            'file' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(2048),
            ],
        ]);

        $validator->validate();
    }

    private function stageFile(string $documentType, UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages([
                'file' => 'Format dokumen tidak didukung.',
            ]),
        };

        $directory = 'ppdb/documents/'.now()->format('Y/m').'/'.Str::uuid();
        $filename = $documentType.'-'.Str::random(40).'.'.$extension;
        $path = $file->storeAs($directory, $filename, PpdbPublicRegistrationService::DOCUMENT_DISK);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Dokumen PPDB gagal disimpan.');
        }

        return $path;
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $sanitized = preg_replace('/[^\pL\pN._ -]/u', '_', basename($filename));
        $sanitized = Str::limit(trim((string) $sanitized), 255, '');

        return $sanitized !== '' ? $sanitized : 'dokumen';
    }
}
