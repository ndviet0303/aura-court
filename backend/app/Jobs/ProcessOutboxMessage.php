<?php

namespace App\Jobs;

use App\Models\OutboxMessage;
use App\Services\OutboxDispatcher;
use App\Services\OutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOutboxMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public readonly int $outboxMessageId) { $this->afterCommit = true; }

    public function handle(OutboxService $outbox, OutboxDispatcher $dispatcher): void
    {
        $message = OutboxMessage::findOrFail($this->outboxMessageId);
        if ($message->published_at) {
            return;
        }
        $claimed = $outbox->claim($message);
        if (! $claimed) {
            return;
        }
        try {
            $dispatcher->dispatch($claimed);
            $outbox->sent($claimed);
        } catch (\Throwable $exception) {
            $outbox->failed($claimed, $exception);
            throw $exception;
        }
    }
}
