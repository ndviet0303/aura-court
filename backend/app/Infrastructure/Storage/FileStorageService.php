<?php

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\FileStorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService implements FileStorageServiceInterface
{
    public function store(UploadedFile $file, string $directory = 'courts'): array
    {
        $filename = $directory.'/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $disk = $this->disk();

        try {
            Storage::disk($disk)->putFileAs('', $file, $filename, 'public');
            $url = Storage::disk($disk)->url($filename);
        } catch (\Throwable $e) {
            $path = Storage::disk('public')->putFileAs($directory, $file, Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');
            $url = Storage::disk('public')->url($path);
            $filename = $path;
        }

        return [
            'url' => $url,
            'filename' => $filename,
        ];
    }

    /** D11: the configured disk's base URL, or the public disk's that store() falls back to. */
    public function isStoredUrl(string $url): bool
    {
        foreach (array_unique([$this->disk(), 'public']) as $disk) {
            try {
                $base = Storage::disk($disk)->url('');
            } catch (\Throwable) {
                continue; // A disk that cannot be built holds nothing: store() fell back to public for it.
            }
            // Only an absolute base that ends after the host can pin the URL to the platform's own storage.
            if (preg_match('#^https?://[^/]+/#', $base) === 1 && str_starts_with($url, $base)) {
                return true;
            }
        }

        return false;
    }

    private function disk(): string
    {
        return config('filesystems.disks.r2.key') ? 'r2' : (config('filesystems.disks.s3.key') ? 's3' : 'public');
    }
}
