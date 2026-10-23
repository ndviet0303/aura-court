<?php

namespace App\Domain\Chat\DTOs;

use App\Http\Requests\Chat\SendChatMessageRequest;
use Illuminate\Http\UploadedFile;

readonly class ChatMessageData
{
    public function __construct(public ?string $body, public ?UploadedFile $image) {}

    public static function from(SendChatMessageRequest $request): self
    {
        $body = trim((string) $request->validated('body'));

        return new self($body === '' ? null : $body, $request->file('image'));
    }
}
