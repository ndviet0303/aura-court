<?php

namespace App\Contracts\Repositories;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** FR-118 conversations and messages; module 07 D8. */
interface ConversationRepositoryInterface
{
    /** @param  array<string, mixed>  $attributes */
    public function firstOrCreate(string $pairKey, array $attributes): Conversation;

    /**
     * The conversations the user takes a side in (BR-158), latest message first, with what the list shows.
     *
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function forUser(User $user, int $perPage): LengthAwarePaginator;

    /**
     * Messages after $afterId (polling), else the $limit latest before $beforeId (null: the latest); oldest first.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function messages(Conversation $conversation, ?int $afterId, ?int $beforeId, int $limit): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function addMessage(Conversation $conversation, array $attributes): ConversationMessage;

    /**
     * FR-119: who is told of a message sent from $side: the other side, read when the message is sent.
     *
     * @return list<int>
     */
    public function recipientIds(Conversation $conversation, string $side, int $senderId): array;
}
