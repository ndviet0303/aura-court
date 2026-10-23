<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function firstOrCreate(string $pairKey, array $attributes): Conversation
    {
        try {
            return Conversation::query()->firstOrCreate(['pair_key' => $pairKey], $attributes);
        } catch (QueryException) {
            // Two first messages at once: the other request created it.
            return Conversation::query()->where('pair_key', $pairKey)->firstOrFail();
        }
    }

    public function forUser(User $user, int $perPage): LengthAwarePaginator
    {
        $venueIds = $user->getAuthorizedVenueIds();
        $ownedOrganizationIds = $user->organizationMembers()
            ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->where('status', 'active')->pluck('organization_id');

        return Conversation::query()
            ->with(['userA', 'userB', 'venue', 'organization', 'latestMessage'])
            ->where(fn (Builder $mine) => $mine
                ->where('user_a_id', $user->id)
                ->orWhere('user_b_id', $user->id)
                ->orWhere(fn (Builder $venue) => $venue->where('type', Conversation::TYPE_VENUE)->whereIn('venue_id', $venueIds))
                ->orWhere(fn (Builder $support) => $support->where('type', Conversation::TYPE_SUPPORT)
                    ->when(! $user->isPlatformAdmin(), fn (Builder $owner) => $owner->whereIn('organization_id', $ownedOrganizationIds))))
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    public function messages(Conversation $conversation, ?int $afterId, ?int $beforeId, int $limit): Collection
    {
        $query = $conversation->messages()->with('sender');
        if ($afterId !== null) {
            return $query->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get();
        }

        return $query->when($beforeId, fn ($older, int $id) => $older->where('id', '<', $id))
            ->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    public function addMessage(Conversation $conversation, array $attributes): ConversationMessage
    {
        return DB::transaction(function () use ($conversation, $attributes) {
            $message = $conversation->messages()->create($attributes);
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message->load('sender');
        });
    }

    public function recipientIds(Conversation $conversation, string $side, int $senderId): array
    {
        $ids = match ($conversation->type) {
            Conversation::TYPE_CUSTOMER => [$conversation->user_a_id, $conversation->user_b_id],
            Conversation::TYPE_VENUE => $side === Conversation::SIDE_CUSTOMER
                ? $this->venueOperatorIds((int) $conversation->venue_id)
                : [$conversation->user_a_id],
            default => $side === Conversation::SIDE_OWNER
                ? User::query()->whereIn('role', ['admin', 'platform_admin'])->pluck('id')->all()
                : OrganizationMember::query()->where('organization_id', $conversation->organization_id)
                    ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->where('status', 'active')->pluck('user_id')->all(),
        };

        return array_values(array_unique(array_map('intval', array_diff(array_filter($ids), [$senderId]))));
    }

    /** @return list<int> the venue's organization owners and the staff assigned to it */
    private function venueOperatorIds(int $venueId): array
    {
        $organizationId = DB::table('venues')->where('id', $venueId)->value('organization_id');

        return OrganizationMember::query()
            ->where('organization_id', $organizationId)->where('status', 'active')
            ->whereIn('role', [OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_STAFF])
            ->get()
            ->filter(fn (OrganizationMember $member) => $member->role === OrganizationMember::ROLE_ORGANIZATION_OWNER
                || in_array($venueId, $member->assignedVenueIds(), true))
            ->pluck('user_id')
            ->all();
    }
}
