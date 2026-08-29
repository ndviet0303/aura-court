<?php

namespace App\Services\Payment;

use App\Models\OutboxMessage;

class ProviderOutboxHandler
{
    public function handle(OutboxMessage $message): void
    {
        // Provider work is persisted in ProviderOperation and dispatched from
        // this seam so a retry cannot create a second external instruction.
        if (! isset($message->payload['operation'])) {
            throw new \InvalidArgumentException('Provider outbox messages require an operation payload.');
        }
    }
}
