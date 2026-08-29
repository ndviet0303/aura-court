<?php

namespace App\Services\Social;

use App\Models\MatchInvitation;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchService
{
    public function profile(User $user, int $organizationId, array $data): PlayerProfile
    {
        if (($data['skill_level'] ?? 1) < 1 || ($data['skill_level'] ?? 1) > 5) {
            throw ValidationException::withMessages(['skill_level' => 'Skill level must be between one and five.']);
        }
        return PlayerProfile::updateOrCreate(['organization_id' => $organizationId, 'user_id' => $user->id], [
            'skill_level' => (int) $data['skill_level'],
            'city' => $data['city'] ?? null,
            'approximate_latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 2) : null,
            'approximate_longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 2) : null,
            'location_precision_km' => max(1, (int) ($data['location_precision_km'] ?? 5)),
            'availability' => $data['availability'] ?? [],
            'preferred_positions' => $data['preferred_positions'] ?? [],
            'visibility' => $data['visibility'] ?? 'organization',
        ]);
    }

    public function createPost(User $creator, int $organizationId, array $data): MatchPost
    {
        $post = MatchPost::create([
            'organization_id' => $organizationId,
            'creator_id' => $creator->id,
            'title' => $data['title'],
            'visibility' => $data['visibility'] ?? 'public',
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'city' => $data['city'] ?? null,
            'approximate_latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 2) : null,
            'approximate_longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 2) : null,
            'min_skill' => $data['min_skill'] ?? null,
            'max_skill' => $data['max_skill'] ?? null,
            'required_participants' => $data['required_participants'],
            'joined_count' => 1,
            'availability' => $data['availability'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ]);
        MatchParticipation::create(['organization_id' => $organizationId, 'match_post_id' => $post->id, 'user_id' => $creator->id, 'status' => 'joined', 'joined_at' => now()]);
        return $post->fresh('participations');
    }

    public function discover(int $organizationId, array $filters = [])
    {
        $posts = MatchPost::query()->where('organization_id', $organizationId)->where('status', 'open')->where('visibility', 'public')->where('starts_at', '>', now())->with('creator')->latest('starts_at')->get();
        $requestedMin = isset($filters['min_skill']) ? (int) $filters['min_skill'] : null;
        $requestedMax = isset($filters['max_skill']) ? (int) $filters['max_skill'] : null;
        $availability = $filters['availability'] ?? null;
        $city = isset($filters['city']) ? strtolower((string) $filters['city']) : null;
        $lat = isset($filters['latitude']) ? (float) $filters['latitude'] : null;
        $lng = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radius = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;

        return $posts->filter(function (MatchPost $post) use ($requestedMin, $requestedMax, $availability, $city, $lat, $lng, $radius): bool {
            if ($post->joined_count >= $post->required_participants) return false;
            if ($city && ! str_contains(strtolower((string) $post->city), $city)) return false;
            if ($requestedMin !== null && $post->max_skill !== null && $post->max_skill < $requestedMin) return false;
            if ($requestedMax !== null && $post->min_skill !== null && $post->min_skill > $requestedMax) return false;
            if ($availability && ! empty(array_diff((array) $availability, (array) $post->availability))) return false;
            if ($radius !== null && $lat !== null && $lng !== null && $post->approximate_latitude !== null && $post->approximate_longitude !== null && $this->distance($lat, $lng, $post->approximate_latitude, $post->approximate_longitude) > $radius) return false;
            return true;
        })->values();
    }

    public function show(MatchPost $post, User $viewer): MatchPost
    {
        if ($post->visibility === 'invite_only' && $post->creator_id !== $viewer->id && ! $post->invitations()->where('invitee_id', $viewer->id)->where('status', 'accepted')->exists()) {
            abort(404);
        }
        return $post->load('participations', 'invitations');
    }

    public function invite(MatchPost $post, User $creator, int $inviteeId): MatchInvitation
    {
        abort_unless($post->creator_id === $creator->id, 403);
        return MatchInvitation::firstOrCreate(['match_post_id' => $post->id, 'invitee_id' => $inviteeId], ['organization_id' => $post->organization_id, 'invited_by' => $creator->id, 'status' => 'pending']);
    }

    public function join(MatchPost $post, User $user): MatchParticipation
    {
        if ($post->visibility === 'invite_only') {
            abort_unless($post->creator_id === $user->id || $post->invitations()->where('invitee_id', $user->id)->where('status', 'accepted')->exists(), 404);
        }
        return DB::transaction(function () use ($post, $user): MatchParticipation {
            $post = MatchPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            abort_unless($post->status === 'open' && $post->joined_count < $post->required_participants, 409, 'Match is full.');
            $participation = MatchParticipation::firstOrCreate(['match_post_id' => $post->id, 'user_id' => $user->id], ['organization_id' => $post->organization_id, 'status' => 'joined', 'joined_at' => now()]);
            if ($participation->wasRecentlyCreated) {
                $post->increment('joined_count');
                if ($post->joined_count >= $post->required_participants) $post->update(['status' => 'full']);
            }
            return $participation->fresh();
        });
    }

    public function respond(MatchInvitation $invitation, User $user, string $status): MatchInvitation
    {
        abort_unless($invitation->invitee_id === $user->id, 403);
        abort_unless(in_array($status, ['accepted', 'declined'], true), 422);
        $invitation->update(['status' => $status, 'responded_at' => now()]);
        if ($status === 'accepted') $this->join($invitation->post, $user);
        return $invitation->fresh();
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
