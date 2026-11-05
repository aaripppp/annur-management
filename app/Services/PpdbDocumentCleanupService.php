<?php

namespace App\Services;

use App\Models\PpdbDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

final class PpdbDocumentCleanupService
{
    /**
     * @return array{attempted: int, resolved: int, pending: int}
     */
    public function retryPending(int $limit = 100): array
    {
        $failures = DB::table('ppdb_document_cleanup_failures')
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();
        $resolved = 0;

        foreach ($failures as $failure) {
            $this->cleanup([[
                'document_id' => (int) $failure->ppdb_document_id,
                'prospective_student_id' => (int) $failure->prospective_student_id,
                'disk' => (string) $failure->disk,
                'path' => (string) $failure->path,
            ]]);

            if (DB::table('ppdb_document_cleanup_failures')
                ->where('id', $failure->id)
                ->whereNotNull('resolved_at')
                ->exists()) {
                $resolved++;
            }
        }

        return [
            'attempted' => $failures->count(),
            'resolved' => $resolved,
            'pending' => DB::table('ppdb_document_cleanup_failures')->whereNull('resolved_at')->count(),
        ];
    }

    /**
     * Capture document ownership while the prospect is locked and defer file
     * removal until the root database transaction commits.
     */
    public function scheduleFor($prospectiveStudent): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('PPDB document cleanup must be scheduled inside a database transaction.');
        }

        $relation = method_exists($prospectiveStudent, 'ppdbDocuments') ? $prospectiveStudent->ppdbDocuments() : null;

        if ($relation && method_exists($relation, 'lockForUpdate')) {
            $relation->lockForUpdate();
        }

        $documents = $relation && method_exists($relation, 'get')
            ? $relation->get(['id', 'prospective_student_id', 'disk', 'path'])
            : collect();

        if ($documents instanceof Collection) {
            $documents = $documents
                ->map(fn ($document): array => [
                    'document_id' => $document->id,
                    'prospective_student_id' => $document->prospective_student_id,
                    'disk' => $document->disk,
                    'path' => $document->path,
                ])
                ->all();
        } else {
            $documents = is_array($documents) ? $documents : [];
        }

        if ($documents === []) {
            return;
        }

        foreach ($documents as $document) {
            $this->recordScheduled($document);
        }

        DB::afterCommit(fn () => $this->cleanup($documents));
    }

    /**
     * @param  list<array{document_id: int, prospective_student_id: int, disk: string, path: string}>  $documents
     */
    public function cleanup(array $documents): void
    {
        foreach ($documents as $document) {
            $this->cleanupDocument($document);
        }
    }

    /**
     * @param  array{document_id: int, prospective_student_id: int, disk: string, path: string}  $document
     */
    private function cleanupDocument(array $document): void
    {
        if (! $this->isManagedLocation($document['disk'], $document['path'])) {
            $this->recordFailure($document, 'unmanaged_location');

            return;
        }

        if (PpdbDocument::query()
            ->where('disk', $document['disk'])
            ->where('path', $document['path'])
            ->exists()) {
            return;
        }

        try {
            $storage = Storage::disk($document['disk']);

            if (! $storage->exists($document['path'])) {
                app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty($document['disk'], $document['path']);
                $this->markResolved($document['path']);

                return;
            }

            if (! $storage->delete($document['path'])) {
                $this->recordFailure($document, 'storage_delete_failed');

                return;
            }

            app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty($document['disk'], $document['path']);
            $this->markResolved($document['path']);
        } catch (Throwable $exception) {
            $this->recordFailure($document, 'storage_exception', $exception);
        }
    }

    private function isManagedLocation(string $disk, string $path): bool
    {
        return $disk === PpdbPublicRegistrationService::DOCUMENT_DISK
            && str_starts_with($path, 'ppdb/documents/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0");
    }

    /**
     * @param  array{document_id: int, prospective_student_id: int, disk: string, path: string}  $document
     */
    private function recordScheduled(array $document): void
    {
        $pathHash = hash('sha256', $document['disk']."\0".$document['path']);
        $existing = DB::table('ppdb_document_cleanup_failures')
            ->where('path_hash', $pathHash)
            ->first(['id']);
        $values = [
            'ppdb_document_id' => $document['document_id'],
            'prospective_student_id' => $document['prospective_student_id'],
            'disk' => $document['disk'],
            'path' => $document['path'],
            'reason_code' => 'scheduled',
            'last_attempted_at' => now(),
            'resolved_at' => null,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('ppdb_document_cleanup_failures')->insert($values + [
                'path_hash' => $pathHash,
                'attempts' => 0,
                'created_at' => now(),
            ]);

            return;
        }

        DB::table('ppdb_document_cleanup_failures')
            ->where('id', $existing->id)
            ->update($values);
    }

    /**
     * @param  array{document_id: int, prospective_student_id: int, disk: string, path: string}  $document
     */
    private function recordFailure(array $document, string $reasonCode, ?Throwable $exception = null): void
    {
        $pathHash = hash('sha256', $document['disk']."\0".$document['path']);

        try {
            $existing = DB::table('ppdb_document_cleanup_failures')
                ->where('path_hash', $pathHash)
                ->first(['id', 'attempts']);

            $values = [
                'ppdb_document_id' => $document['document_id'],
                'prospective_student_id' => $document['prospective_student_id'],
                'disk' => $document['disk'],
                'path' => $document['path'],
                'reason_code' => $reasonCode,
                'last_attempted_at' => now(),
                'resolved_at' => null,
                'updated_at' => now(),
            ];

            if ($existing === null) {
                DB::table('ppdb_document_cleanup_failures')->insert($values + [
                    'path_hash' => $pathHash,
                    'attempts' => 1,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('ppdb_document_cleanup_failures')
                    ->where('id', $existing->id)
                    ->update($values + ['attempts' => ((int) $existing->attempts) + 1]);
            }
        } catch (Throwable $recordingException) {
            report($recordingException);
        }

        report(new RuntimeException(sprintf(
            'PPDB document cleanup failed [%s] for document ID %d and prospective student ID %d.',
            $reasonCode,
            $document['document_id'],
            $document['prospective_student_id'],
        ), previous: $exception));
    }

    private function markResolved(string $path): void
    {
        DB::table('ppdb_document_cleanup_failures')
            ->where('path_hash', hash('sha256', PpdbPublicRegistrationService::DOCUMENT_DISK."\0".$path))
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
