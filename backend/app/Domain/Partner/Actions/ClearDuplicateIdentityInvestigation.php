<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;

class ClearDuplicateIdentityInvestigation
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $kycId, int $reviewerId, ?string $clearanceNote = null): HostKyc
    {
        return DB::transaction(function () use ($kycId, $reviewerId, $clearanceNote) {
            $kyc = $this->kycRepo->findByIdWithLock($kycId);
            if (! $kyc) {
                abort(404, 'Hồ sơ đối tác không tồn tại.');
            }

            $updated = $this->kycRepo->update($kyc, [
                'investigation_status' => 'cleared',
            ]);

            $this->notif->recordAudit(
                userId: $reviewerId,
                action: 'kyc.duplicate_investigation.cleared',
                auditable: $kyc,
                metadata: [
                    'reviewer_notes' => $clearanceNote,
                    'kyc_code' => $kyc->kyc_code,
                    'cleared_at' => now()->toIso8601String(),
                ]
            );

            return $updated;
        });
    }
}
