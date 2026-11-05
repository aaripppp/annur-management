<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class PpdbDocumentDirectoryCleaner
{
    public function removeIfEmpty(string $disk, string $path): void
    {
        try {
            if ($disk !== PpdbPublicRegistrationService::DOCUMENT_DISK
                || config("filesystems.disks.{$disk}.driver") !== 'local'
                || preg_match(
                    '/\Appdb\/documents\/(?<year>\d{4})\/(?<month>0[1-9]|1[0-2])\/(?<uuid>[0-9a-fA-F-]+)\/(?<filename>[^\/\\\\\x00]+)\z/',
                    $path,
                    $matches,
                ) !== 1
                || ! Str::isUuid($matches['uuid'], 4)) {
                return;
            }

            $storage = Storage::disk($disk);
            $directory = "ppdb/documents/{$matches['year']}/{$matches['month']}/{$matches['uuid']}";
            $rootPath = rtrim($storage->path(''), DIRECTORY_SEPARATOR);
            $directoryPath = $storage->path($directory);

            if (! is_dir($directoryPath)) {
                return;
            }

            $currentPath = $rootPath;

            foreach (explode('/', $directory) as $segment) {
                $currentPath .= DIRECTORY_SEPARATOR.$segment;

                if (is_link($currentPath)) {
                    return;
                }
            }

            $rootRealPath = realpath($rootPath);
            $directoryRealPath = realpath($directoryPath);

            if ($rootRealPath === false
                || $directoryRealPath === false
                || ! str_starts_with($directoryRealPath, rtrim($rootRealPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return;
            }

            @rmdir($directoryRealPath);
        } catch (Throwable) {
            // Empty-directory housekeeping must never fail document cleanup.
        }
    }
}
