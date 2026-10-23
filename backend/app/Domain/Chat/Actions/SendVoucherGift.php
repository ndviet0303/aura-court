<?php

namespace App\Domain\Chat\Actions;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Chat\Rules\ConversationAccess;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\PromotionProgram;
use App\Models\User;

/**
 * FR-111, BR-143: the owner sends a free code of a running private program of the venue in its chat with a
 * customer, with what it offers. Sending records no voucher use and binds the code to no account.
 */
class SendVoucherGift
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly PromotionProgramRepositoryInterface $promotions,
        private readonly NotifyChatRecipients $notify,
    ) {}

    public function execute(Conversation $conversation, User $actor, string $code, ?string $note): ConversationMessage
    {
        if ($conversation->type !== Conversation::TYPE_VENUE) {
            ConversationAccess::fail('VOUCHER_NEEDS_VENUE_CHAT', 'Chỉ tặng voucher trong cuộc trò chuyện giữa khách và cơ sở.');
        }
        if (! $actor->canManageVenue((int) $conversation->venue_id)) {
            ConversationAccess::fail('VOUCHER_OWNER_ONLY', 'Chỉ chủ cơ sở được tặng voucher.', 403);
        }

        $voucher = $this->promotions->findCode(mb_strtoupper(trim($code)));
        $program = $voucher?->program;
        if ($program === null
            || $program->type !== PromotionProgram::TYPE_PRIVATE
            || (int) $program->venue_id !== (int) $conversation->venue_id
            || $program->statusAt() !== PromotionProgram::STATUS_RUNNING
            || $voucher->activeUse !== null) {
            ConversationAccess::fail('VOUCHER_NOT_GIFTABLE', 'Mã không thuộc voucher riêng đang hiệu lực của cơ sở hoặc đã được dùng.');
        }

        $message = $this->conversations->addMessage($conversation, [
            'sender_user_id' => $actor->id,
            'sender_side' => Conversation::SIDE_VENUE,
            'body' => $note,
            'voucher' => [
                'code' => $voucher->code,
                'program' => $program->name,
                'discount_kind' => $program->discount_kind,
                'percent_bp' => $program->percent_bp,
                'amount' => $program->amount,
                'max_discount' => $program->max_discount,
                'min_rental' => $program->min_rental,
                'courts' => $program->courts->pluck('name')->all(),
                'starts_at' => $program->starts_at?->toIso8601String(),
                'ends_at' => $program->ends_at?->toIso8601String(),
            ],
        ]);
        $this->notify->execute($conversation, $message);

        return $message;
    }
}
