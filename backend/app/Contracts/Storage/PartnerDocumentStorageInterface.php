<?php

namespace App\Contracts\Storage;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface PartnerDocumentStorageInterface
{
    /**
     * Store a private document file under $prefix (private/kyc for KYC, private/remediations for remediation evidence).
     *
     * @return array{
     *     file_path: string,
     *     file_name: string,
     *     mime_type: string,
     *     file_size: int,
     *     file_hash: string
     * }
     */
    public function storePrivate(UploadedFile $file, int $userId, string $documentType, string $prefix = 'private/kyc'): array;

    /**
     * Generate a scoped temporary URL for authorized reading.
     */
    public function temporaryReadUrl(string $filePath, int $expirationMinutes = 15): string;

    /**
     * Stream download response for a private file.
     */
    public function downloadResponse(string $filePath, ?string $fileName = null): StreamedResponse;

    /**
     * Delete a private document file from storage.
     */
    public function delete(string $filePath): bool;

    /**
     * Check if a private document exists in storage.
     */
    public function exists(string $filePath): bool;

    /**
     * Validate file format and size constraints.
     *
     * @throws \InvalidArgumentException
     */
    public function validateFile(UploadedFile $file): void;

    /**
     * Safely cleanup an aborted/failed upload.
     */
    public function cleanupFailedUpload(?string $filePath): void;

    /**
     * Mask sensitive identity card / passport numbers for safe response serialization.
     */
    public function maskDocumentNumber(string $number): string;

    /**
     * Mask bank account numbers for safe response serialization.
     */
    public function maskBankAccount(string $accountNumber): string;
}
