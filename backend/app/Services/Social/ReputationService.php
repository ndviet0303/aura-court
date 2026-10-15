<?php

namespace App\Services\Social;

use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\ModerationRecord;
use App\Models\OrganizationMember;
use App\Models\PlayerProfile;
use App\Models\QualifyingActivity;
use App\Models\ReputationDispute;
use App\Models\Reservation;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    private const DELTAS = ['attendance' => 2, 'late_cancellation' => -8, 'no_show' => -15];

    public function record(User $user, int $organizationId, string $type, string $sourceType, int $sourceId, ?int $verifiedBy = null): PlayerProfile
    {
        return $this->recordActivity($user, $organizationId, $type, $sourceType, $sourceId, $verifiedBy);
    }

    public function recordActivity(User $user, int $organizationId, string $type, string $sourceType, int $sourceId, ?int $verifiedBy = null): PlayerProfile
    {
        abort_unless(array_key_exists($type, self::DELTAS), 422, 'Unsupported qualifying activity.');

        $modelClass = $sourceType;
        if (! class_exists($modelClass)) {
            $modelClass = match (strtolower($sourceType)) {
                'reservation', 'reservations' => Reservation::class,
                'match_post', 'match', 'matches' => MatchPost::class,
                'match_participation' => MatchParticipation::class,
                'tournament' => Tournament::class,
                default => null,
            };
        }

        abort_unless($modelClass && class_exists($modelClass), 422, 'Source event does not exist.');
        abort_unless($modelClass::whereKey($sourceId)->exists(), 422, 'Source event does not exist.');

        $source = $modelClass::whereKey($sourceId)->firstOrFail();

        abort_unless((int) $source->organization_id === (int) $organizationId, 422, 'Source event does not belong to this organization.');

        $isParticipant = false;
        if ($source instanceof Reservation) {
            $isParticipant = ($source->user_id === $user->id)
                || (method_exists($source, 'entitlements') && $source->entitlements()->where('user_id', $user->id)->exists());
        } elseif ($source instanceof MatchPost) {
            $isParticipant = ($source->creator_id === $user->id)
                || (method_exists($source, 'participations') && $source->participations()->where('user_id', $user->id)->exists());
        } elseif ($source instanceof MatchParticipation) {
            $isParticipant = ($source->user_id === $user->id);
        } else {
            $isParticipant = ($source->user_id ?? null) === $user->id;
        }

        abort_unless($isParticipant, 422, 'User is not a participant in source event.');

        if ($verifiedBy !== null) {
            $actor = User::find($verifiedBy);
            abort_unless($actor, 403, 'Actor not found.');
            $hasAuthority = $actor->hasRoleInOrganization(
                $organizationId,
                [
                    OrganizationMember::ROLE_ORGANIZATION_OWNER,
                    OrganizationMember::ROLE_STAFF,
                ]
            );
            if (! $hasAuthority && $source instanceof MatchPost && $source->creator_id === $actor->id) {
                $hasAuthority = true;
            }
            abort_unless($hasAuthority, 403, 'Actor is not authorized to record reputation activity.');
        }

        return DB::transaction(function () use ($user, $organizationId, $type, $sourceType, $sourceId, $verifiedBy): PlayerProfile {
            QualifyingActivity::firstOrCreate(
                ['organization_id' => $organizationId, 'user_id' => $user->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'type' => $type],
                ['score_delta' => self::DELTAS[$type], 'verified_by' => $verifiedBy, 'qualifies_at' => now()]
            );

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

            if ($decision === 'upheld') {
                $activity = QualifyingActivity::find($dispute->qualifying_activity_id);
                if ($activity && $activity->score_delta < 0) {
                    $activity->update([
                        'score_delta' => 0,
                        'metadata' => array_merge($activity->metadata ?? [], [
                            'reversed_at' => now()->toIso8601String(),
                            'dispute_id' => $dispute->id,
                            'original_score_delta' => $activity->score_delta,
                        ]),
                    ]);
                }
                $this->recalculate(User::findOrFail($dispute->user_id), $dispute->organization_id);
            }

            return $dispute->fresh();
        });
    }
}
