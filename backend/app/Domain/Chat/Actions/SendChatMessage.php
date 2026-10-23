<?php

namespace App\Domain\Chat\Actions;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Chat\DTOs\ChatMessageData;
use App\Domain\Chat\Rules\ConversationAccess;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;

/** FR-118, BR-158, BR-159: a text or image message, stored with its real sender; it changes no booking or result. */
class SendChatMessage
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly FileStorageServiceInterface $files,
        private readonly NotifyChatRecipients $notify,
    ) {}

    public function execute(Conversation $conversation, User $actor, ChatMessageData $data): ConversationMessage
    {
        $side = ConversationAccess::sideOrFail($conversation, $actor);
        $message = $this->conversations->addMessage($conversation, [
            'sender_user_id' => $actor->id,
            'sender_side' => $side,
            'body' => $data->body,
            'image_url' => $data->image ? $this->files->store($data->image, 'chat')['url'] : null,
        ]);
        $this->notify->execute($conversation, $message);

        return $message;
    }
}
