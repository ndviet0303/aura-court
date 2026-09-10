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
        $disk = config('filesystems.disks.r2.key') ? 'r2' : (config('filesystems.disks.s3.key') ? 's3' : 'public');

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
}
