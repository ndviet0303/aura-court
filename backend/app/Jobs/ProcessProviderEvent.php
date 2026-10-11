<?php

namespace App\Jobs;

use App\Models\ProviderEvent;
use App\Services\Payment\ProviderEventHandler;
use App\Services\Payment\ProviderWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessProviderEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public readonly int $providerEventId)
    {
        $this->afterCommit = true;
    }

    public function handle(ProviderWebhookService $webhooks): void
    {
        $event = ProviderEvent::findOrFail($this->providerEventId);
        if ($event->processed_at) {
            return;
        }

        try {
            // Financial posting is deliberately isolated behind this durable
            // job. The event is acknowledged only after its handler commits.
            app(ProviderEventHandler::class)->handle($event);
            $webhooks->markProcessed($event);
        } catch (\Throwable $exception) {
            $webhooks->markFailed($event, $exception);
            throw $exception;
        }
    }
}
