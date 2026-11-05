<?php

use App\Services\PpdbDocumentDirectoryCleaner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->ppdbDirectoryRoot = sys_get_temp_dir().'/annur-ppdb-directory-cleaner-'.Str::uuid();
    File::makeDirectory($this->ppdbDirectoryRoot, 0700, true);
    config(['filesystems.disks.local.root' => $this->ppdbDirectoryRoot]);
    Storage::forgetDisk('local');
});

afterEach(function (): void {
    Storage::forgetDisk('local');
    File::deleteDirectory($this->ppdbDirectoryRoot);

    if (isset($this->ppdbOutsideRoot)) {
        File::deleteDirectory($this->ppdbOutsideRoot);
    }
});

it('removes only an empty managed UUID directory', function () {
    $uuid = (string) Str::uuid();
    $directory = "ppdb/documents/2026/11/{$uuid}";
    Storage::disk('local')->makeDirectory($directory);

    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty('local', "{$directory}/bukti.pdf");

    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists('ppdb/documents/2026/11'))->toBeTrue();
});

it('leaves a non-empty UUID directory and its unrelated file untouched', function () {
    $uuid = (string) Str::uuid();
    $directory = "ppdb/documents/2026/11/{$uuid}";
    $siblingPath = "{$directory}/keep.txt";
    Storage::disk('local')->put($siblingPath, 'keep');

    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty('local', "{$directory}/deleted.pdf");

    Storage::disk('local')->assertExists($siblingPath);
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();
});

it('ignores invalid and non UUID paths', function (string $disk, string $path) {
    $sentinel = 'ppdb/documents/2026/11/sentinel/keep.txt';
    Storage::disk('local')->put($sentinel, 'keep');

    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty($disk, $path);

    Storage::disk('local')->assertExists($sentinel);
})->with([
    'wrong disk' => ['public', 'ppdb/documents/2026/11/'.Str::uuid().'/file.pdf'],
    'non PPDB path' => ['local', 'other/documents/2026/11/'.Str::uuid().'/file.pdf'],
    'traversal' => ['local', 'ppdb/documents/2026/11/../file.pdf'],
    'unexpected depth' => ['local', 'ppdb/documents/2026/11/'.Str::uuid().'/nested/file.pdf'],
    'invalid month' => ['local', 'ppdb/documents/2026/13/'.Str::uuid().'/file.pdf'],
    'non UUID directory' => ['local', 'ppdb/documents/2026/11/not-a-uuid/file.pdf'],
]);

it('rejects a symlinked UUID directory', function () {
    $uuid = (string) Str::uuid();
    $target = $this->ppdbDirectoryRoot.'/target';
    $link = $this->ppdbDirectoryRoot."/ppdb/documents/2026/11/{$uuid}";
    File::makeDirectory($target, 0700, true);
    File::makeDirectory(dirname($link), 0700, true);
    symlink($target, $link);

    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty('local', "ppdb/documents/2026/11/{$uuid}/file.pdf");

    expect(is_link($link))->toBeTrue()
        ->and(is_dir($target))->toBeTrue();
});

it('rejects a directory that resolves outside the disk root', function () {
    $uuid = (string) Str::uuid();
    $this->ppdbOutsideRoot = sys_get_temp_dir().'/annur-ppdb-directory-outside-'.Str::uuid();
    $outsideDirectory = $this->ppdbOutsideRoot."/{$uuid}";
    $monthLink = $this->ppdbDirectoryRoot.'/ppdb/documents/2026/11';
    File::makeDirectory($outsideDirectory, 0700, true);
    File::makeDirectory(dirname($monthLink), 0700, true);
    symlink($this->ppdbOutsideRoot, $monthLink);

    app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty('local', "ppdb/documents/2026/11/{$uuid}/file.pdf");

    expect(is_dir($outsideDirectory))->toBeTrue();
});
