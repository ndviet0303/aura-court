<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Models\SuspensionRemediation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D14: a platform admin reviews the pending submission. approve lifts the suspension in the same transaction and,
 * like every review, never touches the venue's or courts' status. Lock order: venue, venue_suspensions, remediation.
 */
class ReviewSuspensionRemediation
{
    public function __construct(
        private readonly VenueSuspensionRepositoryInterface $suspensions,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(User $admin, int $remediationId, string $decision, string $feedback): SuspensionRemediation
    {
        $suspensionId = (int) $this->suspensions->remediation($remediationId)->venue_suspension_id;
        $venueId = (int) $this->suspensions->find($suspensionId)->venue_id;

        return DB::transaction(function () use ($admin, $remediationId, $decision, $feedback, $suspensionId, $venueId): SuspensionRemediation {
            $venue = $this->suspensions->venue($venueId, true);
            $suspension = $this->suspensions->find($suspensionId, true);
            $remediation = $this->suspensions->remediation($remediationId, true);
            abort_if($remediation->status !== 'pending', 409, 'Yêu cầu khắc phục không còn chờ xem xét.');

            $now = now();
            $this->suspensions->review($remediation, [
                'status' => SuspensionRemediation::DECISIONS[$decision],
                'reviewed_by' => $admin->id,
                'reviewed_at' => $now,
                'review_note' => $feedback,
            ]);
            if ($decision === 'approve') {
                $this->suspensions->lift($suspension, [
                    'lifted_at' => $now, 'lifted_by' => $admin->id, 'remediation_id' => $remediation->id,
                ]);
                DB::afterCommit(fn () => [Cache::increment('courts_version'), Cache::increment('venues_version')]);
            }
            $this->notifications->recordAudit($admin->id, 'venue.remediation.reviewed', $suspension, [
                'remediation_id' => $remediation->id, 'decision' => $decision,
            ]);
            $this->notifications->notifyRemediationReviewed($remediation->setRelation('suspension', $suspension->setRelation('venue', $venue)));

            return $remediation;
        });
    }
}
