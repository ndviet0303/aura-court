<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\PlayerProfile;
use App\Services\Infrastructure\OutboxService;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class FriendshipService
{
    /**
     * Get accepted friends for a user, optionally filtered by name/email/phone.
     */
    public function getFriends(User $user, ?string $search = null): SupportCollection
    {
        $friendships = Friendship::query()
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                    ->orWhere('addressee_id', $user->id);
            })
            ->with(['requester', 'addressee'])
            ->get();

        $friends = $friendships->map(function (Friendship $friendship) use ($user) {
            $friend = $friendship->otherUser($user->id);
            if (!$friend) {
                return null;
            }

            return [
                'id' => $friend->id,
                'name' => $friend->name,
                'email' => $friend->email,
                'phone' => $friend->phone,
                'avatar_url' => $friend->avatar_url,
                'play_level' => $friend->play_level,
                'friendship_id' => $friendship->id,
                'friendship_since' => $friendship->updated_at?->toIso8601String(),
            ];
        })->filter()->values();

        if ($search !== null && trim($search) !== '') {
            $term = mb_strtolower(trim($search));
            $friends = $friends->filter(function ($item) use ($term) {
                return str_contains(mb_strtolower($item['name'] ?? ''), $term)
                    || str_contains(mb_strtolower($item['email'] ?? ''), $term)
                    || str_contains(mb_strtolower($item['phone'] ?? ''), $term);
            })->values();
        }

        return $friends;
    }

    /**
     * Get incoming and outgoing pending friendship requests.
     */
    public function getRequests(User $user): array
    {
        $incoming = Friendship::query()
            ->where('addressee_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->with('requester:id,name,email,phone,avatar_url,play_level')
            ->latest()
            ->get();

        $outgoing = Friendship::query()
            ->where('requester_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->with('addressee:id,name,email,phone,avatar_url,play_level')
            ->latest()
            ->get();

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    /**
     * Send a friendship request from requester to addressee.
     */
    public function sendRequest(User $requester, int $addresseeId): Friendship
    {
        abort_if($requester->id === $addresseeId, 422, 'Cannot send friendship request to yourself.');
        User::findOrFail($addresseeId);

        return DB::transaction(function () use ($requester, $addresseeId) {
            $existing = Friendship::query()
                ->where(function ($q) use ($requester, $addresseeId) {
                    $q->where('requester_id', $requester->id)
                        ->where('addressee_id', $addresseeId);
                })
                ->orWhere(function ($q) use ($requester, $addresseeId) {
                    $q->where('requester_id', $addresseeId)
                        ->where('addressee_id', $requester->id);
                })
                ->first();

            if ($existing) {
                if ($existing->status === Friendship::STATUS_ACCEPTED) {
                    abort(409, 'Already friends.');
                }

                if ($existing->status === Friendship::STATUS_PENDING) {
                    if ($existing->requester_id === $requester->id) {
                        abort(409, 'Friendship request already sent.');
                    }
                    abort(409, 'A pending friendship request from this user already exists.');
                }

                $existing->update([
                    'requester_id' => $requester->id,
                    'addressee_id' => $addresseeId,
                    'status' => Friendship::STATUS_PENDING,
                ]);
                $friendship = $existing;
            } else {
                $friendship = Friendship::create([
                    'requester_id' => $requester->id,
                    'addressee_id' => $addresseeId,
                    'status' => Friendship::STATUS_PENDING,
                ]);
            }

            DB::afterCommit(function () use ($friendship, $requester, $addresseeId) {
                app(OutboxService::class)->enqueue([
                    'organization_id' => null,
                    'message_key' => "friendship_request:{$friendship->id}",
                    'channel' => 'notification',
                    'event_type' => 'social.friendship_request',
                    'aggregate_type' => Friendship::class,
                    'aggregate_id' => $friendship->id,
                ], [
                    'user_id' => $addresseeId,
                    'notification' => [
                        'title' => 'Lời mời kết bạn mới',
                        'body' => "{$requester->name} đã gửi lời mời kết bạn cho bạn.",
                        'type' => 'friendship_request',
                        'source_id' => $friendship->id,
                        'requester_id' => $requester->id,
                    ],
                ]);
            });

            return $friendship->fresh(['requester', 'addressee']);
        });
    }

    /**
     * Accept a pending friendship request.
     */
    public function acceptRequest(Friendship $friendship, User $user): Friendship
    {
        return DB::transaction(function () use ($friendship, $user) {
            abort_unless($friendship->addressee_id === $user->id, 403, 'Unauthorized to accept this request.');
            abort_unless($friendship->status === Friendship::STATUS_PENDING, 422, 'Request is not pending.');

            $friendship->update([
                'status' => Friendship::STATUS_ACCEPTED,
            ]);

            DB::afterCommit(function () use ($friendship, $user) {
                app(OutboxService::class)->enqueue([
                    'organization_id' => null,
                    'message_key' => "friendship_accepted:{$friendship->id}",
                    'channel' => 'notification',
                    'event_type' => 'social.friendship_accepted',
                    'aggregate_type' => Friendship::class,
                    'aggregate_id' => $friendship->id,
                ], [
                    'user_id' => $friendship->requester_id,
                    'notification' => [
                        'title' => 'Lời mời kết bạn được chấp nhận',
                        'body' => "{$user->name} đã đồng ý lời mời kết bạn của bạn.",
                        'type' => 'friendship_accepted',
                        'source_id' => $friendship->id,
                        'friend_id' => $user->id,
                    ],
                ]);
            });

            return $friendship->fresh(['requester', 'addressee']);
        });
    }

    /**
     * Decline a pending friendship request.
     */
    public function declineRequest(Friendship $friendship, User $user): Friendship
    {
        abort_unless($friendship->addressee_id === $user->id, 403, 'Unauthorized to decline this request.');
        abort_unless($friendship->status === Friendship::STATUS_PENDING, 422, 'Request is not pending.');

        $friendship->update([
            'status' => Friendship::STATUS_DECLINED,
        ]);

        return $friendship->fresh(['requester', 'addressee']);
    }

    /**
     * Remove or cancel a friendship or request.
     */
    public function removeFriendship(Friendship $friendship, User $user): void
    {
        abort_unless($friendship->involves($user->id), 403, 'Unauthorized to modify this friendship.');

        // If pending, only requester can cancel or addressee can decline
        if ($friendship->status === Friendship::STATUS_PENDING && $friendship->requester_id !== $user->id) {
            abort(403, 'Only the sender can cancel a pending request.');
        }

        $friendship->delete();
    }

    /**
     * Check if two users are accepted friends.
     */
    public function areFriends(int $userId1, int $userId2): bool
    {
        if ($userId1 === $userId2) {
            return false;
        }

        return Friendship::query()
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(function ($q) use ($userId1, $userId2) {
                $q->where(function ($sub) use ($userId1, $userId2) {
                    $sub->where('requester_id', $userId1)->where('addressee_id', $userId2);
                })->orWhere(function ($sub) use ($userId1, $userId2) {
                    $sub->where('requester_id', $userId2)->where('addressee_id', $userId1);
                });
            })
            ->exists();
    }
}
