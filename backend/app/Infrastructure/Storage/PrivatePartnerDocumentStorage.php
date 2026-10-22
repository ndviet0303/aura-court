<?php

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\PartnerDocumentStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivatePartnerDocumentStorage implements PartnerDocumentStorageInterface
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    private string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? (string) config('filesystems.private_disk', 'local');
    }

    public function storePrivate(UploadedFile $file, int $userId, string $documentType, string $prefix = 'private/kyc'): array
    {
        $this->validateFile($file);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $random = Str::random(16);
        $timestamp = time();
        $safeType = preg_replace('/[^a-zA-Z0-9_\-]/', '', $documentType);

        $relativePath = "{$prefix}/{$userId}/{$safeType}_{$timestamp}_{$random}.{$ext}";

        $hash = hash_file('sha256', $file->getRealPath());

        $stored = Storage::disk($this->disk)->put(
            $relativePath,
            file_get_contents($file->getRealPath())
        );

        if (! $stored) {
            throw new \RuntimeException('Không thể lưu trữ tệp tài liệu xác thực đối tác.');
        }

        return [
            'file_path' => $relativePath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'file_hash' => $hash ?: '',
        ];
    }

    public function temporaryReadUrl(string $filePath, int $expirationMinutes = 15): string
    {
        // Generate a secure temporary signed route URL
        return URL::temporarySignedRoute(
            'partner.documents.download',
            now()->addMinutes($expirationMinutes),
            ['path' => ltrim($filePath, '/')]
        );
    }

    public function downloadResponse(string $filePath, ?string $fileName = null): StreamedResponse
    {
        return Storage::disk($this->disk)->download($filePath, $fileName);
    }

    public function delete(string $filePath): bool
    {
        return Storage::disk($this->disk)->delete($filePath);
    }

    public function exists(string $filePath): bool
    {
        return Storage::disk($this->disk)->exists($filePath);
    }

    public function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Tệp tải lên không hợp lệ hoặc bị lỗi đường truyền.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Định dạng tệp không được hỗ trợ. Chỉ chấp nhận PDF, JPG hoặc PNG.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('Kích thước tệp vượt quá giới hạn tối đa cho phép (10MB).');
        }
    }

    public function cleanupFailedUpload(?string $filePath): void
    {
        if ($filePath && $this->exists($filePath)) {
            try {
                $this->delete($filePath);
            } catch (\Throwable) {
                // Ignore failure during cleanup
            }
        }
    }

    public function maskDocumentNumber(string $number): string
    {
        $clean = trim($number);
        $len = strlen($clean);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        if ($len >= 8) {
            $prefix = substr($clean, 0, 3);
            $suffix = substr($clean, -4);
            $maskLen = max(1, $len - 7);

            return $prefix.str_repeat('*', $maskLen).$suffix;
        }

        $prefix = substr($clean, 0, 2);
        $suffix = substr($clean, -2);
        $maskLen = max(1, $len - 4);

        return $prefix.str_repeat('*', $maskLen).$suffix;
    }

    public function maskBankAccount(string $accountNumber): string
    {
        $clean = trim($accountNumber);
        $len = strlen($clean);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $suffix = substr($clean, -4);
        $maskLen = max(1, $len - 4);

        return str_repeat('*', $maskLen).$suffix;
    }
}
