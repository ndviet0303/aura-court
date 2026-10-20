<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\VenuePublicationRevision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewVenuePublication
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly EvaluateVenueReadiness $readiness,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(
        int $revisionId,
        int $reviewerId,
        string $decision,
        ?string $reason = null,
        ?array $feedbackFields = null
    ): VenuePublicationRevision {
        return DB::transaction(function () use ($revisionId, $reviewerId, $decision, $reason, $feedbackFields) {
            $revision = $this->venueRepo->findPublicationRevisionWithLock($revisionId);
            if (! $revision) {
                abort(404, 'Yêu cầu xuất bản không tồn tại.');
            }

            if (! $revision->isPending()) {
                abort(409, 'Yêu cầu xuất bản này đã được xử lý trước đó.');
            }

            // Lock the venue so a concurrent suspension or closure is seen, not overwritten
            $venue = $this->venueRepo->findByIdWithLock($revision->venue_id);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            // A stale revision may still be settled, but must never lift a suspension or reopen a closure
            $keepStatus = $venue->isSuspended() || $venue->isClosed() || $venue->isArchived();

            if ($decision === 'approve') {
                if ($keepStatus) {
                    throw ValidationException::withMessages([
                        'status' => 'Cơ sở đang bị tạm ngưng hoặc đã ngừng kinh doanh, không thể phê duyệt xuất bản.',
                    ]);
                }

                // Real-time readiness recheck before approval
                $eval = $this->readiness->execute($venue);
                if (! $eval['is_ready']) {
                    throw ValidationException::withMessages([
                        'readiness' => 'Không thể phê duyệt vì cơ sở hiện không còn đáp ứng các điều kiện sẵn sàng xuất bản.',
                        'missing_items' => $eval['missing_items'],
                    ]);
                }

                $updatedRevision = $this->venueRepo->updatePublicationRevision($revision, [
                    'status' => 'approved',
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'decision_reason' => $reason ?: 'Cơ sở đáp ứng đầy đủ tiêu chuẩn xuất bản.',
                ]);

                $this->venueRepo->updateStatus($venue, 'published', $reason);
                $venue->update([
                    'is_active' => true,
                    'published_at' => now(),
                    'approved_public_snapshot' => $revision->configuration_snapshot,
                ]);

                Cache::increment('venues_version');
            } elseif ($decision === 'revision_required') {
                if (empty($reason)) {
                    throw ValidationException::withMessages([
                        'reason' => 'Vui lòng cung cấp lý do yêu cầu chỉnh sửa/bổ sung cơ sở.',
                    ]);
                }

                $updatedRevision = $this->venueRepo->updatePublicationRevision($revision, [
                    'status' => 'revision_required',
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'decision_reason' => $reason,
                    'feedback_fields' => $feedbackFields,
                ]);

                if (! $keepStatus) {
                    $this->venueRepo->updateStatus($venue, 'revision_required', $reason);
                }
            } elseif ($decision === 'reject') {
                if (empty($reason)) {
                    throw ValidationException::withMessages([
                        'reason' => 'Vui lòng cung cấp lý do từ chối xuất bản cơ sở.',
                    ]);
                }

                $updatedRevision = $this->venueRepo->updatePublicationRevision($revision, [
                    'status' => 'rejected',
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'decision_reason' => $reason,
                ]);

                if (! $keepStatus) {
                    $this->venueRepo->updateStatus($venue, 'draft', $reason);
                }
            } else {
                throw ValidationException::withMessages([
                    'decision' => 'Quyết định thẩm định xuất bản không hợp lệ.',
                ]);
            }

            $this->notif->recordAudit(
                userId: $reviewerId,
                action: 'venue.publication.reviewed',
                auditable: $updatedRevision,
                metadata: [
                    'venue_id' => $venue->id,
                    'decision' => $decision,
                    'reason' => $reason,
                ]
            );

            return $updatedRevision;
        });
    }
}
