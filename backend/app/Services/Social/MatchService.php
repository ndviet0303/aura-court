<?php

namespace App\Services\Social;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\MatchBill;
use App\Models\MatchInvitation;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\OrganizationMember;
use App\Models\PlayerProfile;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Infrastructure\OutboxService;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Reservation\SlotSelectionPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchService
{
    public function __construct(private readonly RentalPriceCalculator $rentals) {}

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
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();
        abort_unless($startsAt->lt($endsAt), 422, 'Thời gian bắt đầu phải trước thời gian kết thúc.');
        abort_unless($startsAt->gt(now()->utc()), 422, 'Thời gian bắt đầu phải ở tương lai.');

        $regDeadline = ! empty($data['registration_deadline']) ? Carbon::parse($data['registration_deadline'])->utc() : null;
        abort_if($regDeadline && $regDeadline->gt($startsAt), 422, 'Hạn đăng ký phải trước hoặc bằng thời gian bắt đầu.');

        abort_if(isset($data['min_skill'], $data['max_skill']) && (int) $data['min_skill'] > (int) $data['max_skill'], 422, 'Trình độ tối thiểu phải nhỏ hơn hoặc bằng trình độ tối đa.');

        $isOwnerHosted = (! empty($data['is_owner_hosted']) || ! empty($data['court_id']))
            && empty($data['reservation_id'])
            && empty($data['entitlement_id']);

        if ($isOwnerHosted) {
            $courtId = $data['court_id'] ?? null;
            abort_unless($courtId, 422, 'Cần chọn sân cho buổi giao lưu.');
            $court = Court::with('venue')->findOrFail($courtId);
            abort_unless($court->venue !== null, 400, 'Sân không có thông tin cơ sở.');

            $orgId = $court->venue->organization_id;
            abort_unless(
                $creator->hasRoleInOrganization($orgId, OrganizationMember::ROLE_ORGANIZATION_OWNER),
                403,
                'Chỉ chủ cơ sở mới có quyền tạo buổi giao lưu cho sân này.'
            );

            $category = $data['category'] ?? MatchPost::CATEGORY_GIAO_LUU;
            abort_unless(in_array($category, MatchPost::VALID_CATEGORIES, true), 422, 'Phân loại buổi chơi không hợp lệ.');

            $skillLevels = (array) ($data['skill_levels'] ?? []);
            abort_if(empty($skillLevels), 422, 'Cần chọn ít nhất một nhãn trình độ.');
            $diff = array_diff($skillLevels, MatchPost::VALID_SKILL_LABELS);
            abort_unless(empty($diff), 422, 'Nhãn trình độ không hợp lệ.');

            $this->assertCourtCanHostSession($court);
            $this->checkCourtAvailability($court, $startsAt, $endsAt);

            $requiredParticipants = (int) $data['required_participants'];
            abort_unless($requiredParticipants > 0, 422, 'Số lượng người chơi phải lớn hơn 0.');

            $post = MatchPost::create([
                'organization_id' => $orgId,
                'creator_id' => $creator->id,
                'court_id' => $court->id,
                'title' => $data['title'],
                'visibility' => $data['visibility'] ?? 'public',
                'is_owner_hosted' => true,
                'category' => $category,
                'skill_levels' => array_values(array_unique($skillLevels)),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'registration_deadline' => $regDeadline,
                'approval_required' => ! empty($data['approval_required']),
                'city' => $data['city'] ?? $court->venue->address ?? null,
                'approximate_latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 2) : null,
                'approximate_longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 2) : null,
                'min_skill' => null,
                'max_skill' => null,
                'required_participants' => $requiredParticipants,
                'joined_count' => 0, // Host is not automatically added as a player
                'status' => 'open',
                'availability' => $data['availability'] ?? [],
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'is_owner_hosted' => true,
                    'court_id' => $court->id,
                    'category' => $category,
                    'skill_levels' => array_values(array_unique($skillLevels)),
                ]),
            ]);

            return $post->fresh(['court.venue', 'participations']);
        }

        $reservationId = $data['reservation_id'] ?? null;
        $entitlementId = $data['entitlement_id'] ?? null;
        $courtId = null;

        if ($reservationId || $entitlementId) {
            $reservation = null;
            $entitlement = null;

            if ($entitlementId) {
                $entitlement = ReservationEntitlement::with(['reservation.court.venue', 'court.venue'])->findOrFail($entitlementId);
                abort_unless($entitlement->user_id === $creator->id, 403, 'Customer does not hold this entitlement.');
                abort_unless(in_array($entitlement->status, ['active', 'confirmed'], true), 422, 'Entitlement is not active.');
                abort_unless($entitlement->organization_id === $organizationId, 422, 'Entitlement does not belong to this organization.');

                $reservation = $entitlement->reservation;
                abort_unless($reservation && $reservation->status === 'confirmed', 422, 'Entitlement reservation is not confirmed.');
                $reservationId = $reservation->id;
            }

            if ($reservationId && ! $reservation) {
                $reservation = Reservation::with(['court.venue', 'slots', 'entitlements'])->findOrFail($reservationId);
                abort_unless($reservation->user_id === $creator->id, 403, 'Customer is not the owner of this reservation.');
                abort_unless($reservation->status === 'confirmed', 422, 'Reservation is not confirmed.');
                abort_unless($reservation->organization_id === $organizationId, 422, 'Reservation does not belong to this organization.');

                if (! $entitlementId) {
                    $entitlement = $reservation->entitlements()->where('user_id', $creator->id)->whereIn('status', ['active', 'confirmed'])->first();
                    $entitlementId = $entitlement?->id;
                }
            }

            $court = $entitlement?->court ?? $reservation->court;
            abort_unless($court, 422, 'Reservation has no associated court.');
            abort_unless($court->venue && $court->venue->organization_id === $organizationId, 422, 'Reservation court does not belong to this organization.');

            $courtId = $court->id;

            $rStarts = $entitlement?->starts_at ?? $reservation->starts_at;
            $rEnds = $entitlement?->ends_at ?? $reservation->ends_at;
            abort_unless($rStarts && $rEnds, 422, 'Reservation has no valid time bounds.');

            $rStartsUtc = Carbon::parse($rStarts)->utc();
            $rEndsUtc = Carbon::parse($rEnds)->utc();

            abort_unless(
                $startsAt->gte($rStartsUtc) && $endsAt->lte($rEndsUtc),
                422,
                'Activity time window must be within reservation/entitlement time window.'
            );

            // Prevent double allocation: verify no other active MatchPost is bound to this reservation/entitlement.
            $alreadyBound = MatchPost::query()
                ->where(function ($query) use ($reservationId, $entitlementId) {
                    if ($reservationId) {
                        $query->where('reservation_id', $reservationId);
                    }
                    if ($entitlementId) {
                        $query->orWhere('entitlement_id', $entitlementId);
                    }
                })
                ->whereNotIn('status', ['cancelled', 'ended', 'completed'])
                ->exists();

            abort_if($alreadyBound, 409, 'Another active activity is already bound to this reservation/entitlement.');
        }

        $post = MatchPost::create([
            'organization_id' => $organizationId,
            'creator_id' => $creator->id,
            'court_id' => $courtId,
            'reservation_id' => $reservationId,
            'entitlement_id' => $entitlementId,
            'title' => $data['title'],
            'visibility' => $data['visibility'] ?? 'public',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'registration_deadline' => $regDeadline,
            'approval_required' => ! empty($data['approval_required']),
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

        MatchParticipation::create([
            'organization_id' => $organizationId,
            'match_post_id' => $post->id,
            'user_id' => $creator->id,
            'status' => 'joined',
            'joined_at' => now(),
        ]);

        return $post->fresh(['participations', 'court.venue']);
    }

    public function discover(?int $organizationId = null, array $filters = [])
    {
        $query = MatchPost::query()
            ->where('status', 'open')
            ->where('visibility', 'public')
            ->with(['creator', 'court.venue', 'bill'])
            ->latest('id');

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        if (isset($filters['court_id']) && $filters['court_id'] !== '') {
            $query->where('court_id', (int) $filters['court_id']);
        }

        if (isset($filters['venue_id']) && $filters['venue_id'] !== '') {
            $venueId = (int) $filters['venue_id'];
            $query->whereHas('court', fn ($q) => $q->where('venue_id', $venueId));
        }

        if (isset($filters['category']) && $filters['category'] !== '') {
            $query->where('category', trim((string) $filters['category']));
        }

        if (isset($filters['is_owner_hosted']) && $filters['is_owner_hosted'] !== '') {
            $query->where('is_owner_hosted', filter_var($filters['is_owner_hosted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['date']) && $filters['date'] !== '') {
            $parsedDate = Carbon::parse($filters['date'], config('app.timezone', 'Asia/Ho_Chi_Minh'));
            $startOfDayUtc = $parsedDate->copy()->startOfDay()->utc();
            $endOfDayUtc = $parsedDate->copy()->endOfDay()->utc();
            $query->whereBetween('starts_at', [$startOfDayUtc, $endOfDayUtc]);
        }

        // Allow open matches that haven't ended yet, or active matches from today onwards
        $query->where(function ($q) {
            $q->where('ends_at', '>=', now()->utc())
                ->orWhere('starts_at', '>=', now()->utc()->subHours(12))
                ->orWhere('starts_at', '>=', now()->startOfDay()->utc());
        });

        $posts = $query->get();
        $requestedMin = isset($filters['min_skill']) ? (int) $filters['min_skill'] : null;
        $requestedMax = isset($filters['max_skill']) ? (int) $filters['max_skill'] : null;
        $availability = $filters['availability'] ?? null;
        $city = isset($filters['city']) ? mb_strtolower(trim((string) $filters['city'])) : null;
        $lat = isset($filters['latitude']) ? (float) $filters['latitude'] : null;
        $lng = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radius = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;

        $requestedSkillLabel = $filters['skill_level'] ?? $filters['skill_label'] ?? null;

        return $posts->filter(function (MatchPost $post) use ($requestedMin, $requestedMax, $availability, $city, $lat, $lng, $radius, $requestedSkillLabel): bool {
            if ($post->joined_count >= $post->required_participants) {
                return false;
            }
            if ($city && ! str_contains(mb_strtolower((string) $post->city), $city)) {
                return false;
            }

            // Skill label filter for owner-hosted badminton sessions
            if ($requestedSkillLabel) {
                $skills = (array) ($post->skill_levels ?? $post->metadata['skill_levels'] ?? []);
                if (empty($skills) || ! in_array($requestedSkillLabel, $skills, true)) {
                    return false;
                }
            }

            if ($requestedMin !== null && $post->max_skill !== null && $post->max_skill < $requestedMin) {
                return false;
            }
            if ($requestedMax !== null && $post->min_skill !== null && $post->min_skill > $requestedMax) {
                return false;
            }
            if ($availability && ! empty(array_diff((array) $availability, (array) $post->availability))) {
                return false;
            }
            if ($radius !== null && $lat !== null && $lng !== null && $post->approximate_latitude !== null && $post->approximate_longitude !== null && $this->distance($lat, $lng, $post->approximate_latitude, $post->approximate_longitude) > $radius) {
                return false;
            }

            return true;
        })->values();
    }

    public function show(MatchPost $post, User $viewer): MatchPost
    {
        if ($post->visibility === 'invite_only' && $post->creator_id !== $viewer->id && ! $post->invitations()->where('invitee_id', $viewer->id)->whereIn('status', ['pending', 'accepted'])->exists()) {
            abort(404);
        }

        return $post->load(['participations.user', 'invitations', 'court.venue', 'bill.finalizedBy']);
    }

    public function invite(MatchPost $post, User $creator, int $inviteeId): MatchInvitation
    {
        abort_unless($post->creator_id === $creator->id, 403);
        abort_if($post->creator_id === $inviteeId, 422, 'Cannot invite yourself.');
        User::findOrFail($inviteeId);

        $invitation = MatchInvitation::firstOrCreate(
            ['match_post_id' => $post->id, 'invitee_id' => $inviteeId],
            ['organization_id' => $post->organization_id, 'invited_by' => $creator->id, 'status' => 'pending']
        );

        if ($invitation->wasRecentlyCreated) {
            DB::afterCommit(function () use ($invitation, $post, $creator, $inviteeId) {
                app(OutboxService::class)->enqueue([
                    'organization_id' => $post->organization_id,
                    'message_key' => "match_invitation:{$invitation->id}",
                    'channel' => 'notification',
                    'event_type' => 'social.match_invitation',
                    'aggregate_type' => MatchInvitation::class,
                    'aggregate_id' => $invitation->id,
                ], [
                    'user_id' => $inviteeId,
                    'notification' => [
                        'title' => 'Lời mời tham gia trận đấu',
                        'body' => "{$creator->name} đã mời bạn tham gia trận đấu: {$post->title}",
                        'type' => 'match_invitation',
                        'source_id' => $post->id,
                        'invitation_id' => $invitation->id,
                        'match_id' => $post->id,
                    ],
                ]);
            });
        }

        return $invitation;
    }

    public function inviteMany(MatchPost $post, User $creator, array $inviteeIds): array
    {
        abort_unless($post->creator_id === $creator->id, 403);

        $results = [];
        $invitations = [];
        $invitedCount = 0;
        $failedCount = 0;

        foreach ($inviteeIds as $inviteeId) {
            $inviteeId = (int) $inviteeId;

            if ($inviteeId === $creator->id) {
                $results[] = [
                    'invitee_id' => $inviteeId,
                    'status' => 'failed',
                    'reason' => 'Cannot invite match creator.',
                ];
                $failedCount++;

                continue;
            }

            $user = User::find($inviteeId);
            if (! $user) {
                $results[] = [
                    'invitee_id' => $inviteeId,
                    'status' => 'failed',
                    'reason' => 'User not found or does not exist.',
                ];
                $failedCount++;

                continue;
            }

            if ($post->joined_count >= $post->required_participants) {
                $results[] = [
                    'invitee_id' => $inviteeId,
                    'status' => 'failed',
                    'reason' => 'Match is already full.',
                ];
                $failedCount++;

                continue;
            }

            if ($post->participations()->where('user_id', $inviteeId)->exists()) {
                $results[] = [
                    'invitee_id' => $inviteeId,
                    'status' => 'failed',
                    'reason' => 'User already joined this match.',
                ];
                $failedCount++;

                continue;
            }

            $invitation = MatchInvitation::firstOrCreate(
                ['match_post_id' => $post->id, 'invitee_id' => $inviteeId],
                [
                    'organization_id' => $post->organization_id,
                    'invited_by' => $creator->id,
                    'status' => 'pending',
                ]
            );

            $invitations[] = $invitation;
            $results[] = [
                'invitee_id' => $inviteeId,
                'status' => 'invited',
                'invitation_id' => $invitation->id,
            ];
            $invitedCount++;
        }

        return [
            'invitations' => $invitations,
            'results' => $results,
            'summary' => [
                'total' => count($inviteeIds),
                'invited' => $invitedCount,
                'failed' => $failedCount,
            ],
        ];
    }

    public function join(MatchPost $post, User $user): MatchParticipation
    {
        if ($post->visibility === 'invite_only') {
            abort_unless($post->creator_id === $user->id || $post->invitations()->where('invitee_id', $user->id)->where('status', 'accepted')->exists(), 404);
        }

        if ($post->registration_deadline && now()->utc()->gt($post->registration_deadline->copy()->utc())) {
            abort(422, 'Registration deadline has passed.');
        }

        if ($post->isRosterClosed()) {
            abort(422, 'Roster is closed.');
        }

        $profile = PlayerProfile::query()
            ->where('organization_id', $post->organization_id)
            ->where('user_id', $user->id)
            ->first();

        if ($profile && $profile->skill_level !== null) {
            if ($post->min_skill !== null && $profile->skill_level < $post->min_skill) {
                abort(422, 'Player skill level does not meet activity requirements.');
            }
            if ($post->max_skill !== null && $profile->skill_level > $post->max_skill) {
                abort(422, 'Player skill level does not meet activity requirements.');
            }
        }

        return DB::transaction(function () use ($post, $user): MatchParticipation {
            $post = MatchPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($post->registration_deadline && now()->utc()->gt($post->registration_deadline->copy()->utc())) {
                abort(422, 'Registration deadline has passed.');
            }

            if ($post->isRosterClosed()) {
                abort(422, 'Roster is closed.');
            }

            if ($post->approval_required) {
                abort_unless($post->status === 'open' && $post->joined_count < $post->required_participants, 409, 'Match is full.');

                $existing = MatchParticipation::query()
                    ->where('match_post_id', $post->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    if (in_array($existing->status, [MatchParticipation::STATUS_JOINED, MatchParticipation::STATUS_PENDING_APPROVAL], true)) {
                        return $existing;
                    }

                    $existing->update([
                        'status' => MatchParticipation::STATUS_PENDING_APPROVAL,
                        'joined_at' => now(),
                    ]);

                    return $existing->fresh();
                }

                return MatchParticipation::create([
                    'organization_id' => $post->organization_id,
                    'match_post_id' => $post->id,
                    'user_id' => $user->id,
                    'status' => MatchParticipation::STATUS_PENDING_APPROVAL,
                    'joined_at' => now(),
                ]);
            }

            abort_unless($post->status === 'open' && $post->joined_count < $post->required_participants, 409, 'Match is full.');
            $participation = MatchParticipation::firstOrCreate(['match_post_id' => $post->id, 'user_id' => $user->id], ['organization_id' => $post->organization_id, 'status' => 'joined', 'joined_at' => now()]);
            if ($participation->wasRecentlyCreated) {
                $post->increment('joined_count');
                if ($post->joined_count >= $post->required_participants) {
                    $post->update(['status' => 'full']);
                }
            }

            return $participation->fresh();
        });
    }

    public function joinMatch(MatchPost $post, User $user): MatchParticipation
    {
        return $this->join($post, $user);
    }

    public function approveParticipant(MatchPost $match, User $organizer, int $userId): MatchParticipation
    {
        $this->assertCanManageMatch($match, $organizer);

        return DB::transaction(function () use ($match, $userId): MatchParticipation {
            $match = MatchPost::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            abort_unless($match->joined_count < $match->required_participants, 409, 'Match is full.');

            $participation = MatchParticipation::query()
                ->where('match_post_id', $match->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $participation->status === MatchParticipation::STATUS_PENDING_APPROVAL,
                422,
                'Participant is not pending approval.'
            );

            $participation->update(['status' => MatchParticipation::STATUS_JOINED]);
            $match->increment('joined_count');

            if ($match->joined_count >= $match->required_participants) {
                $match->update(['status' => 'full']);
            }

            return $participation->fresh();
        });
    }

    public function rejectParticipant(MatchPost $match, User $organizer, int $userId): MatchParticipation
    {
        $this->assertCanManageMatch($match, $organizer);

        return DB::transaction(function () use ($match, $userId): MatchParticipation {
            $participation = MatchParticipation::query()
                ->where('match_post_id', $match->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $participation->update(['status' => MatchParticipation::STATUS_REJECTED]);

            return $participation->fresh();
        });
    }

    public function closeRoster(MatchPost $match, User $organizer): MatchPost
    {
        $this->assertCanManageMatch($match, $organizer);

        $match->update(['roster_closed_at' => now()->utc()]);

        return $match->fresh();
    }

    public function recordAttendance(MatchPost $match, User $operator, int $userId, string $status, ?string $notes = null): MatchParticipation
    {
        abort_unless(
            in_array($status, MatchParticipation::VALID_ATTENDANCE_STATUSES, true),
            422,
            'Invalid attendance status.'
        );

        $this->assertCanRecordAttendance($match, $operator);

        return DB::transaction(function () use ($match, $operator, $userId, $status, $notes): MatchParticipation {
            $match = MatchPost::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            $participation = MatchParticipation::query()
                ->where('match_post_id', $match->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $wasJoined = $participation->status === MatchParticipation::STATUS_JOINED;

            $updateData = [
                'attendance_status' => $status,
                'attendance_recorded_by' => $operator->id,
                'attendance_recorded_at' => now(),
                'attendance_notes' => $notes,
            ];

            if ($status === MatchParticipation::ATTENDANCE_WITHDRAWN) {
                $updateData['status'] = MatchParticipation::STATUS_WITHDRAWN;
                if ($wasJoined && $match->joined_count > 0) {
                    $match->decrement('joined_count');
                    if ($match->status === 'full' && $match->joined_count < $match->required_participants) {
                        $match->update(['status' => 'open']);
                    }
                }
            } elseif ($status === MatchParticipation::ATTENDANCE_PRESENT || $status === MatchParticipation::ATTENDANCE_LATE) {
                $updateData['verified_at'] = now();
            }

            $participation->update($updateData);

            return $participation->fresh();
        });
    }

    public function assertCanManageMatch(MatchPost $post, User $user): void
    {
        if ($post->creator_id === $user->id) {
            return;
        }

        $canManage = $user->hasRoleInOrganization(
            $post->organization_id,
            [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
            ]
        );

        abort_unless($canManage, 403, 'Unauthorized to manage this match.');
    }

    public function assertCanRecordAttendance(MatchPost $post, User $user): void
    {
        if ($post->creator_id === $user->id) {
            return;
        }

        $canRecord = $user->hasRoleInOrganization(
            $post->organization_id,
            [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_STAFF,
            ]
        );

        abort_unless($canRecord, 403, 'Unauthorized to record attendance.');
    }

    public function respond(MatchInvitation $invitation, User $user, string $status): MatchInvitation
    {
        abort_unless($invitation->invitee_id === $user->id, 403, 'Unauthorized to respond to this invitation.');
        abort_unless(in_array($status, ['accepted', 'declined'], true), 422);
        abort_unless($invitation->status === 'pending', 409, 'Invitation has already been responded to.');

        return DB::transaction(function () use ($invitation, $user, $status) {
            $invitation->update(['status' => $status, 'responded_at' => now()]);
            if ($status === 'accepted') {
                $this->join($invitation->post, $user);
            }

            DB::afterCommit(function () use ($invitation, $user, $status) {
                $post = $invitation->post;
                app(OutboxService::class)->enqueue([
                    'organization_id' => $invitation->organization_id,
                    'message_key' => "match_invitation_response:{$invitation->id}:{$status}",
                    'channel' => 'notification',
                    'event_type' => 'social.match_invitation_response',
                    'aggregate_type' => MatchInvitation::class,
                    'aggregate_id' => $invitation->id,
                ], [
                    'user_id' => $post->creator_id,
                    'notification' => [
                        'title' => 'Phản hồi lời mời trận đấu',
                        'body' => "{$user->name} đã ".($status === 'accepted' ? 'chấp nhận' : 'từ chối')." lời mời tham gia trận đấu: {$post->title}",
                        'type' => 'match_invitation_response',
                        'source_id' => $post->id,
                        'invitation_id' => $invitation->id,
                        'status' => $status,
                    ],
                ]);
            });

            return $invitation->fresh();
        });
    }

    public function updatePost(MatchPost $post, User $user, array $data): MatchPost
    {
        $this->assertCanManageSession($post, $user);

        if ($post->isOwnerHosted()) {
            $court = Court::with('venue')->findOrFail($data['court_id'] ?? $post->court_id);
            abort_unless($court->venue && $user->hasRoleInOrganization($court->venue->organization_id, OrganizationMember::ROLE_ORGANIZATION_OWNER), 403, 'Bạn không phải chủ cơ sở của sân này.');

            $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at'])->utc() : $post->starts_at?->copy()->utc();
            $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at'])->utc() : $post->ends_at?->copy()->utc();
            abort_unless($startsAt && $endsAt && $startsAt->lt($endsAt), 422, 'Thời gian bắt đầu phải trước thời gian kết thúc.');

            // Moving a session onto another court is new business there; edits on its current court stay allowed.
            if ((int) $court->id !== (int) $post->court_id) {
                $this->assertCourtCanHostSession($court);
            }
            $this->checkCourtAvailability($court, $startsAt, $endsAt, $post->id);

            $updates = ['court_id' => $court->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt];

            if (isset($data['title'])) {
                $updates['title'] = $data['title'];
            }
            if (isset($data['visibility'])) {
                $updates['visibility'] = $data['visibility'];
            }
            if (isset($data['category'])) {
                abort_unless(in_array($data['category'], MatchPost::VALID_CATEGORIES, true), 422, 'Phân loại không hợp lệ.');
                $updates['category'] = $data['category'];
            }
            if (isset($data['skill_levels'])) {
                $skills = (array) $data['skill_levels'];
                abort_if(empty($skills), 422, 'Cần chọn ít nhất một nhãn trình độ.');
                abort_unless(empty(array_diff($skills, MatchPost::VALID_SKILL_LABELS)), 422, 'Nhãn trình độ không hợp lệ.');
                $updates['skill_levels'] = array_values(array_unique($skills));
            }
            if (isset($data['required_participants'])) {
                $req = (int) $data['required_participants'];
                abort_unless($req > 0, 422, 'Số lượng người chơi phải lớn hơn 0.');
                $updates['required_participants'] = $req;
            }
            if (isset($data['registration_deadline'])) {
                $updates['registration_deadline'] = ! empty($data['registration_deadline']) ? Carbon::parse($data['registration_deadline'])->utc() : null;
            }

            $post->update($updates);

            return $post->fresh(['court.venue', 'participations', 'bill']);
        }

        if (isset($data['starts_at'])) {
            $data['starts_at'] = Carbon::parse($data['starts_at'])->utc();
        }
        if (isset($data['ends_at'])) {
            $data['ends_at'] = Carbon::parse($data['ends_at'])->utc();
        }
        if (isset($data['registration_deadline'])) {
            $data['registration_deadline'] = ! empty($data['registration_deadline']) ? Carbon::parse($data['registration_deadline'])->utc() : null;
        }

        $post->update($data);

        return $post->fresh(['participations']);
    }

    public function endSession(MatchPost $post, User $user): MatchPost
    {
        abort_unless($post->isOwnerHosted(), 400, 'Chỉ áp dụng cho buổi do chủ cơ sở tổ chức.');
        $this->assertCanManageSession($post, $user);
        abort_if($post->status === 'cancelled', 422, 'Buổi chơi đã bị hủy.');

        if ($post->status !== 'ended' && $post->status !== 'completed') {
            $post->update(['status' => 'ended']);
        }

        return $post->fresh(['court.venue', 'participations.user', 'bill']);
    }

    public function getBill(MatchPost $post, User $viewer): ?MatchBill
    {
        abort_unless($post->isOwnerHosted(), 400, 'Chỉ áp dụng cho buổi do chủ cơ sở tổ chức.');

        $isHost = $post->creator_id === $viewer->id;
        $isParticipant = $post->participations()->where('user_id', $viewer->id)->exists();
        $court = $post->court ?? ($post->court_id ? Court::with('venue')->find($post->court_id) : null);
        $isFacilityOwner = $court?->venue && $viewer->hasRoleInOrganization($court->venue->organization_id, OrganizationMember::ROLE_ORGANIZATION_OWNER);

        abort_unless($isHost || $isParticipant || $isFacilityOwner, 403, 'Bạn không có quyền xem chi phí của buổi chơi này.');

        return $post->bill()->with('finalizedBy')->first();
    }

    public function saveDraftBill(MatchPost $post, User $user, array $data): MatchBill
    {
        abort_unless($post->isOwnerHosted(), 400, 'Chỉ áp dụng cho buổi do chủ cơ sở tổ chức.');
        $this->assertCanManageSession($post, $user);
        abort_unless($post->hasEnded(), 422, 'Chỉ có thể nhập bảng chi phí sau khi buổi chơi đã kết thúc.');

        $this->assertNoAuxiliaryCharges($data);

        $existingBill = $post->bill;
        if ($existingBill && $existingBill->isFinalized()) {
            abort(409, 'Bảng chi phí đã được chốt, không thể sửa bản nháp.');
        }

        abort_unless(isset($data['actual_headcount']), 422, 'Cần nhập số người chia phí.');
        $headcount = (int) $data['actual_headcount'];
        abort_unless($headcount > 0, 422, 'Số người chia phí phải lớn hơn 0.');

        $durationMinutes = isset($data['court_duration_minutes'])
            ? (int) $data['court_duration_minutes']
            : (isset($data['duration_hours']) ? (int) round(((float) $data['duration_hours']) * 60) : null);
        abort_unless($durationMinutes !== null && $durationMinutes > 0, 422, 'Thời gian chơi phải lớn hơn 0 phút.');

        abort_unless(isset($data['court_hourly_rate']), 422, 'Cần nhập đơn giá sân.');
        $courtRate = (int) $data['court_hourly_rate'];
        abort_unless($courtRate >= 0, 422, 'Đơn giá sân không được âm.');

        abort_unless(isset($data['shuttlecock_count']), 422, 'Cần nhập số quả cầu.');
        $rawCount = $data['shuttlecock_count'];
        abort_if(is_float($rawCount) && floor($rawCount) != $rawCount, 422, 'Số quả cầu phải là số nguyên.');
        $shuttleCount = (int) $rawCount;
        abort_unless($shuttleCount >= 0, 422, 'Số quả cầu không được âm.');

        abort_unless(isset($data['shuttlecock_unit_price']), 422, 'Cần nhập đơn giá quả cầu.');
        $shuttlePrice = (int) $data['shuttlecock_unit_price'];
        abort_unless($shuttlePrice >= 0, 422, 'Đơn giá cầu không được âm.');

        $calc = $this->calculateBill($headcount, $durationMinutes, $courtRate, $shuttleCount, $shuttlePrice);

        return DB::transaction(function () use ($post, $existingBill, $headcount, $durationMinutes, $courtRate, $shuttleCount, $shuttlePrice, $calc) {
            $court = $post->court ?? ($post->court_id ? Court::with('venue')->find($post->court_id) : null);
            $orgId = $court?->venue?->organization_id ?? $post->organization_id;

            $bill = MatchBill::updateOrCreate(
                ['match_post_id' => $post->id],
                [
                    'organization_id' => $orgId,
                    'actual_headcount' => $headcount,
                    'court_duration_minutes' => $durationMinutes,
                    'court_hourly_rate' => $courtRate,
                    'shuttlecock_count' => $shuttleCount,
                    'shuttlecock_unit_price' => $shuttlePrice,
                    'court_subtotal' => $calc['court_subtotal'],
                    'shuttlecock_subtotal' => $calc['shuttlecock_subtotal'],
                    'total_amount' => $calc['total_amount'],
                    'per_person_amount' => $calc['per_person_amount'],
                    'is_approximate' => $calc['is_approximate'],
                    'status' => MatchBill::STATUS_DRAFT,
                    'version' => ($existingBill?->version ?? 0) + 1,
                ]
            );

            return $bill->fresh('finalizedBy');
        });
    }

    public function finalizeBill(MatchPost $post, User $user, array $data): MatchBill
    {
        abort_unless($post->isOwnerHosted(), 400, 'Chỉ áp dụng cho buổi do chủ cơ sở tổ chức.');
        $this->assertCanManageSession($post, $user);
        abort_unless($post->hasEnded(), 422, 'Chỉ có thể chốt chi phí sau khi buổi chơi đã kết thúc.');

        $this->assertNoAuxiliaryCharges($data);

        abort_unless(isset($data['actual_headcount']), 422, 'Cần nhập số người chia phí.');
        $headcount = (int) $data['actual_headcount'];
        abort_unless($headcount > 0, 422, 'Số người chia phí phải lớn hơn 0.');

        $durationMinutes = isset($data['court_duration_minutes'])
            ? (int) $data['court_duration_minutes']
            : (isset($data['duration_hours']) ? (int) round(((float) $data['duration_hours']) * 60) : null);
        abort_unless($durationMinutes !== null && $durationMinutes > 0, 422, 'Thời gian chơi phải lớn hơn 0 phút.');

        abort_unless(isset($data['court_hourly_rate']), 422, 'Cần nhập đơn giá sân.');
        $courtRate = (int) $data['court_hourly_rate'];
        abort_unless($courtRate >= 0, 422, 'Đơn giá sân không được âm.');

        abort_unless(isset($data['shuttlecock_count']), 422, 'Cần nhập số quả cầu.');
        $rawCount = $data['shuttlecock_count'];
        abort_if(is_float($rawCount) && floor($rawCount) != $rawCount, 422, 'Số quả cầu phải là số nguyên.');
        $shuttleCount = (int) $rawCount;
        abort_unless($shuttleCount >= 0, 422, 'Số quả cầu không được âm.');

        abort_unless(isset($data['shuttlecock_unit_price']), 422, 'Cần nhập đơn giá quả cầu.');
        $shuttlePrice = (int) $data['shuttlecock_unit_price'];
        abort_unless($shuttlePrice >= 0, 422, 'Đơn giá cầu không được âm.');

        $calc = $this->calculateBill($headcount, $durationMinutes, $courtRate, $shuttleCount, $shuttlePrice);

        return DB::transaction(function () use ($post, $user, $data, $headcount, $durationMinutes, $courtRate, $shuttleCount, $shuttlePrice, $calc) {
            $bill = MatchBill::query()->where('match_post_id', $post->id)->lockForUpdate()->first();

            if ($bill && $bill->isFinalized()) {
                $inputs = $bill->snapshot['inputs'] ?? [];
                $isRetry = ($inputs['actual_headcount'] ?? null) === $headcount
                    && ($inputs['court_duration_minutes'] ?? null) === $durationMinutes
                    && ($inputs['court_hourly_rate'] ?? null) === $courtRate
                    && ($inputs['shuttlecock_count'] ?? null) === $shuttleCount
                    && ($inputs['shuttlecock_unit_price'] ?? null) === $shuttlePrice;

                if ($isRetry) {
                    return $bill->load('finalizedBy');
                }

                abort(409, 'Bảng chi phí đã được chốt và không thể sửa đổi.');
            }

            if ($bill && isset($data['version']) && (int) $data['version'] < $bill->version) {
                abort(409, 'Dữ liệu bảng chi phí đã có thay đổi mới hơn, vui lòng tải lại.');
            }

            $court = $post->court ?? ($post->court_id ? Court::with('venue')->find($post->court_id) : null);
            $orgId = $court?->venue?->organization_id ?? $post->organization_id;

            $snapshot = [
                'inputs' => [
                    'actual_headcount' => $headcount,
                    'court_duration_minutes' => $durationMinutes,
                    'court_hourly_rate' => $courtRate,
                    'shuttlecock_count' => $shuttleCount,
                    'shuttlecock_unit_price' => $shuttlePrice,
                ],
                'breakdown' => [
                    'court_subtotal' => $calc['court_subtotal'],
                    'shuttlecock_subtotal' => $calc['shuttlecock_subtotal'],
                    'total_amount' => $calc['total_amount'],
                    'divisor' => $headcount,
                    'per_person_amount' => $calc['per_person_amount'],
                    'is_approximate' => $calc['is_approximate'],
                ],
                'host' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'finalized_at' => now()->toIso8601String(),
            ];

            $bill = MatchBill::updateOrCreate(
                ['match_post_id' => $post->id],
                [
                    'organization_id' => $orgId,
                    'actual_headcount' => $headcount,
                    'court_duration_minutes' => $durationMinutes,
                    'court_hourly_rate' => $courtRate,
                    'shuttlecock_count' => $shuttleCount,
                    'shuttlecock_unit_price' => $shuttlePrice,
                    'court_subtotal' => $calc['court_subtotal'],
                    'shuttlecock_subtotal' => $calc['shuttlecock_subtotal'],
                    'total_amount' => $calc['total_amount'],
                    'per_person_amount' => $calc['per_person_amount'],
                    'is_approximate' => $calc['is_approximate'],
                    'status' => MatchBill::STATUS_FINALIZED,
                    'version' => ($bill?->version ?? 0) + 1,
                    'finalized_by' => $user->id,
                    'finalized_at' => now(),
                    'snapshot' => $snapshot,
                ]
            );

            return $bill->fresh('finalizedBy');
        });
    }

    public function calculateBill(int $headcount, int $durationMinutes, int $courtHourlyRate, int $shuttleCount, int $shuttleUnitPrice): array
    {
        abort_unless($headcount > 0, 422, 'Số người chia phí phải lớn hơn 0.');
        abort_unless($durationMinutes > 0, 422, 'Thời gian chơi phải lớn hơn 0 phút.');
        abort_unless($courtHourlyRate >= 0, 422, 'Đơn giá sân không được âm.');
        abort_unless($shuttleCount >= 0, 422, 'Số quả cầu không được âm.');
        abort_unless($shuttleUnitPrice >= 0, 422, 'Đơn giá cầu không được âm.');

        $courtSubtotal = intdiv($durationMinutes * $courtHourlyRate, 60);
        $shuttleSubtotal = (int) ($shuttleCount * $shuttleUnitPrice);
        $totalAmount = $courtSubtotal + $shuttleSubtotal;

        $perPersonAmount = (int) round($totalAmount / $headcount);
        $isApproximate = ($totalAmount % $headcount) !== 0;

        return [
            'court_subtotal' => $courtSubtotal,
            'shuttlecock_subtotal' => $shuttleSubtotal,
            'total_amount' => $totalAmount,
            'per_person_amount' => $perPersonAmount,
            'is_approximate' => $isApproximate,
            'divisor' => $headcount,
        ];
    }

    public function checkCourtAvailability(Court $court, Carbon $startsAt, Carbon $endsAt, ?int $ignorePostId = null): void
    {
        $startsAtUtc = $startsAt->copy()->utc();
        $endsAtUtc = $endsAt->copy()->utc();

        app(SlotSelectionPolicy::class)->assertNoStop($court, $startsAt, $endsAt);

        $reservationExists = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->whereHas('reservation', fn ($r) => $r->whereIn('status', ['held', 'confirmed']))
            ->exists();
        abort_if($reservationExists, 409, 'Sân đã được giữ chỗ trong khoảng thời gian đã chọn.');

        $sessionExists = MatchPost::query()
            ->where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->when($ignorePostId, fn ($q) => $q->where('id', '!=', $ignorePostId))
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
        abort_if($sessionExists, 409, 'Sân đã có buổi giao lưu khác trong khoảng thời gian đã chọn.');
    }

    private function assertCourtCanHostSession(Court $court): void
    {
        // BR-37: no new session on a paused, closed or suspended venue; a session sets its own price, so an unpriced court may host one.
        // Global lock order (plan, court, venue) so a stop or suspension committed meanwhile is seen.
        $verifier = app(VerifyEffectiveBookingEligibility::class);
        $verifier->getEntitlement((int) $court->venue->organization_id);
        $locked = Court::query()->whereKey($court->id)->lockForUpdate()->firstOrFail();
        $eligibility = $verifier->execute($locked->setRelation('venue', Venue::query()->whereKey($locked->venue_id)->lockForUpdate()->firstOrFail()));
        abort_unless($eligibility['eligible'] || $eligibility['reason'] === 'COURT_UNPRICED', 409, $eligibility['message'] ?? '');
    }

    public function assertCanManageSession(MatchPost $post, User $user): void
    {
        abort_unless($post->creator_id === $user->id, 403, 'Chỉ người mở buổi mới có quyền quản lý.');

        if ($post->isOwnerHosted()) {
            $court = $post->court ?? ($post->court_id ? Court::with('venue')->find($post->court_id) : null);
            $orgId = $court?->venue?->organization_id ?? $post->organization_id;

            abort_unless(
                $user->hasRoleInOrganization($orgId, OrganizationMember::ROLE_ORGANIZATION_OWNER),
                403,
                'Bạn không còn là chủ cơ sở của sân này.'
            );
        }
    }

    public function assertNoAuxiliaryCharges(array $data): void
    {
        $disallowedKeys = [
            'drinks', 'water', 'ice', 'grip', 'grips', 'accessories',
            'services', 'extra_items', 'other_charges', 'custom_charges',
            'items', 'pos_order_id', 'pos_orders', 'auxiliary',
        ];
        foreach ($disallowedKeys as $key) {
            if (isset($data[$key]) && ! empty($data[$key])) {
                abort(422, 'Chỉ cho phép chi phí tiền sân và tiền cầu.');
            }
        }
    }

    public function getDefaultRates(Court $court, ?Carbon $time = null): array
    {
        // D24: the price per hour of the session's first minute; an unpriced minute suggests nothing.
        $start = CarbonImmutable::instance($time ?? now())->startOfMinute();
        $quote = $this->rentals->quoteEach($court, [[$start, $start->addMinute()]])[0] ?? null;

        return [
            'court_hourly_rate' => $quote?->snapshot['parts'][0]['price_per_hour'],
            'shuttlecock_unit_price' => null,
        ];
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
