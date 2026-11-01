<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Upload storage helpers shared by modules: store, roll back newly stored files when the
 * database write fails, and remove replaced files without touching external URLs.
 */
class FileStorageService
{
    /**
     * Store an upload under a random name and return its path.
     *
     * @throws RuntimeException When the disk refuses the file.
     */
    public function store(UploadedFile $file, string $directory, string $disk, bool $uuidName = false): string
    {
        $path = $uuidName
            ? $file->storeAs($directory, Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?: 'bin'), $disk)
            : $file->store($directory, $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('File gagal disimpan ke penyimpanan.');
        }

        return $path;
    }

    /**
     * Run a database write; when it throws, delete the files stored for it and rethrow.
     *
     * @template T
     *
     * @param  list<string|null>|string|null  $storedPaths
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rollbackOnFailure(string $disk, array|string|null $storedPaths, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            foreach ((array) $storedPaths as $path) {
                $this->delete($disk, $path);
            }

            throw $e;
        }
    }

    /**
     * Delete a stored file. Empty values and external URLs (e.g. Google avatars) are ignored;
     * a failed delete is logged instead of breaking the request that already succeeded.
     */
    public function delete(string $disk, ?string $path): void
    {
        if (! $this->isStoredPath($path)) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            Log::warning('Gagal menghapus file dari penyimpanan.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Whether the value is a path on our disks rather than empty or an external URL.
     */
    public function isStoredPath(?string $path): bool
    {
        return filled($path) && ! Str::startsWith($path, ['http://', 'https://']);
    }
}
