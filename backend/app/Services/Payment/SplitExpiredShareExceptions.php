<?php

namespace App\Services\Payment;

use App\Models\FinanceException;
use App\Models\SplitGroup;
use App\Models\SplitShare;

/**
 * Money already received for an expired split group stays traceable per payer. The owner settles it outside
 * the platform; no refund obligation is created.
 */
class SplitExpiredShareExceptions
{
    public function record(SplitGroup $group): void
    {
        /** @var iterable<SplitShare> $paidShares */
        $paidShares = $group->shares()->where('status', 'paid')->get();
        foreach ($paidShares as $share) {
            FinanceException::create([
                'organization_id' => $group->organization_id,
                'type' => 'split_expired_paid_share',
                'severity' => 'high',
                'status' => 'open',
                'amount' => (int) $share->paid_amount,
                'currency' => 'VND',
                'reference' => 'split-share:'.$share->id,
                'description' => 'A split group expired after this share was paid; the owner settles it with the payer outside the platform.',
                'links' => [
                    'split_group_id' => $group->id,
                    'split_share_id' => $share->id,
                    'reservation_id' => $group->reservation_id,
                    'payer_id' => $share->payer_id,
                    'payment_reference' => $share->payment_reference,
                ],
                'detected_at' => now(),
                'due_at' => now()->addDay(),
            ]);
        }
    }
}
