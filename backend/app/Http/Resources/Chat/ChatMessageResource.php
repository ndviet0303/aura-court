<?php

namespace App\Http\Resources\Chat;

use App\Domain\Chat\Rules\ChatDisplay;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BR-158: a message shows under its side's name; who actually sent it is shown only to that same side.
 * The viewer side is passed in, so the list and the send response use the same rule.
 *
 * @mixin ConversationMessage
 */
class ChatMessageResource extends JsonResource
{
    public function __construct($resource, private readonly ?Conversation $conversation = null, private readonly ?string $viewerSide = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $conversation = $this->conversation ?? $this->resource->conversation;
        $shared = $this->sender_side !== Conversation::SIDE_CUSTOMER;

        return [
            'id' => $this->id,
            'side' => $this->sender_side,
            'sender_name' => ChatDisplay::sideName($conversation, $this->sender_side, $this->sender),
            'sent_by' => $shared && $this->sender_side === $this->viewerSide ? $this->sender?->name : null,
            'mine' => $this->sender_user_id === $request->user()?->id,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'voucher' => $this->voucher,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
