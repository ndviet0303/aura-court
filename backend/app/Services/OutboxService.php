<?php

namespace App\Services;

use App\Jobs\ProcessOutboxMessage;
use App\Models\OutboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OutboxService
{
    public function enqueue(array $attributes, array $payload): OutboxMessage
    {
        $key = (string) ($attributes['message_key'] ?? '');
        abort_if($key === '', 422, 'Outbox message_key is required.');
        try {
            $message = DB::transaction(fn () => OutboxMessage::firstOrCreate(['message_key' => $key], array_merge($attributes, [
                'payload' => $payload,
                'status' => 'pending',
                'available_at' => now(),
            ])));
        } catch (QueryException $exception) {
            if (! $this->isDuplicate($exception)) {
                throw $exception;
            }
            $message = OutboxMessage::where('message_key', $key)->firstOrFail();
        }

        if ($message->wasRecentlyCreated && ! $message->published_at) {
            ProcessOutboxMessage::dispatch($message->id);
        }
        return $message;
    }

    public function claim(OutboxMessage $message): ?OutboxMessage
    {
        return DB::transaction(function () use ($message) {
            $locked = OutboxMessage::query()->whereKey($message->id)->lockForUpdate()->first();
            if (! $locked || $locked->published_at || $locked->status === 'processing') {
                return null;
            }
            $locked->update(['status' => 'processing', 'locked_at' => now(), 'attempts' => $locked->attempts + 1]);
            return $locked;
        });
    }

    public function sent(OutboxMessage $message): void
    {
        $message->update(['status' => 'published', 'published_at' => now(), 'locked_at' => null, 'last_error' => null]);
    }

    public function failed(OutboxMessage $message, \Throwable $exception): void
    {
        $message->update([
            'status' => 'pending',
            'locked_at' => null,
            'last_error' => $exception->getMessage(),
            'available_at' => now()->addSeconds(min(3600, 2 ** min($message->attempts, 10))),
        ]);
    }

    private function isDuplicate(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'outbox_messages') && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
