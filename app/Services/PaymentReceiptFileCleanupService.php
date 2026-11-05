<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

final class PaymentReceiptFileCleanupService
{
    /**
     * @param  list<mixed>  $paths
     */
    public function scheduleAfterCommit(array $paths): void
    {
        $managedPaths = collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->unique(strict: true)
            ->values()
            ->all();

        if ($managedPaths === []) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            throw new LogicException('Payment receipt cleanup must be scheduled inside a database transaction.');
        }

        DB::afterCommit(fn () => $this->cleanup($managedPaths));
    }

    /** @param list<string> $paths */
    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->cleanupPath($path);
            } catch (Throwable $exception) {
                report(new RuntimeException('Payment receipt file cleanup failed.', previous: $exception));
            }
        }
    }

    private function cleanupPath(string $path): void
    {
        if (! $this->isManagedPath($path)
            || Payment::query()->where('receipt', $path)->exists()
            || ProspectiveStudentPayment::query()->where('receipt', $path)->exists()) {
            return;
        }

        $storage = Storage::disk('public');

        if (! $this->isSafeLocalFile($storage, $path) || ! $storage->exists($path)) {
            return;
        }

        if (! $storage->delete($path)) {
            report(new RuntimeException('Payment receipt file cleanup failed [storage_delete_failed].'));
        }
    }

    private function isManagedPath(string $path): bool
    {
        return config('filesystems.disks.public.driver') === 'local'
            && preg_match('/\Areceipts\/[A-Za-z0-9][A-Za-z0-9._-]*\z/', $path) === 1;
    }

    private function isSafeLocalFile(FilesystemAdapter $storage, string $path): bool
    {
        $rootPath = rtrim($storage->path(''), DIRECTORY_SEPARATOR);
        $filePath = $storage->path($path);
        $currentPath = $rootPath;

        foreach (explode('/', $path) as $segment) {
            $currentPath .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($currentPath)) {
                return false;
            }
        }

        $rootRealPath = realpath($rootPath);
        $parentRealPath = realpath(dirname($filePath));

        if ($rootRealPath === false
            || $parentRealPath === false
            || ! str_starts_with($parentRealPath, rtrim($rootRealPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return false;
        }

        if (! file_exists($filePath)) {
            return true;
        }

        $fileRealPath = realpath($filePath);

        return $fileRealPath !== false
            && str_starts_with($fileRealPath, rtrim($rootRealPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
