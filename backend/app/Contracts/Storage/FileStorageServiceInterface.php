<?php

namespace App\Contracts\Storage;

use Illuminate\Http\UploadedFile;

interface FileStorageServiceInterface
{
    /**
     * Store an uploaded file and return array with ['url' => ..., 'filename' => ...]
     */
    public function store(UploadedFile $file, string $directory = 'courts'): array;
}
