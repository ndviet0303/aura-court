<?php

namespace App\Domain\Partner\DTOs;

use App\Http\Requests\Partner\AttachPartnerDocumentRequest;
use Illuminate\Http\UploadedFile;

readonly class AttachPartnerDocumentData
{
    public function __construct(
        public int $userId,
        public int $kycId,
        public string $documentType,
        public UploadedFile $file,
    ) {}

    public static function from(AttachPartnerDocumentRequest $request, int $kycId): self
    {
        return new self(
            userId: (int) $request->user()->id,
            kycId: $kycId,
            documentType: (string) $request->input('document_type'),
            file: $request->file('file'),
        );
    }
}
