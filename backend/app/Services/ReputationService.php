<?php

namespace App\Services;

use App\Models\ModerationRecord;
use App\Models\PlayerProfile;
use App\Models\QualifyingActivity;
use App\Models\ReputationDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    private const DELTAS = ['attendance' => 2, 'late_cancellation' => -8, 'no_show' => -15, 'upheld_dispute' => -10];

    public function record(User $user, int $organizationId, string $type, string $sourceType, int $sourceId, ?int $verifiedBy = null): PlayerProfile
    {
        abort_unless(array_key_exists($type, self::DELTAS), 422, 'Unsupported qualifying activity.');
        return DB::transaction(function () use ($user, $organizationId, $type, $sourceType, $sourceId, $verifiedBy): PlayerProfile {
            QualifyingActivity::firstOrCreate(['organization_id' => $organizationId, 'user_id' => $user->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'type' => $type], ['score_delta' => self::DELTAS[$type], 'verified_by' => $verifiedBy, 'qualifies_at' => now()]);
            return $this->recalculate($user, $organizationId);
        });
    }

    public function recalculate(User $user, int $organizationId): PlayerProfile
    {
        $activities = QualifyingActivity::query()->where('organization_id', $organizationId)->where('user_id', $user->id)->latest('qualifies_at')->limit(20)->get();
        $score = max(0, min(100, 60 + (int) $activities->sum('score_delta')));
        return PlayerProfile::updateOrCreate(['organization_id' => $organizationId, 'user_id' => $user->id], ['reputation_score' => $score]);
    }

    public function fileDispute(User $user, int $organizationId, int $activityId, string $reason): ReputationDispute
    {
        $activity = QualifyingActivity::query()->where('organization_id', $organizationId)->findOrFail($activityId);
        abort_unless($activity->user_id === $user->id, 403);
        return ReputationDispute::create(['organization_id' => $organizationId, 'user_id' => $user->id, 'qualifying_activity_id' => $activity->id, 'filed_by' => $user->id, 'status' => 'open', 'reason' => $reason]);
    }

    public function resolveDispute(ReputationDispute $dispute, User $moderator, string $decision, string $resolution): ReputationDispute
    {
        abort_unless(in_array($decision, ['upheld', 'rejected'], true), 422);
        return DB::transaction(function () use ($dispute, $moderator, $decision, $resolution): ReputationDispute {
            abort_unless($dispute->status === 'open', 409);
            $dispute->update(['status' => 'resolved', 'decision' => $decision, 'decided_by' => $moderator->id, 'resolution' => $resolution, 'decided_at' => now()]);
            ModerationRecord::create(['organization_id' => $dispute->organization_id, 'actor_id' => $moderator->id, 'target_type' => ReputationDispute::class, 'target_id' => $dispute->id, 'action' => 'reputation_dispute_resolved', 'reason' => $resolution, 'metadata' => ['decision' => $decision]]);
            if ($decision === 'upheld') $this->record(User::findOrFail($dispute->user_id), $dispute->organization_id, 'upheld_dispute', ReputationDispute::class, $dispute->id, $moderator->id);
            return $dispute->fresh();
        });
    }
}
