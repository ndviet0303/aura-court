<?php

namespace App\Http\Resources\Chat;

use App\Domain\Chat\Rules\ChatDisplay;
use App\Domain\Chat\Rules\ConversationAccess;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $side = ConversationAccess::side($this->resource, $viewer) ?? Conversation::SIDE_CUSTOMER;
        $last = $this->latestMessage;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'side' => $side,
            'title' => ChatDisplay::counterpart($this->resource, $side, $viewer),
            'venue' => $this->venue ? ['id' => $this->venue->id, 'name' => $this->venue->name, 'organization_id' => $this->venue->organization_id] : null,
            'customer_id' => $this->type === Conversation::TYPE_VENUE ? $this->user_a_id : null,
            'last_message' => $last ? [
                'preview' => $last->voucher ? 'Voucher '.$last->voucher['code'] : ($last->body ? Str::limit($last->body, 80) : 'Ảnh'),
                'mine' => $last->sender_user_id === $viewer->id,
            ] : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
