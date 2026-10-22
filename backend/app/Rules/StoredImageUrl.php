<?php

namespace App\Rules;

use App\Contracts\Storage\FileStorageServiceInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * D11: a venue or court image must be one the platform stores itself: a file uploaded through FileStorageService,
 * or a built-in court image /courts/<file>.jpg served from public/courts. Any other link is rejected.
 */
class StoredImageUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isStored($value)) {
            $fail('Ảnh phải được tải lên nền tảng; không nhận liên kết tới website khác.');
        }
    }

    private function isStored(string $url): bool
    {
        // The character class keeps the preset inside public/courts (no "..", no subfolders).
        if (preg_match('#^/courts/[A-Za-z0-9_-]+\.jpg$#', $url) === 1) {
            return is_file(public_path(ltrim($url, '/')));
        }

        return app(FileStorageServiceInterface::class)->isStoredUrl($url);
    }
}
