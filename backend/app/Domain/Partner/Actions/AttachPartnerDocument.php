<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Domain\Partner\DTOs\AttachPartnerDocumentData;
use App\Models\PartnerDocument;
use Illuminate\Support\Facades\DB;

class AttachPartnerDocument
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
        private readonly PartnerDocumentStorageInterface $storage,
    ) {}

    public function execute(AttachPartnerDocumentData $data): PartnerDocument
    {
        $kyc = $this->kycRepo->findByIdWithLock($data->kycId);

        if (! $kyc || $kyc->user_id !== $data->userId) {
            abort(403, 'Bạn không có quyền đính kèm tài liệu vào hồ sơ này.');
        }

        if (! $kyc->isDraft() && ! $kyc->isRevisionRequired()) {
            abort(409, 'Không thể thay đổi tài liệu của hồ sơ đang trong quá trình xét duyệt.');
        }

        // Store private document file
        $stored = $this->storage->storePrivate($data->file, $data->userId, $data->documentType);

        try {
            return DB::transaction(function () use ($kyc, $data, $stored) {
                // Find existing active document of the same type to replace
                $existingDoc = $kyc->documents()
                    ->where('document_type', $data->documentType)
                    ->whereIn('status', ['uploaded', 'verified'])
                    ->latest('version')
                    ->first();

                $newVersion = 1;
                if ($existingDoc) {
                    $newVersion = $existingDoc->version + 1;
                    $existingDoc->update(['status' => 'replaced']);
                }

                $document = $this->kycRepo->attachDocument($kyc, [
                    'document_type' => $data->documentType,
                    'file_path' => $stored['file_path'],
                    'file_name' => $stored['file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size' => $stored['file_size'],
                    'file_hash' => $stored['file_hash'],
                    'version' => $newVersion,
                    'status' => 'uploaded',
                    'uploaded_by' => $data->userId,
                ]);

                // Bump application version
                $this->kycRepo->update($kyc, ['version' => $kyc->version + 1]);

                return $document;
            });
        } catch (\Throwable $e) {
            // Clean up uploaded file if transaction fails
            $this->storage->cleanupFailedUpload($stored['file_path']);
            throw $e;
        }
    }
}
