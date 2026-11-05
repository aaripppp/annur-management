<?php

namespace App\Http\Controllers;

use App\Enums\PpdbDocumentType;
use App\Models\PpdbDocument;
use App\Services\PpdbPublicRegistrationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PpdbDocumentController extends Controller
{
    /** @var array<string, string> */
    private const ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function preview(PpdbDocument $ppdbDocument): StreamedResponse
    {
        Gate::authorize('view', $ppdbDocument);

        return $this->respond($ppdbDocument, 'inline');
    }

    public function download(PpdbDocument $ppdbDocument): StreamedResponse
    {
        Gate::authorize('download', $ppdbDocument);

        return $this->respond($ppdbDocument, 'attachment');
    }

    private function respond(PpdbDocument $document, string $disposition): StreamedResponse
    {
        abort_unless(
            $document->disk === PpdbPublicRegistrationService::DOCUMENT_DISK
                && $this->isManagedPath($document->path),
            404
        );

        $storage = Storage::disk($document->disk);

        try {
            abort_unless($storage->exists($document->path), 404);

            $mimeType = $storage->mimeType($document->path);
        } catch (Throwable) {
            abort(404);
        }

        abort_unless(is_string($mimeType) && isset(self::ALLOWED_MIME_EXTENSIONS[$mimeType]), 404);

        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType];
        $documentType = $document->document_type instanceof PpdbDocumentType
            ? $document->document_type->value
            : 'document';
        $filename = 'ppdb-'.$documentType.'-'.$document->id.'.'.$extension;

        return $storage->response(
            $document->path,
            $filename,
            ['Content-Type' => $mimeType],
            $disposition
        );
    }

    private function isManagedPath(string $path): bool
    {
        return str_starts_with($path, 'ppdb/documents/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0");
    }
}
