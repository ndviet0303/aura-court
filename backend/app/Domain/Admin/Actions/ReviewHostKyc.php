<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Admin\DTOs\ReviewHostKycData;
use App\Domain\Partner\Actions\ApprovePartnerApplication;
use App\Domain\Partner\Actions\ReviewPartnerApplication;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use InvalidArgumentException;

class ReviewHostKyc
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepository,
        private readonly ApprovePartnerApplication $approveAction,
        private readonly ReviewPartnerApplication $reviewAction,
    ) {}

    public function execute(ReviewHostKycData $data): HostKyc
    {
        $kyc = $this->kycRepository->findById($data->kycId);

        if (! $kyc) {
            throw new InvalidArgumentException("Hồ sơ KYC #{$data->kycId} không tồn tại.");
        }

        if ($data->decision === 'approve') {
            $dto = new ReviewPartnerApplicationData(
                reviewerId: $data->reviewerId,
                kycId: $data->kycId,
                decision: 'approve',
                reason: $data->reviewerNotes,
                reviewerNotes: $data->reviewerNotes,
                feedbackFields: null,
                expectedVersion: $kyc->version
            );

            return $this->approveAction->execute($dto);
        }

        $mappedDecision = $data->decision === 'request_supplement' ? 'revision_required' : 'reject';
        $dto = new ReviewPartnerApplicationData(
            reviewerId: $data->reviewerId,
            kycId: $data->kycId,
            decision: $mappedDecision,
            reason: $data->rejectionReason,
            reviewerNotes: $data->reviewerNotes,
            feedbackFields: null,
            expectedVersion: $kyc->version
        );

        return $this->reviewAction->execute($dto);
    }
}
