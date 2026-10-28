<?php

namespace App\Domain\Chat\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Domain\Chat\Rules\ChatDisplay;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Str;

/** FR-119, BR-161: an in-app notice of a new message to the other side only; never email. */
class NotifyChatRecipients
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(Conversation $conversation, ConversationMessage $message): void
    {
        $from = ChatDisplay::sideName($conversation, $message->sender_side, $message->sender);
        $preview = match (true) {
            $message->voucher !== null => 'đã gửi một voucher',
            $message->transfer !== null => 'đã hỏi nhận chuyển nhượng booking',
            $message->body !== null => Str::limit($message->body, 80),
            default => 'đã gửi một ảnh',
        };

        foreach ($this->conversations->recipientIds($conversation, $message->sender_side, (int) $message->sender_user_id) as $userId) {
            $this->notifications->recordOutboxNotification(
                key: "chat.message.{$message->id}.{$userId}",
                userId: $userId,
                eventType: 'chat.message',
                payload: [
                    'title' => 'Tin nhắn mới',
                    'body' => "{$from}: {$preview}",
                    'action_url' => "/messages?conversation={$conversation->id}",
                    'aggregate_type' => Conversation::class,
                    'aggregate_id' => $conversation->id,
                ],
            );
        }
    }
}
