<?php

namespace App\Services\Infrastructure;

use App\Models\Organization;
use App\Models\OutboxMessage;
use App\Models\OwnerPaymentReceipt;
use App\Models\ProviderEvent;
use App\Jobs\ProcessOwnerPaymentReceipt;
use App\Services\Payment\ProviderEventHandler;
use App\Services\Payment\ProviderWebhookService;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduledRecoveryService
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly OutboxDispatcher $dispatcher,
        private readonly ProviderWebhookService $webhooks,
        private readonly TenantContext $tenantContext,
        private readonly ProviderEventHandler $eventHandler,
        private readonly OwnerPaymentAttemptService $ownerPayments,
    ) {}

    /**
     * Recover due unpublished outbox messages with per-item isolation,
     * stale claim reclamation, and an 8-attempt limit.
     */
    public function recoverOutbox(int $batchSize = 50, int $leaseSeconds = 300): int
    {
        $messages = OutboxMessage::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereNull('published_at')
            ->whereNotIn('status', ['published', 'exhausted'])
            ->where(function ($query) {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->where('attempts', '<', 8)
            ->where(function ($query) use ($leaseSeconds) {
                $query->where('status', 'pending')
                    ->orWhere(function ($q) use ($leaseSeconds) {
                        $q->where('status', 'processing')
                            ->where(function ($stale) use ($leaseSeconds) {
                                $stale->whereNull('locked_at')
                                    ->orWhere('locked_at', '<=', now()->subSeconds($leaseSeconds));
                            });
                    });
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $recovered = 0;

        foreach ($messages as $message) {
            $claimed = null;
            try {
                $claimed = $this->outbox->claim($message, $leaseSeconds);
                if (! $claimed) {
                    continue;
                }

                $org = $claimed->organization_id ? Organization::find($claimed->organization_id) : null;
                if ($org) {
                    $this->tenantContext->executeInContext($org, fn () => $this->dispatcher->dispatch($claimed));
                } else {
                    $this->dispatcher->dispatch($claimed);
                }
                $this->outbox->sent($claimed);
                $recovered++;
            } catch (\Throwable $exception) {
                if ($claimed) {
                    $this->outbox->failed($claimed, $exception);
                }
                Log::warning("Scheduled outbox recovery failed for message {$message->id}: {$exception->getMessage()}", [
                    'message_id' => $message->id,
                    'message_key' => $message->message_key,
                    'attempts' => $claimed ? $claimed->attempts : $message->attempts,
                    'exception' => $exception,
                ]);
            }
        }

        return $recovered;
    }

    /**
     * Recover due unprocessed provider events with per-item isolation,
     * tenant scoping, and an 8-attempt limit.
     */
    public function recoverProviderEvents(int $batchSize = 50): int
    {
        $events = ProviderEvent::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereNull('processed_at')
            ->whereNotIn('status', ['processed', 'exhausted'])
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->where('attempts', '<', 8)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $recovered = 0;

        foreach ($events as $event) {
            try {
                $locked = DB::transaction(function () use ($event) {
                    $row = ProviderEvent::query()->withoutGlobalScope('tenant_isolation')->whereKey($event->id)->lockForUpdate()->first();
                    if (! $row || $row->processed_at || $row->status === 'exhausted') {
                        return null;
                    }

                    return $row;
                });

                if (! $locked) {
                    continue;
                }

                $org = $locked->organization_id ? Organization::find($locked->organization_id) : null;
                if ($org) {
                    $this->tenantContext->executeInContext($org, fn () => $this->eventHandler->handle($locked));
                } else {
                    $this->eventHandler->handle($locked);
                }
                $this->webhooks->markProcessed($locked);
                $recovered++;
            } catch (\Throwable $exception) {
                $this->webhooks->markFailed($event, $exception);
                Log::warning("Scheduled provider event recovery failed for event {$event->id}: {$exception->getMessage()}", [
                    'event_id' => $event->id,
                    'provider' => $event->provider,
                    'attempts' => $event->attempts + 1,
                    'exception' => $exception,
                ]);
            }
        }

        return $recovered;
    }

    /** Re-dispatch due authenticated owner receipts that have not reached a durable outcome. */
    public function recoverOwnerPaymentReceipts(int $batchSize = 50): int
    {
        $receipts = OwnerPaymentReceipt::query()
            ->where('status', OwnerPaymentReceipt::STATUS_PENDING)
            ->whereNull('processed_at')
            ->where('processing_attempts', '<', 8)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, min(500, $batchSize)))
            ->get();

        $queued = 0;
        foreach ($receipts as $receipt) {
            try {
                if (! $this->ownerPayments->claimReceiptForDispatch((int) $receipt->id)) {
                    continue;
                }

                ProcessOwnerPaymentReceipt::dispatch((int) $receipt->id)->afterCommit();
                $queued++;
            } catch (\Throwable $exception) {
                Log::warning('Owner payment receipt recovery dispatch failed.', [
                    'receipt_id' => $receipt->id,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $queued;
    }
}
