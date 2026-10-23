<?php

namespace App\Domain\Community\Rules;

use App\Models\User;

/** BR-144: only the author edits or deletes a post, comment or review; managing a venue grants no such right. */
final class CommunityAccess
{
    public static function authorOrFail(int $authorId, User $actor, string $code = 'FEED_NOT_AUTHOR'): void
    {
        if ($authorId !== (int) $actor->id) {
            self::fail($code, 'Bạn chỉ được sửa hoặc xoá nội dung của chính mình.', 403);
        }
    }

    public static function fail(string $code, string $message, int $status = 422): never
    {
        abort(response()->json(['message' => $message, 'code' => $code], $status));
    }
}
