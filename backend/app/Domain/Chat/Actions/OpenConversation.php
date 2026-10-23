<?php

namespace App\Domain\Chat\Actions;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Domain\Chat\DTOs\OpenConversationData;
use App\Domain\Chat\Rules\ConversationAccess;
use App\Models\Conversation;
use App\Models\User;

/** UC-7.13–UC-7.15: finds or starts the one conversation of a pair; opening it changes nothing else (BR-159). */
class OpenConversation
{
    public function __construct(private readonly ConversationRepositoryInterface $conversations) {}

    public function execute(User $actor, OpenConversationData $data): Conversation
    {
        return match ($data->type) {
            Conversation::TYPE_CUSTOMER => $this->betweenCustomers($actor, (int) $data->userId),
            Conversation::TYPE_VENUE => $this->withVenue($actor, (int) $data->venueId, $data->userId),
            default => $this->support($actor, (int) $data->organizationId),
        };
    }

    private function betweenCustomers(User $actor, int $userId): Conversation
    {
        if ($userId === $actor->id) {
            ConversationAccess::fail('CHAT_WITH_SELF', 'Không thể trò chuyện với chính mình.');
        }
        [$a, $b] = [min($actor->id, $userId), max($actor->id, $userId)];

        return $this->conversations->firstOrCreate("cc:{$a}:{$b}", ['type' => Conversation::TYPE_CUSTOMER, 'user_a_id' => $a, 'user_b_id' => $b]);
    }

    /** The customer opens it with the venue; an owner or assigned staff opens it with a customer of the venue (FR-111). */
    private function withVenue(User $actor, int $venueId, ?int $customerId): Conversation
    {
        $operates = $actor->canOperateVenue($venueId);
        if ($customerId !== null && ! $operates) {
            ConversationAccess::fail('VENUE_NOT_ASSIGNED', 'Bạn không được trả lời thay cơ sở này.', 403);
        }
        $customerId ??= $actor->id;
        if ($customerId === $actor->id && $operates) {
            ConversationAccess::fail('CHAT_WITH_SELF', 'Không thể trò chuyện với cơ sở mình đang quản lý.');
        }

        return $this->conversations->firstOrCreate("cv:{$venueId}:{$customerId}", [
            'type' => Conversation::TYPE_VENUE, 'user_a_id' => $customerId, 'venue_id' => $venueId,
        ]);
    }

    private function support(User $actor, int $organizationId): Conversation
    {
        if (! $actor->isPlatformAdmin() && ! $actor->isOrganizationOwner($organizationId)) {
            ConversationAccess::fail('CONVERSATION_FORBIDDEN', 'Chỉ chủ cơ sở của tổ chức hoặc quản trị viên được mở hỗ trợ đối tác.', 403);
        }

        return $this->conversations->firstOrCreate("oa:{$organizationId}", [
            'type' => Conversation::TYPE_SUPPORT, 'organization_id' => $organizationId,
        ]);
    }
}
