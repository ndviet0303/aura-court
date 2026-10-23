<?php

namespace App\Http\Requests\Chat;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** UC-7.13–UC-7.15: who to talk to. The action checks the actor's side. */
class OpenConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Conversation::TYPE_CUSTOMER, Conversation::TYPE_VENUE, Conversation::TYPE_SUPPORT])],
            'user_id' => ['nullable', 'required_if:type,'.Conversation::TYPE_CUSTOMER, 'integer', 'exists:users,id'],
            'venue_id' => ['nullable', 'required_if:type,'.Conversation::TYPE_VENUE, 'integer', 'exists:venues,id'],
            'organization_id' => ['nullable', 'required_if:type,'.Conversation::TYPE_SUPPORT, 'integer', 'exists:organizations,id'],
        ];
    }
}
