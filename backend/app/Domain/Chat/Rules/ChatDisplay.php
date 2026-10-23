<?php

namespace App\Domain\Chat\Rules;

use App\Models\Conversation;
use App\Models\User;

/** BR-158: shared sides speak under the venue or organization name; the platform side as the administrator. */
final class ChatDisplay
{
    public static function sideName(Conversation $conversation, string $side, ?User $user): string
    {
        return match ($side) {
            Conversation::SIDE_VENUE => (string) $conversation->venue?->name,
            Conversation::SIDE_OWNER => (string) $conversation->organization?->name,
            Conversation::SIDE_ADMIN => 'Quản trị viên',
            default => (string) $user?->name,
        };
    }

    /** The side across from $side; in a customer–customer conversation, the other customer. */
    public static function counterpart(Conversation $conversation, string $side, User $viewer): string
    {
        return match ($conversation->type) {
            Conversation::TYPE_CUSTOMER => self::sideName($conversation, Conversation::SIDE_CUSTOMER,
                $conversation->user_a_id === $viewer->id ? $conversation->userB : $conversation->userA),
            Conversation::TYPE_VENUE => $side === Conversation::SIDE_CUSTOMER
                ? self::sideName($conversation, Conversation::SIDE_VENUE, null)
                : self::sideName($conversation, Conversation::SIDE_CUSTOMER, $conversation->userA),
            default => self::sideName($conversation, $side === Conversation::SIDE_ADMIN ? Conversation::SIDE_OWNER : Conversation::SIDE_ADMIN, null),
        };
    }
}
