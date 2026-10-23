<?php

namespace App\Domain\Chat\Rules;

use App\Models\Conversation;
use App\Models\User;

/**
 * BR-158: the side a user takes in a conversation, read on every request so a withdrawn venue assignment or
 * owner role closes the conversation from the next request. Errors carry a code the frontend maps.
 */
final class ConversationAccess
{
    public static function side(Conversation $conversation, User $user): ?string
    {
        return match ($conversation->type) {
            Conversation::TYPE_CUSTOMER => in_array($user->id, [$conversation->user_a_id, $conversation->user_b_id], true)
                ? Conversation::SIDE_CUSTOMER : null,
            Conversation::TYPE_VENUE => match (true) {
                $user->id === $conversation->user_a_id => Conversation::SIDE_CUSTOMER,
                $user->canOperateVenue((int) $conversation->venue_id) => Conversation::SIDE_VENUE,
                default => null,
            },
            Conversation::TYPE_SUPPORT => match (true) {
                $user->isPlatformAdmin() => Conversation::SIDE_ADMIN,
                $user->isOrganizationOwner((int) $conversation->organization_id) => Conversation::SIDE_OWNER,
                default => null,
            },
            default => null,
        };
    }

    public static function sideOrFail(Conversation $conversation, User $user): string
    {
        return self::side($conversation, $user)
            ?? self::fail('CONVERSATION_FORBIDDEN', 'Bạn không có quyền truy cập cuộc trò chuyện này.', 403);
    }

    public static function fail(string $code, string $message, int $status = 422): never
    {
        abort(response()->json(['message' => $message, 'code' => $code], $status));
    }
}
