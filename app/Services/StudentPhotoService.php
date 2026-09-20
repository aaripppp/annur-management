<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

    private function isManagedPath(?string $path): bool
    {
        return $path !== null
            && str_starts_with($path, 'student-photos/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }
}
