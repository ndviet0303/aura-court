<?php

namespace App\Jobs;

use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Backoff([1, 5, 15, 30, 60, 300, 900])]
class ProcessOwnerPaymentReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 8;

    public function __construct(public readonly int $receiptId) {}

    public function handle(OwnerPaymentAttemptService $payments): void
    {
        try {
            $payments->processReceipt($this->receiptId);
        } catch (\Throwable $exception) {
            $payments->markReceiptProcessingFailure($this->receiptId, $exception);
            throw $exception;
        }
    }
}
