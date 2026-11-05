<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

class StudentPhotoService
{
    public function store(UploadedFile $photo): string
    {
        $path = $photo->store('student-photos', 'public');

        if ($path === false) {
            throw new RuntimeException('Foto siswa gagal disimpan.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $this->isManagedPath($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function scheduleDeletionAfterCommit(?string $path): void
    {
        if (! $this->isManagedScheduledPath($path)) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            throw new LogicException('Student photo cleanup must be scheduled inside a database transaction.');
        }

        DB::afterCommit(fn () => $this->deleteIfUnreferenced($path));
    }

    private function deleteIfUnreferenced(string $path): void
    {
        try {
            if (Student::query()->where('foto', $path)->exists()) {
                return;
            }

            $storage = Storage::disk('public');

            if (! $this->isSafeLocalFile($storage, $path) || ! $storage->exists($path)) {
                return;
            }

            if (! $storage->delete($path)) {
                report(new RuntimeException('Student photo cleanup failed [storage_delete_failed].'));
            }
        } catch (Throwable $exception) {
            report(new RuntimeException('Student photo cleanup failed.', previous: $exception));
        }
    }

    private function isManagedScheduledPath(?string $path): bool
    {
        return is_string($path)
            && config('filesystems.disks.public.driver') === 'local'
            && preg_match('/\Astudent-photos\/[A-Za-z0-9][A-Za-z0-9._-]*\z/', $path) === 1;
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

    private function isManagedPath(?string $path): bool
    {
        return $path !== null
            && str_starts_with($path, 'student-photos/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }
}
