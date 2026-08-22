<?php

namespace App\Jobs;

use App\Models\ProviderOperation;
use App\Services\PaymentProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecoverProviderOperations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 8;

    public function handle(PaymentProviderManager $providers): void
    {
        ProviderOperation::query()
            ->whereIn('status', ['pending', 'processing', 'submitted', 'retryable_failure'])
            ->whereNotNull('provider_resource_id')
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($operations) use ($providers): void {
                foreach ($operations as $operation) {
                    $this->recover($operation, $providers);
                }
            });
    }

    private function recover(ProviderOperation $operation, PaymentProviderManager $providers): void
    {
        try {
            $status = $providers->provider($operation->provider)->queryPayoutStatus($operation->provider_resource_id);
            $normalized = strtolower($status->status);
            $terminal = in_array($normalized, ['paid', 'completed', 'succeeded', 'failed', 'reversed'], true);
            $operation->update([
                'status' => $normalized,
                'response_payload' => $status->toArray(),
                'attempts' => $operation->attempts + 1,
                'completed_at' => $terminal ? now() : null,
                'next_attempt_at' => $terminal ? null : now()->addMinutes(5),
                'last_error' => $status->failureMessage,
            ]);
        } catch (\Throwable $exception) {
            $attempts = $operation->attempts + 1;
            $operation->update([
                'attempts' => $attempts,
                'status' => 'unresolved',
                'last_error' => $exception->getMessage(),
                'next_attempt_at' => now()->addSeconds(min(3600, 2 ** min($attempts, 10))),
            ]);
        }
    }
}
